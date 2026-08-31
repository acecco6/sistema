<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookCommand;
use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookHandler;
use App\Application\Payments\Webhooks\WebhookSignatureValidator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookSignatureValidator $signatureValidator,
        ProcessMercadoPagoWebhookHandler $handler,
    ): JsonResponse {
        /*
         * PHP transforma ?data.id=... en data_id.
         */
        $dataId = $request->query('data_id')
            ?? $request->input('data.id');

        $type = $request->query('type')
            ?? $request->input('type');

        $signature = $request->header('x-signature');
        $requestId = $request->header('x-request-id');

        if (
            ! is_string($signature) ||
            ! is_string($requestId) ||
            ! is_string($dataId) ||
            $signature === '' ||
            $requestId === '' ||
            $dataId === ''
        ) {
            return $this->errorResponse(
                'Webhook inválido.',
                401
            );
        }

        /*
         * Verificamos que la notificación realmente
         * haya sido enviada por Mercado Pago.
         */
        if (
            ! $signatureValidator->validate(
                signature: $signature,
                requestId: $requestId,
                dataId: $dataId,
            )
        ) {
            return $this->errorResponse(
                'Firma del webhook inválida.',
                401
            );
        }

        /*
         * Solo procesamos eventos de pagos.
         */
        if ($type !== 'payment') {
            return $this->successResponse(
                message: 'Evento ignorado.',
                code: 200,
            );
        }

        try {
            $handler->handle(
                new ProcessMercadoPagoWebhookCommand(
                    providerPaymentId: $dataId,
                )
            );
        } catch (Throwable $th) {
            return $this->errorResponse(
                message: 'Error al procesar el webhook.',
                code: 500,
            );
        }

        return $this->successResponse(
            message: 'Webhook procesado correctamente.',
            code: 200,
        );
    }
}
