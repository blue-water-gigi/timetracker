<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Models\User;
use Auth;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(LoginUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Theres no user with such credentials.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'User logged in successfully.',
            'user' => $request->user(),
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): void
    {
        Auth::logout();
    }
}
