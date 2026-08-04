<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAdminRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Resources\Json\JsonResource;
use Log;
use Throwable;

class AdminRegistrationController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(RegisterAdminRequest $request): JsonResource
    {
        try {
            /**
             * @var User $user
             */
            $user = DB::transaction(function () use ($request) {
                $user = User::make();

                $user->system_role = SystemRole::ADMINISTRATOR;
                $user->workspace_id = null;

                $user->forceFill($request->validated())
                    ->saveOrFail();

                return $user;
            });

            Auth::login($user);
            $request->session()->regenerate();

            return new UserResource($user);
        } catch (Throwable $th) {
            Log::error('Failed to create admin user', [
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);

            throw $th;
        }
    }
}
