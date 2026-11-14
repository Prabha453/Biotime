<?php

namespace App\Libraries;

class ImportValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed. No records were saved.');
    }
}
