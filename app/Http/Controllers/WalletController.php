<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'name', 'email')->where('id', '!=', $request->user()->id)->get();

        return response()->json([
            'users' => $users,
            'message' => 'Users fetched successfully',
        ]);
    }


public function getBalance(Request $request)
    {
        return response()->json([
            'balance' => $request->user()->balance,
            'message' => 'Balance fetched successfully'
        ]);
    }

public function sendMoney(Request $request)
    {
        $request->validate([
            'receiver_email' => 'required|email|exists:users,email',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $sender = $request->user();
        $receiver = User::where('email', $request->receiver_email)->first();

        if ($sender->id === $receiver->id) {
            return response()->json([
                'message' => 'You cannot send money to yourself',
            ], 400);
        }

        if ($sender->balance < $request->amount) {
            return response()->json([
                'message' => 'Not enough funds',
            ], 400);
        }

        DB::transaction(function () use ($sender, $receiver, $request) {

            // deduct sender
            $sender->balance -= $request->amount;
            $sender->save();

            // add receiver
            $receiver->balance += $request->amount;
            $receiver->save();

            // log transaction
            Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'type' => 'send'
            ]);
        });

        return response()->json([
            'message' => 'Money sent successfully'
        ]);
    }
}
