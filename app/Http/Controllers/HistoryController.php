<?php

namespace App\Http\Controllers;

use App\Models\ExpenseLog;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * All history: money sent + expenses logged, merged and sorted by date.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sent = Transaction::with('receiver:id,name,email')
            ->where('sender_id', $userId)
            ->where('type', 'send')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'type'           => 'send',
                'name'           => 'Sent to ' . ($t->receiver->name ?? $t->receiver->email ?? 'Unknown'),
                'receiver_name'  => $t->receiver->name  ?? null,
                'receiver_email' => $t->receiver->email ?? null,
                'amount'         => $t->amount,
                'date'           => $t->created_at,
            ]);

        $expenses = ExpenseLog::where('user_id', $userId)
            ->get()
            ->map(fn ($e) => [
                'id'     => $e->id,
                'type'   => 'expense',
                'name'   => $e->name,
                'amount' => $e->amount,
                'date'   => $e->created_at,
            ]);

        $history = $sent->concat($expenses)
            ->sortByDesc('date')
            ->values();

        return response()->json([
            'history' => $history,
            'message' => 'History fetched successfully',
        ]);
    }

    /**
     * Money sent transactions only.
     */
    public function sent(Request $request): JsonResponse
    {
        $sent = Transaction::with('receiver:id,name,email')
            ->where('sender_id', $request->user()->id)
            ->where('type', 'send')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'type'           => 'send',
                'receiver_name'  => $t->receiver->name  ?? null,
                'receiver_email' => $t->receiver->email ?? null,
                'amount'         => $t->amount,
                'date'           => $t->created_at,
            ]);

        return response()->json([
            'transactions' => $sent,
            'message'      => 'Sent transactions fetched successfully',
        ]);
    }

    /**
     * Expense logs only — persists even after expense is deleted from expenses screen.
     * Auto-deleted after 3 days via scheduled job.
     */
    public function expenses(Request $request): JsonResponse
    {
        $expenses = ExpenseLog::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'expenses' => $expenses,
            'message'  => 'Expense history fetched successfully',
        ]);
    }
}
