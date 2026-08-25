<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Get\GetClubsHandler;
use App\Application\Clubs\Get\GetClubsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetClubController extends Controller
{
    public function __invoke(GetClubsHandler $handler, Request $request): JsonResponse
    {
        $query = new GetClubsQuery(
            userId: $request->user()->id,
        );

        $clubs = $handler->handle($query);

        return $this->successResponse(
            data: $clubs,
            message: 'Listado de clubes obtenido exitosamente',
            code: 200
        );
    }
}
