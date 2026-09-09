<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\ResetPassword\ResetPasswordCommand;
use App\Application\Auth\ResetPassword\ResetPasswordHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordHandler $handler): JsonResponse
    {
        $validated = $request->validated();
        $reset = $handler->handle(new ResetPasswordCommand(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password'],
        ));

        if (! $reset) {
            return $this->errorResponse('El enlace de recuperación no es válido o expiró.', 422);
        }

        return $this->successResponse(
            message: 'Contraseña actualizada. Iniciá sesión nuevamente.',
        );
    }
}
