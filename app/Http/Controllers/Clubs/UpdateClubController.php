<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Update\UpdateCommand;
use App\Application\Clubs\Update\UpdateHandler;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateClubController extends Controller
{

    public function __invoke($id, Request $request, UpdateHandler $handler): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'active' => 'nullable|boolean'
        ]);

        try {
            if (!is_numeric($id)) {
                throw new ClubNotFoundException();
            }

            $command = new UpdateCommand(
                id: $id,
                name: $request->name,
                active: $request->boolean('active')
            );

            $club = $handler->handle($command);
            return $this->successResponse(
                data: $club,
                message: 'Club actualizado exitosamente',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al actualizar el club',
                code: 400
            );
        }
    }
}
