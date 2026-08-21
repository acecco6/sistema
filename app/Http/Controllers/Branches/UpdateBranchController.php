<?php

namespace App\Http\Controllers\Branches;

use App\Application\Branches\Update\UpdateCommand;
use App\Application\Branches\Update\UpdateHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateBranchController extends Controller
{
    public function __invoke(int $id, Request $request, UpdateHandler $handler): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'opening_time' => 'nullable|date_format:H:i:s,H:i',
            'closing_time' => 'nullable|date_format:H:i:s,H:i',
            'active' => 'nullable|boolean'
        ]);

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
