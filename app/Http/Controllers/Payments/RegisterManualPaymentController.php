<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\RegisterManualPayment\RegisterManualPaymentCommand;
use App\Application\Payments\RegisterManualPayment\RegisterManualPaymentHandler;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RegisterManualPaymentRequest;
use Illuminate\Http\JsonResponse;

final class RegisterManualPaymentController extends Controller
{

    public function __invoke(int $id, RegisterManualPaymentRequest $request, RegisterManualPaymentHandler $handler,): JsonResponse
    {

        $summary = $handler(
            new RegisterManualPaymentCommand(
                reservationId: $id,
                amount: (string) $request->validated('amount'),
                method: PaymentMethod::from(
                    $request->validated('method')
                ),
                createdByUserId: $request->user()->id,
            )
        );

        return $this->successResponse(
            message: 'Pago registrado correctamente.',
            data: [
                'payment_summary' => $summary->toArray(),
            ],
            code: 201,
        );
    }
}
