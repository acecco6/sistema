<?php

namespace App\Http\Controllers\Notifications;

use App\Application\Notifications\GetNotificationChannels;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetNotificationChannelsController extends Controller
{
    public function __construct(
        private readonly GetNotificationChannels $useCase
    ) {}

    public function __invoke(Request $request, int $clubId): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $result = $this->useCase->handle($userId, $clubId);

        return $this->successResponse($result, 'Canales de notificación obtenidos exitosamente.');
    }
}
