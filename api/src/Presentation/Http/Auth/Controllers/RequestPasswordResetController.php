<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Controllers;

use Application\Auth\DTOs\RequestPasswordResetCommand;
use Application\Auth\UseCases\RequestPasswordResetUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Presentation\Http\Auth\Requests\PasswordResetRequestForm;

class RequestPasswordResetController extends Controller
{
    public function __construct(
        private readonly RequestPasswordResetUseCase $requestResetUseCase,
    ) {}

    public function __invoke(PasswordResetRequestForm $request): JsonResponse
    {
        $this->requestResetUseCase->execute(
            new RequestPasswordResetCommand($request->email)
        );

        return response()->json([]);
    }
}
