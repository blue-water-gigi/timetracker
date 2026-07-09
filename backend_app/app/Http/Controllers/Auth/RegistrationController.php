<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RegistrationController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'message' => 'User successfully registered',
            'data' => $request->validated(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(User $user): void
    {
        $user->deleteOrFail();
    }
}
