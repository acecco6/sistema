<?php

namespace App\Http\Controllers\Customers;

use App\Application\Customers\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCustomerController extends Controller
{
    public function __invoke(int $club_id, int $id, UpdateCustomerRequest $request, CustomerService $service): JsonResponse
    {
        return $this->successResponse($service->update($club_id, $id, $request->validated()), 'Cliente actualizado correctamente.');
    }
}
