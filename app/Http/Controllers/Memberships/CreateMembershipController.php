<?php

namespace App\Http\Controllers\Memberships;

use App\Application\Memberships\Create\CreateMembershipCommand;
use App\Application\Memberships\Create\CreateMembershipHandler;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Memberships\CreateMembershipRequest;

final class CreateMembershipController extends Controller
{

    public function __invoke(CreateMembershipRequest $request, CreateMembershipHandler $handler): JsonResponse
    {
        try {
            $command = new CreateMembershipCommand(
                userId: $request->integer('user_id'),
                clubId: $request->integer('club_id'),
                roleId: $request->integer('rol_id'),
                branchId: $request->integer('branch_id') ?: null
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
