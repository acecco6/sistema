<?php

namespace App\Http\Controllers\Clubs;

use App\Http\Controllers\Controller;
use App\Application\Clubs\Get\GetClubsHandler;
use Illuminate\Http\JsonResponse;

class GetClubController extends Controller
{
    public function __invoke(GetClubsHandler $handler): JsonResponse
    {
        $clubs = $handler->handle();

        return $this->successResponse(
            data: $clubs,
            message: 'Listado de clubes obtenido exitosamente',
            code: 200
        );
    }
}
