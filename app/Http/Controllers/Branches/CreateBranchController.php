<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Store\StoreCommand;
use App\Application\Branches\Store\StoreHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateBranchController extends Controller
{
    public function __invoke(int $clubId, Request $request, StoreHandler $handler): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'opening_time' => 'nullable|date_format:H:i:s,H:i',
            'closing_time' => 'nullable|date_format:H:i:s,H:i',
        ]);

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
