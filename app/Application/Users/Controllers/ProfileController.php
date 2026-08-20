<?php

namespace App\Application\Users\Controllers;

use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ProfileController extends Controller
{
    use ApiResponse;

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
