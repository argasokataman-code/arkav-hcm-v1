<?php

namespace App\Services\Reconciliation\Exceptions;

use RuntimeException;

class ExportReconciliationException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = 422
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}
