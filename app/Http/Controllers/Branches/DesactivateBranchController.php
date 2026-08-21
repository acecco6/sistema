<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Desactivate\DesactivateCommand;
use App\Application\Branches\Desactivate\DesactivateHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class DesactivateBranchController extends Controller
{
    public function __invoke(int $id, DesactivateHandler $handler): JsonResponse
    {
        try {
            $handler->handle(new DesactivateCommand($id));

            return $this->successResponse(
                data: null,
                message: 'Sucursal eliminada exitosamente',
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
