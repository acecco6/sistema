<?php

namespace App\Http\Controllers\Memberships;

use App\Application\Memberships\ChangeStatus\ChangeStatusMembershipCommand;
use App\Application\Memberships\ChangeStatus\ChangeStatusMembershipHandler;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

final class ChangeMembershipStatusController extends Controller
{
    public function __invoke($id, ChangeStatusMembershipHandler $handler): JsonResponse
    {

        try {
            if ($id === null || !is_numeric($id)) {
                throw new MembershipNotFoundException();
            }

            $command = new ChangeStatusMembershipCommand($id);
            $handler->handle($command);

            return $this->successResponse(
                message: 'El estado de la membresía ha sido actualizado exitosamente.',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al actualizar el estado de la membresía.',
                code: 500
            );
        }
    }
}
