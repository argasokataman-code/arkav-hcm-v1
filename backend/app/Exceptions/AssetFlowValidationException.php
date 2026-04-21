<?php

namespace App\Exceptions;

use Exception;

class AssetFlowValidationException extends Exception
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ], $this->statusCode);
    }
}
