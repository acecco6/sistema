<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\GetReservationPayments\GetReservationPaymentsHandler;
use App\Application\Payments\GetReservationPayments\GetReservationPaymentsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetReservationPaymentsController extends Controller
{


    public function __invoke(int $id, GetReservationPaymentsHandler $handler): JsonResponse
    {

        $result = $handler(new GetReservationPaymentsQuery($id));

        return $this->successResponse(
            message: 'Pagos de la reserva obtenidos correctamente.',
            data: $result->toArray(),
        );
    }
}
