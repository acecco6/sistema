<?php

namespace App\Http\Controllers\Clubs;

use App\Application\Clubs\Desactivate\DesactivateCommand;
use App\Application\Clubs\Desactivate\DesactivateHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DesactivateClubController  extends Controller
{

    public function __invoke($id, DesactivateHandler $handler)
    {
        try {
            if (!is_numeric($id)) {
                throw new NotFoundHttpException('Club no encontrado');
            }

            $club = $handler->handle(new DesactivateCommand($id));

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
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al eliminar el club',
                code: 400
            );
        }
    }
}
