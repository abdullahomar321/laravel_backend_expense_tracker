<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    // LIST ALL EXPENSES FOR AUTHENTICATED USER
    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully',
            'data' => $expenses
        ]);
    }

    // CREATE NEW EXPENSE
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01'
        ]);

        $expense = Expense::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'amount' => $request->amount
        ]);

        // Log to expense history — persists even after expense is deleted
        ExpenseLog::create([
            'user_id' => $request->user()->id,
            'name'    => $request->name,
            'amount'  => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense
        ], 201);
    }

    // UPDATE EXPENSE
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01'
        ]);

        $expense = Expense::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $expense->update($request->only(['name', 'amount']));

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense
        ]);
    }

    // DELETE EXPENSE
    public function destroy(Request $request, $id): JsonResponse
    {
        $expense = Expense::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    }
}
