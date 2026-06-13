<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class PaymentController extends Controller
{
    public function getPublishableKey(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'publishable_key' => config('services.stripe.key'),
        ]);
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'amount'   => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
        ]);
       Log::info('Incoming Create Payment Intent Payload:', [
            'url'     => $request->fullUrl(),
            'method'  => $request->method(),
            'payload' => $request->all(),
            'user_id' => $request->user()?->id,
        ]);
        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $user = $request->user();

            $paymentIntent = PaymentIntent::create([
                'amount'      => (int) $request->amount,
                'currency'    => strtolower($request->input('currency', 'usd')),
                'description' => 'Expense Tracker Premium Access',
                'metadata'    => [
                    'user_id'    => (string) $user->id,
                    'user_email' => $user->email,
                    'feature'    => 'gemini_premium_access',
                ],
            ]);

            return response()->json([
                'success'            => true,
                'client_secret'      => $paymentIntent->client_secret,
                'payment_intent_id'  => $paymentIntent->id,
                'amount'             => $paymentIntent->amount,
                'currency'           => $paymentIntent->currency,
            ]);

        } catch (ApiErrorException $e) {
            return response()->json(['success' => false, 'message' => 'Stripe API error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Payment error: ' . $e->getMessage()], 500);
        }
    }

    public function confirmPayment(Request $request): JsonResponse
    {   
        
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);
        Log::info($request);
        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status === 'requires_confirmation') {
                $paymentIntent = PaymentIntent::confirm($request->payment_intent_id);
            }

            // Ownership check: compare metadata user_id to authenticated user
            $metaUserId = $paymentIntent->metadata['user_id'] ?? null;

            if ((string) $metaUserId !== (string) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment does not belong to the authenticated user.',
                ], 403);
            }

            $wasAlreadyPremium = (bool) $request->user()->is_premium;

            if ($paymentIntent->status === 'succeeded') {
                $this->activatePremium($paymentIntent);
            }

            // Force fresh DB read after potential premium activation
            // Also check if payment was successful - if so, user should be premium
            $freshUser = $request->user()->fresh();
            $paymentSucceeded = $paymentIntent->status === 'succeeded';
            $isPremiumNow = $paymentSucceeded || (bool) $freshUser->is_premium;

            // Build response data
            $responseData = [
                'success'    => true,
                'status'     => $paymentIntent->status,
                'amount'     => $paymentIntent->amount / 100,
                'currency'   => $paymentIntent->currency,
                'is_premium' => $isPremiumNow,
            ];

            // If user just became premium (or already was), include the Gemini API key
            // Once premium, stays premium forever - they can always access the API key
            if ($isPremiumNow) {
                $responseData['gemini_api_key'] = config('services.gemini.api_key');
            }

            return response()->json($responseData);

        } catch (ApiErrorException $e) {
            return response()->json(['success' => false, 'message' => 'Stripe API error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Payment error: ' . $e->getMessage()], 500);
        }
    }

    public function premiumStatus(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $isPremium = (bool) $user->is_premium;

        $responseData = [
            'success'    => true,
            'is_premium' => $isPremium,
        ];

        // Once premium, user can always access the Gemini API key
        if ($isPremium) {
            $responseData['gemini_api_key'] = config('services.gemini.api_key');
        }

        return response()->json([
    'success' => true,
    'data' => [
        'is_premium' => $isPremium,
        'gemini_api_key' => $isPremium
            ? config('services.gemini.api_key')
            : null,
    ]
]);
    }

 
    public function getGeminiApiKey(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        if (! $user->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires a premium subscription.',
            ], 403);
        }

        return response()->json([
            'success'       => true,
            'gemini_api_key' => config('services.gemini.api_key'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Webhook (real Stripe events — signature-verified, no Sanctum token)
    // -------------------------------------------------------------------------

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload       = $request->getContent();
        $sigHeader     = $request->header('stripe-signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            // constructEvent returns a real Stripe\Event object, not a plain stdClass.
            // We pass it directly to processWebhookEvent — no (object) cast needed.
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook error'], 400);
        }

        return $this->processWebhookEvent($event);
    }

    // -------------------------------------------------------------------------
    // Test webhook (dev/staging only — bypasses signature verification)
    // -------------------------------------------------------------------------

    public function handleTestWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'type'            => 'required|string',
            'data.object.id'  => 'required|string',
        ]);

        // BUG FIX: The original code did (object) $request->all() and then used
        // data_get() with dot notation on the resulting stdClass. data_get() only
        // resolves dot-notation on arrays and ArrayAccess objects — it does NOT
        // traverse stdClass properties. The result was metadata.user_id always
        // resolving to null, so activatePremium() silently bailed and is_premium
        // was never set.
        //
        // Fix: keep the payload as a plain array so data_get() works correctly.
        $eventArray = $request->all();

        // Wrap in a minimal object that processWebhookEvent can consume,
        // keeping data.object as an array (data_get handles array traversal).
        $event = new class($eventArray) {
            public string $type;
            public array  $data;

            public function __construct(array $payload)
            {
                $this->type = $payload['type'];
                $this->data = $payload['data'] ?? [];
            }
        };

        return $this->processWebhookEvent($event);
    }

    // -------------------------------------------------------------------------
    // Shared event dispatcher
    // -------------------------------------------------------------------------

    private function processWebhookEvent(object $event): JsonResponse
    {
        // For real Stripe\Event objects, $event->data->object is a StripeObject.
        // For our test shim, $event->data is a plain array.
        // data_get() handles both: arrays and ArrayAccess (StripeObject implements it).
        $dataObject = data_get($event, 'data.object') ?? data_get((array) $event->data, 'object');

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($dataObject);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($dataObject);
                break;

            case 'charge.refunded':
                $this->handleRefund($dataObject);
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event->type);
        }

        return response()->json(['received' => true]);
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    private function handlePaymentSuccess(mixed $paymentIntent): void
    {
        $this->activatePremium($paymentIntent);

        Log::info('Payment succeeded', [
            'payment_intent_id' => data_get($paymentIntent, 'id'),
            'user_id'           => data_get($paymentIntent, 'metadata.user_id'),
            'amount'            => data_get($paymentIntent, 'amount', 0) / 100,
        ]);
    }

    private function handlePaymentFailed(mixed $paymentIntent): void
    {
        $userId    = data_get($paymentIntent, 'metadata.user_id');
        $paymentId = data_get($paymentIntent, 'id');

        if ($userId && $paymentId) {
            Payment::updateOrCreate(
                ['stripe_payment_intent_id' => $paymentId],
                [
                    'user_id'  => $userId,
                    'amount'   => data_get($paymentIntent, 'amount', 0) / 100,
                    'currency' => data_get($paymentIntent, 'currency'),
                    'status'   => data_get($paymentIntent, 'status', 'failed'),
                    'paid_at'  => null,
                ]
            );
        }

        Log::warning('Payment failed', [
            'payment_intent_id' => $paymentId,
            'user_id'           => $userId,
            'error'             => data_get($paymentIntent, 'last_payment_error.message', 'Unknown'),
        ]);
    }

    private function handleRefund(mixed $charge): void
    {
        // Note: Premium status is permanent once granted.
        // Even if refund is issued, user keeps premium access forever.
        // We only update the payment status in our records.
        $paymentIntentId = data_get($charge, 'payment_intent');

        if ($paymentIntentId) {
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($payment) {
                $payment->update(['status' => 'refunded']);

                Log::info('Payment refunded (premium kept)', [
                    'user_id'            => $payment->user_id,
                    'stripe_payment_id'  => $paymentIntentId,
                    'amount_refunded'    => data_get($charge, 'amount_refunded', 0) / 100,
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Premium activation (single source of truth)
    // -------------------------------------------------------------------------

    private function activatePremium(mixed $paymentIntent): void
    {
        $userId    = data_get($paymentIntent, 'metadata.user_id');
        $paymentId = data_get($paymentIntent, 'id');

        if (! $userId) {
            Log::warning('activatePremium: missing user_id in metadata', ['payment_intent_id' => $paymentId]);
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            Log::warning('activatePremium: user not found', ['user_id' => $userId, 'payment_intent_id' => $paymentId]);
            return;
        }

        $user->forceFill(['is_premium' => true])->save();

        Payment::updateOrCreate(
            ['stripe_payment_intent_id' => $paymentId],
            [
                'user_id'  => $user->id,
                'amount'   => data_get($paymentIntent, 'amount', 0) / 100,
                'currency' => data_get($paymentIntent, 'currency'),
                'status'   => data_get($paymentIntent, 'status', 'succeeded'),
                'paid_at'  => now(),
            ]
        );
    }
}