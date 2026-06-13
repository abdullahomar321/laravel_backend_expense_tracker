<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HangoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'emails'  => 'required|array|min:1',
            'emails.*' => 'required|email|exists:users,email',
        ]);

        $creator = $request->user();

        $hangout = Hangout::create([
            'name'       => $request->name,
            'creator_id' => $creator->id,
        ]);

        // Resolve member IDs from provided emails
        $memberIds = User::whereIn('email', $request->emails)
            ->pluck('id')
            ->toArray();

        // Always include creator as a member
        if (! in_array($creator->id, $memberIds)) {
            $memberIds[] = $creator->id;
        }

        $hangout->members()->attach($memberIds);

        $hangout->load([
            'creator:id,name,email',
            'members:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hangout created successfully',
            'data'    => $hangout,
        ], 201);
    }
}
