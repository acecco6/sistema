<?php

namespace App\Http\Controllers\Courts;

use App\Application\Courts\Update\UpdateCommand;
use App\Application\Courts\Update\UpdateHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courts\UpdateCourtRequest;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class UpdateCourtController extends Controller
{
    public function __invoke(int $id, UpdateCourtRequest $request, UpdateHandler $handler): JsonResponse
    {
        try {
            $command = new UpdateCommand(
                id:          $id,
                tipoCourtId: $request->integer('tipo_court_id'),
                name:        $request->string('name'),
            );

            $court = $handler->handle($command);

            return $this->successResponse(
                data: $court,
                message: 'Cancha actualizada exitosamente',
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
