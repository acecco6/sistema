<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\ListMembershipsHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\MembershipIndexRequest;
use Illuminate\Http\JsonResponse;

final class ListMembershipsController extends Controller
{
    public function __invoke(int $club_id, MembershipIndexRequest $request, ListMembershipsHandler $handler): JsonResponse
    {
        $data = $request->validated();
        $filters = array_filter([
            'search' => $data['search'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'role_id' => $data['role_id'] ?? null,
        ], fn($value) => $value !== null && $value !== '');

        if (array_key_exists('active', $data)) {
            $filters['active'] = $request->boolean('active');
        }

        return $this->successResponse($handler->handle(
            (int) $request->user()->id,
            $club_id,
            $filters,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
        ), 'Membresías obtenidas correctamente.');
    }
}
