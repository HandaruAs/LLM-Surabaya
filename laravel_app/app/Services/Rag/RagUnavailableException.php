<?php

namespace App\Services\Rag;

use RuntimeException;

class RagUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Layanan AI tidak tersedia.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
