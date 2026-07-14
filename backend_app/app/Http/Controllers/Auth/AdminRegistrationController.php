<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAdminRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Log;
use Session;
use Throwable;

class AdminRegistrationController extends Controller
{

    /**
     * @throws Throwable
     */
    public function __invoke(RegisterAdminRequest $request): JsonResource
    {
        $validated = $request->validated();

        try {
            /**
             * @var User $user
             */
            $user = DB::transaction(function () use ($validated) {
                $user = User::create($validated);

                $user->system_role = SystemRole::ADMINISTRATOR;
                $user->workspace_id = null;

                $user->saveOrFail();

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
