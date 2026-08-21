<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Show\ShowCommand;
use App\Application\Clubs\Show\ShowHandler;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;


class ShowClubController extends Controller
{

    public function __invoke($id, ShowHandler $handler): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                throw new ClubNotFoundException();
            }

            $command = new ShowCommand($id);
            $club = $handler->handle($command);
            return $this->successResponse(
                data: $club,
                message: 'Club obtenido exitosamente',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al obtener el club',
                code: 400
            );
        }
    }
}
