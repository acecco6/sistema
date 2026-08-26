<?php

namespace App\Http\Controllers\Courts;

use App\Application\Courts\Show\ShowCommand;
use App\Application\Courts\Show\ShowHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class ShowCourtController extends Controller
{
    public function __invoke(int $id, ShowHandler $handler): JsonResponse
    {
        try {
            $court = $handler->handle(new ShowCommand($id));

            return $this->successResponse(
                data: $court,
                message: 'Cancha obtenida exitosamente',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        }
    }
}
