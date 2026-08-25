<?php

namespace App\Http\Controllers\Auth;


use App\Application\Auth\Register\RegisterCommand;
use App\Application\Auth\Register\RegisterHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


final class RegisterController extends Controller
{

    public function __invoke(Request $request, RegisterHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'confirm_password' => 'required|string|min:8|same:password',
        ]);

        try {
            $command = new RegisterCommand(
                $validated['name'],
                $validated['email'],
                $validated['password']
            );

            $handler->handle($command);

            return $this->successResponse(
                message: 'Usuario registrado exitosamente',
                code: 201
            );
        } catch (\RuntimeException $e) {
            dd($e);
            return $this->errorResponse(
                message: 'Ocurrió un error al registrar el usuario',
                code: 400
            );
        }
    }
}
