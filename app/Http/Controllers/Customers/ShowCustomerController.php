<?php

namespace App\Http\Controllers\Customers;
use App\Application\Customers\CustomerService; use App\Http\Controllers\Controller; use Illuminate\Http\JsonResponse;
final class ShowCustomerController extends Controller { public function __invoke(int $club_id, int $id, CustomerService $service): JsonResponse { return $this->successResponse($service->show($club_id, $id), 'Cliente obtenido correctamente.'); } }
