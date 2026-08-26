<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Store\StoreCommand;
use App\Application\Branches\Store\StoreHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Branches\CreateBranchRequest;

class CreateBranchController extends Controller
{
    public function __invoke(int $clubId, CreateBranchRequest $request, StoreHandler $handler): JsonResponse
    {
        try {
            $command = new StoreCommand(
                clubId: $clubId,
                name: $request->name,
                address: $request->address,
                openingTime: $request->opening_time,
                closingTime: $request->closing_time
            );

            $branch = $handler->handle($command);

            return $this->successResponse(
                data: $branch,
                message: 'Sucursal creada exitosamente',
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
