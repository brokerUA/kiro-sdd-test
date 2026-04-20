<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Controllers;

use Application\Auth\DTOs\LoginCommand;
use Application\Auth\Exceptions\AccountLockedException;
use Application\Auth\Exceptions\AuthenticationFailedException;
use Application\Auth\UseCases\LoginUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Presentation\Http\Auth\Requests\LoginRequest;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $token = $this->loginUseCase->execute(
                new LoginCommand($request->email, $request->password)
            );

            return response()->json(['session_token' => $token->raw], 200);
        } catch (AccountLockedException $e) {
            return response()->json([
                'error'                => 'ACCOUNT_LOCKED',
                'retry_after_seconds'  => $e->getRetryAfterSeconds(),
            ], 423);
        } catch (AuthenticationFailedException) {
            return response()->json(['error' => 'AUTHENTICATION_FAILED'], 401);
        }
    }
}
