<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Show\ShowCommand;
use App\Application\Clubs\Show\ShowHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowClubController extends Controller
{

    public function __invoke($id, ShowHandler $handler): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                throw new NotFoundHttpException('Club no encontrado');
            }

            $command = new ShowCommand($id);
            $club = $handler->handle($command);
            return $this->successResponse(
                data: $club,
                message: 'Club obtenido exitosamente',
                code: 200
            );
        } catch (NotFoundHttpException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 404
            );
        }
    }
}
