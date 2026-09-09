<?php

namespace App\Application\Customers;

use App\Application\Customers\Contracts\CustomerRepository;

final readonly class CustomerAccountLinker
{
    public function __construct(private CustomerRepository $customers) {}

    /** Vincula un Customer sin cuenta por email normalizado, si existe. */
    public function linkVerifiedUser(int $userId): void
    {
        $this->customers->linkVerifiedUser($userId);
    }
}
