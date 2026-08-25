<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Login\LoginCommand;
use App\Application\Auth\Login\LoginHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


final class LoginController extends Controller
{

    public function __invoke(Request $request, LoginHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string',
        ]);

        try {
            $command = new LoginCommand($validated['email'], $validated['password']);
            $token = $handler->handle($command);

            return $this->successResponse(
                data: [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
                message: 'Login exitoso',
                code: 200
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 401
            );
        }
    }
}
