<?php

namespace App\Http\Controllers\Memberships;

use App\Application\Memberships\ChangeBranche\ChangeMembershipBranchCommand;
use App\Application\Memberships\ChangeBranche\ChangeMembershipBranchHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Memberships\ChangeMembershipBranchRequest;

final class ChangeMembershipBranchController extends Controller
{
    public function __invoke($id, ChangeMembershipBranchRequest $request, ChangeMembershipBranchHandler $handler): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;

        try {
            $command = new ChangeMembershipBranchCommand($id, $branchId);
            $handler->handle($command);

            return $this->successResponse(
                message: 'La sucursal de la membresía ha sido actualizada exitosamente.',
                code: 200
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al actualizar la sucursal de la membresía.',
                code: 500
            );
        }
    }
}
