<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Collection\GetBranchReservationsHandler;
use App\Application\Reservations\Collection\GetBranchReservationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GetCourtReservationsRequest;
use Illuminate\Http\JsonResponse;

final class GetBranchReservationsController extends Controller
{
    public function __invoke(int $branch_id, GetCourtReservationsRequest $request, GetBranchReservationsHandler $handler): JsonResponse
    {
        $validated = $request->validated();

        $result = $handler->handle(new GetBranchReservationsQuery($branch_id, $validated['date']));
        return $this->successResponse($result);
    }
}
