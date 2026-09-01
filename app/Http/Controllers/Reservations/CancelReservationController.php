<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Cancel\CancelReservationCommand;
use App\Application\Reservations\Cancel\CancelReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\CancelReservationRequest;
use Illuminate\Http\JsonResponse;

final class CancelReservationController extends Controller
{
    public function __invoke(int $id, CancelReservationRequest $request, CancelReservationHandler $handler,): JsonResponse
    {
        $result = $handler->handle(
            new CancelReservationCommand(
                id: $id,
                createRefund: $request->boolean('create_refund'),
                refundReason: $request->input('refund_reason'),
                cancelledByUserId: $request->user()->id,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva cancelada correctamente.',
        );
    }
}
