<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Get\GetBranchesHandler;
use App\Application\Branches\Get\GetBranchesQuery;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetBranchController extends Controller
{
    public function __invoke(GetBranchesHandler $handler, int $clubId, Request $request): JsonResponse
    {
        try {
            $branches = $handler->handle(new GetBranchesQuery(userId: $request->user()->id, clubId: $clubId));
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
