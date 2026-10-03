<?php

namespace App\Exceptions;

use Exception;

class ApiError extends Exception
{
    public string $apiCode;

    public ?array $apiErrors;

    public function __construct(
        int $status,
        string $code,
        string $message,
        ?array $errors = null,
    ) {
        parent::__construct($message, $status);
        $this->apiCode = $code;
        $this->apiErrors = $errors;
    }

    public static function make(int $status, string $code, string $message, ?array $errors = null): self
    {
        return new self($status, $code, $message, $errors);
    }
}
