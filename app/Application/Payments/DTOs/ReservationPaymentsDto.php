<?php

namespace App\Application\Payments\DTOs;

final readonly class ReservationPaymentsDto
{
    /**
     * @param PaymentDto[] $payments
     * @param PaymentRefundDto[] $paymentRefunds
     */
    public function __construct(
        public array $payments,
        public array $paymentRefunds,
        public ReservationPaymentSummary $paymentSummary,
    ) {}

    public function toArray(): array
    {
        return [
            'payments' => array_map(
                static fn(PaymentDto $payment): array => $payment->toArray(),
                $this->payments,
            ),
            'payment_refunds' => array_map(
                static fn(PaymentRefundDto $paymentRefund): array => $paymentRefund->toArray(),
                $this->paymentRefunds,
            ),
            'payment_summary' => $this->paymentSummary->toArray(),
        ];
    }
}

// {
//     "payments": [
//         {
//             "id": 1,
//             "reservation_id": 15,
//             "amount": "20000.00",
//             "method": "MERCADO_PAGO",
//             "status": "APPROVED",
//             "provider": "mercadopago",
//             "provider_payment_id": "175489453279",
//             "external_reference": "PAY-...",
//             "created_by_user_id": null,
//             "paid_at": "2026-09-01 14:30:00"
//         },
//         {
//             "id": 2,
//             "reservation_id": 15,
//             "amount": "20000.00",
//             "method": "CASH",
//             "status": "APPROVED",
//             "provider": null,
//             "provider_payment_id": null,
//             "external_reference": "MANUAL-...",
//             "created_by_user_id": 4,
//             "paid_at": "2026-09-01 15:20:00"
//         }
//     ],
//     "payment_summary": {
//         "total_price": "40000.00",
//         "approved_amount": "40000.00",
//         "required_deposit": "20000.00",
//         "remaining_amount": "0.00",
//         "financial_status": "paid"
//     }
// }
