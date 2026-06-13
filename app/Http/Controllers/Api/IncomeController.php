<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function store(Request $request){
        $request->validate(
            [
                'amount'=>'required|numeric|min:1',
                'description'=>'nullable|string|max:225',

            ]
        );

        $user=$request->user();

        $user->balance=$request->amount;
        $user->save();

        return response()->json([
            'message'=>'income successfully added',
            'new_balance'=>$user->balance
        ]);
    }

     public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'balance' => $user->balance,
            'message' => 'Current balance fetched successfully'
        ]);
    }
}
