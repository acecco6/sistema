<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Get\GetBranchesHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class GetBranchController extends Controller
{
    public function __invoke(int $clubId, GetBranchesHandler $handler): JsonResponse
    {
        try {
            $branches = $handler->handle($clubId);

            return $this->successResponse(
                data: $branches,
                message: 'Listado de sucursales obtenido exitosamente',
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
