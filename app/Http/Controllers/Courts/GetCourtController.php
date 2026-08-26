<?php

namespace App\Http\Controllers\Courts;

use App\Application\Courts\Get\GetCommand;
use App\Application\Courts\Get\GetHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class GetCourtController extends Controller
{
    public function __invoke(int $branchId, GetHandler $handler): JsonResponse
    {
        try {
            $courts = $handler->handle(new GetCommand($branchId));

            return $this->successResponse(
                data: $courts,
                message: 'Listado de canchas obtenido exitosamente',
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
