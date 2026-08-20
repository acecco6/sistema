<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


final class ProfileController extends Controller
{

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Lo ideal en DDD es no devolver el modelo de Eloquent crudo.
        // Aquí podrías usar un Resource o transformarlo a un array específico.
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->active,
        ];

        return $this->successResponse(
            data: $userData,
            message: 'Perfil de usuario obtenido correctamente',
            code: 200
        );
    }
}
