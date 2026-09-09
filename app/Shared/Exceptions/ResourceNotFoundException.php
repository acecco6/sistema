<?php

namespace App\Shared\Exceptions;

final class ResourceNotFoundException extends DomainException
{
    public function __construct(string $message = 'Recurso no encontrado.') { parent::__construct($message, 404); }
}
