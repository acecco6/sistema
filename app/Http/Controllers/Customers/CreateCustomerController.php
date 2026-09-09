<?php

namespace App\Http\Controllers\Customers;

use App\Application\Customers\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CreateCustomerRequest;
use Illuminate\Http\JsonResponse;

final class CreateCustomerController extends Controller
{
    public function __invoke(int $club_id, CreateCustomerRequest $request, CustomerService $service): JsonResponse
    {
        return $this->successResponse($service->create($club_id, $request->validated()), 'Cliente agregado al club correctamente.', 201);
    }
}
