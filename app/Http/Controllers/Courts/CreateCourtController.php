<?php

namespace App\Http\Controllers\Courts;

use App\Application\Courts\Store\StoreCommand;
use App\Application\Courts\Store\StoreHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courts\CreateCourtRequest;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class CreateCourtController extends Controller
{
    public function __invoke(int $branchId, CreateCourtRequest $request, StoreHandler $handler): JsonResponse
    {
        try {
            $command = new StoreCommand(
                branchId:    $branchId,
                tipoCourtId: $request->integer('tipo_court_id'),
                name:        $request->string('name'),
            );

            $court = $handler->handle($command);

            return $this->successResponse(
                data: $court,
                message: 'Cancha creada exitosamente',
                code: 201
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        }
    }
}
