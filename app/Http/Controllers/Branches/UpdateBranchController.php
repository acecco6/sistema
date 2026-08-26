<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Update\UpdateCommand;
use App\Application\Branches\Update\UpdateHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Branches\UpdateBranchRequest;

class UpdateBranchController extends Controller
{
    public function __invoke(int $id, UpdateBranchRequest $request, UpdateHandler $handler): JsonResponse
    {
        try {
            $command = new UpdateCommand(
                id: $id,
                name: $request->name,
                address: $request->address,
                openingTime: $request->opening_time,
                closingTime: $request->closing_time,
                active: $request->boolean('active')
            );

            $branch = $handler->handle($command);

            return $this->successResponse(
                data: $branch,
                message: 'Sucursal actualizada exitosamente',
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
