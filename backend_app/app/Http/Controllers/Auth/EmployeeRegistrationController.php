<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterEmployeeRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Models\Workspace;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeRegistrationController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(RegisterEmployeeRequest $request): JsonResource
    {
        $validated = $request->validated();

        $workspace = Workspace::query()
            ->whereJoinCode($validated['join_code'])
            ->where('active', true)
            ->first();

        if ($workspace === null) {
            throw ValidationException::withMessages([
                'join_code' => ['The join code is invalid or inactive.'],
            ]);
        }

        /**
         * @var User $user
         */
        $user = DB::transaction(fn() => $workspace->users()->create(
            Arr::except($validated, ['join_code'])
        ));

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }
}
