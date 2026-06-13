<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GeminiController extends Controller
{
    
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $apiKey = config('services.gemini.api_key');
        $model  = config('services.gemini.model', 'gemini-2.5-flash');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API key is not configured.',
            ], 500);
        }

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $request->message],
                        ],
                    ],
                ],
            ]
        ); 
        

        if (! $response->successful()) {
            Log::error('Gemini API error', [
        'status' => $response->status(),
        'body' => $response->json(),
    ]);
            return response()->json([
                'success' => false,
                'message' => 'Gemini request failed.',
                'error'   => $response->json(),
            ], $response->status());
        }

        $reply = data_get($response->json(), 'candidates.0.content.parts.0.text');

        return response()->json([
            'success' => true,
            'message' => 'Gemini response generated successfully.',
            'data'    => [
                'reply' => $reply,
                'model' => $model,
            ],
        ]);
    }
}
