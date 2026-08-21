<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Delete\DeleteCommand;
use App\Application\Clubs\Delete\DeleteHandler;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteClubController  extends Controller
{

    public function __invoke($id, DeleteHandler $handler)
    {
        try {
            if (!is_numeric($id)) {
                throw new NotFoundHttpException('Club no encontrado');
            }

            $club = $handler->handle(new DeleteCommand($id));

            return $this->successResponse(
                data: null,
                message: 'Club eliminado exitosamente',
                code: 204
            );
        } catch (NotFoundHttpException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 404
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al eliminar el club',
                code: 400
            );
        }
    }
}
