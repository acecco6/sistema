<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\ForgotPassword\ForgotPasswordCommand;
use App\Application\Auth\ForgotPassword\ForgotPasswordHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

final class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, ForgotPasswordHandler $handler): JsonResponse
    {
        $handler->handle(new ForgotPasswordCommand($request->validated('email')));

        return $this->successResponse(
            message: 'Si existe una cuenta con ese email, enviamos un enlace para recuperar la contraseña.',
        );
    }
}
