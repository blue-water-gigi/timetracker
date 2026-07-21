<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Resources\User\UserResource;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(LoginUserRequest $request): JsonResource|JsonResponse
    {
        $validated = $request->validated();

        if (! Auth::attempt($validated)) {
            return response()->json([
                'data' => [
                    'error' => 'Theres no user with such credentials.',
                ],
            ], 422);
        }

        $request->session()->regenerate();

        return new UserResource(Auth::user());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }
}
