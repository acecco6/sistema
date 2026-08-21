<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Store\StoreCommand;
use App\Application\Clubs\Store\StoreHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateClubController extends Controller
{

    public function __invoke(Request $request, StoreHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100'
        ]);

        try {
            $command = new StoreCommand(
                $validated['name']
            );

            $handler->handle($command);

            return $this->successResponse(
                message: 'Club creado exitosamente',
                code: 201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al crear el club',
                code: 400
            );
        }
    }
}
