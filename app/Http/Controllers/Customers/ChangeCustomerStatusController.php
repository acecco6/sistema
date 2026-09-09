<?php

namespace App\Http\Controllers\Customers;
use App\Application\Customers\CustomerService; use App\Http\Controllers\Controller; use App\Http\Requests\Customers\ChangeCustomerStatusRequest; use Illuminate\Http\JsonResponse;
final class ChangeCustomerStatusController extends Controller { public function __invoke(int $club_id, int $id, ChangeCustomerStatusRequest $request, CustomerService $service): JsonResponse { return $this->successResponse($service->status($club_id, $id, $request->boolean('active')), 'Estado del cliente actualizado.'); } }
