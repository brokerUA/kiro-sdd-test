<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Controllers;

use Application\Auth\DTOs\LogoutCommand;
use Application\Auth\UseCases\LogoutUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LogoutController extends Controller
{
    public function __construct(
        private readonly LogoutUseCase $logoutUseCase,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        $rawToken  = substr($authHeader, 7);
        $tokenHash = hash('sha256', $rawToken);

        $this->logoutUseCase->execute(new LogoutCommand($tokenHash));

        return response()->json([]);
    }
}
