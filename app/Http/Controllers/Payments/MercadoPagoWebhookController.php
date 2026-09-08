<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookCommand;
use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookHandler;
use App\Application\Payments\Webhooks\WebhookSignatureValidator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookSignatureValidator $signatureValidator,
        ProcessMercadoPagoWebhookHandler $handler,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Data ID para firma
        |--------------------------------------------------------------------------
        */

        $dataIdFromQuery =
            $request->query('data_id')
            ?? $request->query('data.id');

        $dataIdForSignature =
            $dataIdFromQuery !== null
            ? (string) $dataIdFromQuery
            : '';

        /*
        |--------------------------------------------------------------------------
        | ID real del Payment
        |--------------------------------------------------------------------------
        */

        $providerPaymentId =
            $request->query('data_id')
            ?? $request->query('data.id')
            ?? $request->input('data.id')
            ?? $request->input('id');

        /*
        |--------------------------------------------------------------------------
        | Seller
        |--------------------------------------------------------------------------
        |
        | Mercado Pago define user_id en este webhook como el identificador
        | del vendedor.
        |
        */

        $mercadoPagoUserId =
            $request->input('user_id');

        $signature =
            $request->header('x-signature');

        $requestId =
            $request->header('x-request-id');

        if (
            ! is_string($signature)
            || ! is_string($requestId)
            || $providerPaymentId === null
            || $mercadoPagoUserId === null
            || $signature === ''
            || $requestId === ''
            || $providerPaymentId === ''
        ) {
            return $this->errorResponse(
                'Webhook inválido.',
                401
            );
        }

        if (
            ! $signatureValidator->validate(
                signature: $signature,
                requestId: $requestId,
                dataId: $dataIdForSignature,
            )
        ) {
            return $this->errorResponse(
                'Firma del webhook inválida.',
                401
            );
        }

        $type = $request->input('type') ?? $request->query('type');

        if (
            $type !== null
            && $type !== 'payment'
        ) {
            return $this->successResponse(
                message: 'Evento ignorado.',
                code: 200
            );
        }

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: (string) $providerPaymentId,

                mercadoPagoUserId: (string) $mercadoPagoUserId,
            )
        );

        return $this->successResponse(
            message: 'Webhook recibido correctamente.',
            code: 200
        );
    }
}
