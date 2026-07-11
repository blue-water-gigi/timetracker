<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Auth;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'User successfully registered.',
            'user' => $user,
        ], 201);
    }
}
