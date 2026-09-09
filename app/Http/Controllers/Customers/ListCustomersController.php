<?php

namespace App\Http\Controllers\Customers;

use App\Application\Customers\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerIndexRequest;
use Illuminate\Http\JsonResponse;

final class ListCustomersController extends Controller
{
    public function __invoke(int $club_id, CustomerIndexRequest $request, CustomerService $service): JsonResponse
    {
        $v = $request->validated();
        $filters = array_filter(['search' => $v['search'] ?? null], fn($x) => $x !== null && $x !== '');
        if (array_key_exists('active', $v)) $filters['active'] = $request->boolean('active');
        return $this->successResponse($service->index($club_id, $filters, (int)($v['page'] ?? 1), (int)($v['per_page'] ?? 20)), 'Clientes obtenidos correctamente.');
    }
}
