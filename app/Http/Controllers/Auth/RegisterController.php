<?php

namespace App\Http\Controllers\Auth;


use App\Application\Auth\Register\RegisterCommand;
use App\Application\Auth\Register\RegisterHandler;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;


final class RegisterController extends Controller
{

    public function __invoke(RegisterRequest $request, RegisterHandler $handler): JsonResponse
    {
        $validated = $request->validated();

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
    }
}
