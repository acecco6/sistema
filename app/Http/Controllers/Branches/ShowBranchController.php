<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Show\ShowCommand;
use App\Application\Branches\Show\ShowHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class ShowBranchController extends Controller
{
    public function __invoke(int $id, ShowHandler $handler): JsonResponse
    {
        try {
            $branch = $handler->handle(new ShowCommand($id));

            return $this->successResponse(
                data: $branch,
                message: 'Sucursal obtenida exitosamente',
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
