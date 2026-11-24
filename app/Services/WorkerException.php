<?php

namespace App\Services;

use RuntimeException;

class WorkerException extends RuntimeException
{
    public function __construct(public string $errorCode, string $message)
    {
        parent::__construct("[{$errorCode}] {$message}");
    }
}
