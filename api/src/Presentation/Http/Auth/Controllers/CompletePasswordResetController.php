<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Controllers;

use Application\Auth\DTOs\CompletePasswordResetCommand;
use Application\Auth\Exceptions\TokenExpiredException;
use Application\Auth\Exceptions\TokenInvalidException;
use Application\Auth\UseCases\CompletePasswordResetUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Presentation\Http\Auth\Requests\PasswordResetCompleteForm;

class CompletePasswordResetController extends Controller
{
    public function __construct(
        private readonly CompletePasswordResetUseCase $completeResetUseCase,
    ) {}

    public function __invoke(PasswordResetCompleteForm $request): JsonResponse
    {
        try {
            $this->completeResetUseCase->execute(
                new CompletePasswordResetCommand($request->token, $request->new_password)
            );

            return response()->json([]);
        } catch (TokenExpiredException) {
            return response()->json(['error' => 'TOKEN_EXPIRED'], 400);
        } catch (TokenInvalidException) {
            return response()->json(['error' => 'TOKEN_INVALID'], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'error'   => 'VALIDATION_ERROR',
                'message' => implode(' ', array_merge(...array_values($e->errors()))),
            ], 400);
        }
    }
}
