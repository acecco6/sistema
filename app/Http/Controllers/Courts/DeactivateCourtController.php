<?php

namespace App\Http\Controllers\Courts;

use App\Application\Courts\Deactivate\DeactivateCommand;
use App\Application\Courts\Deactivate\DeactivateHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class DeactivateCourtController extends Controller
{
    public function __invoke(int $id, DeactivateHandler $handler): JsonResponse
    {
        try {
            $handler->handle(new DeactivateCommand($id));

            return $this->successResponse(
                data: null,
                message: 'Cancha eliminada exitosamente',
                code: 204
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        }
    }
}
