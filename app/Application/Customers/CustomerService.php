<?php

namespace App\Application\Customers;

use App\Application\Customers\Contracts\CustomerRepository;
use App\Shared\Exceptions\ResourceNotFoundException;

final readonly class CustomerService
{
    public function __construct(private CustomerRepository $customers) {}

    public function index(int $clubId, array $filters, int $page, int $perPage): array
    { return $this->customers->paginateForClub($clubId, $filters, $page, $perPage); }

    public function show(int $clubId, int $id): array
    { return $this->customers->findClubCustomer($clubId, $id) ?? throw new ResourceNotFoundException('Cliente del club no encontrado.'); }

    public function create(int $clubId, array $data): array
    { return $this->customers->createOrAttach($clubId, $data); }

    public function update(int $clubId, int $id, array $data): array
    { return $this->customers->update($clubId, $id, $data) ?? throw new ResourceNotFoundException('Cliente del club no encontrado.'); }

    public function status(int $clubId, int $id, bool $active): array
    { return $this->customers->changeStatus($clubId, $id, $active) ?? throw new ResourceNotFoundException('Cliente del club no encontrado.'); }
}
