<?php

namespace App\Services\IsoOverlay;

/**
 * A rejected overlay operation. getErrors() lists every problem found, so a
 * caller can show all of them at once instead of one at a time.
 */
class OverlayException extends \RuntimeException
{
    private array $errors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors ?: [$message];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
