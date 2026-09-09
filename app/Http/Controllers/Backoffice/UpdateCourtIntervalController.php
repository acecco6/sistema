<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\CourtIntervalHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UpdateCourtIntervalRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCourtIntervalController extends Controller
{
    public function __invoke(int $branch_id, int $court_type_id, UpdateCourtIntervalRequest $request, CourtIntervalHandler $handler): JsonResponse
    {
        return $this->successResponse(
            $handler->update($branch_id, $court_type_id, (int) $request->validated('interval_minutes')),
            'Intervalo actualizado correctamente.',
        );
    }
}
