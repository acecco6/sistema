<?php

namespace App\Http\Controllers\Memberships;

use App\Application\Memberships\Create\CreateMembershipCommand;
use App\Application\Memberships\Create\CreateMembershipHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateMembershipController extends Controller
{

    public function __invoke(Request $request, CreateMembershipHandler $handler): JsonResponse
    {


        $validated = $request->validate([
            'user_id' => 'required|integer',
            'club_id' => 'required|integer',
            'role_id' => 'required|integer',
            'branch_id' => 'nullable|integer',
        ]);

        try {
            $command = new CreateMembershipCommand(
                userId: $validated['user_id'],
                clubId: $validated['club_id'],
                roleId: $validated['role_id'],
                branchId: $validated['branch_id'] ?? null
            );

            $handler->handle($command);

            return $this->successResponse(
                message: 'Membresía creada exitosamente.',
                code: 201
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: $e->getCode()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                message: 'Ocurrió un error al crear la membresía.',
                code: 500
            );
        }
    }
}
