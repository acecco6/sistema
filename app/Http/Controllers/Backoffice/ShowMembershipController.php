<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Memberships\Show\ShowMembershipCommand;
use App\Application\Memberships\Show\ShowMembershipHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowMembershipController extends Controller
{
    public function __invoke(int $id, Request $request, ShowMembershipHandler $handler): JsonResponse
    {
        return $this->successResponse(
            $handler->handle(new ShowMembershipCommand($id, (int) $request->user()->id)),
            'Membresía obtenida correctamente.',
        );
    }
}
