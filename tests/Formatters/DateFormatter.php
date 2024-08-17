<?php

namespace Yajra\DataTables\Tests\Formatters;

use Carbon\Carbon;
use DateTime;
use Yajra\DataTables\Contracts\Formatter;

class DateFormatter implements Formatter
{
    public function __construct(public string $format = 'Y-m-d h:i a') {}

    public function format($value, $row): string
    {
        if ($value instanceof DateTime) {
            return $value->format($this->format);
        }

        return $value ? Carbon::parse($value)->format($this->format) : '';
    }
}
