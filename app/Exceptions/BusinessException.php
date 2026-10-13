<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class BusinessException extends Exception implements ShouldntReport
{
    public function __construct(
        string $message = '',
        public int $businessCode = 0,
        public int $status = 200,
    ) {
        parent::__construct($message, $businessCode);
    }
}
