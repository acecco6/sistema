<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\SearchUsersHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UserSearchRequest;
use Illuminate\Http\JsonResponse;

final class SearchUsersController extends Controller
{
    public function __invoke(int $club_id, UserSearchRequest $request, SearchUsersHandler $handler): JsonResponse
    {
        $data = $request->validated();

        return $this->successResponse($handler->handle(
            (int) $request->user()->id,
            (int) $data['club_id'],
            isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            trim((string) ($data['search'] ?? '')),
            array_key_exists('active', $data) ? $request->boolean('active') : true,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
        ), 'Usuarios obtenidos correctamente.');
    }
}
