<?php

namespace App\Http\Controllers\Memberships;

use App\Application\Memberships\ChangeRole\ChangeRoleMembershipCommand;
use App\Application\Memberships\ChangeRole\ChangeRoleMembershipHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


final class ChangeMembershipRoleController extends Controller
{
    public function __invoke($id, Request $request, ChangeRoleMembershipHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'rol_id' => 'required|integer',
        ]);

        $roleId = $validated['rol_id'];

        try {
            $command = new ChangeRoleMembershipCommand($id, $roleId);
            $handler->handle($command);

            return $this->successResponse(
                message: 'El rol de la membresía ha sido actualizado exitosamente.',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al actualizar el rol de la membresía.',
                code: 500
            );
        }
    }
}
