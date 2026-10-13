<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ArrayExport implements FromArray, WithHeadings, WithStrictNullComparison
{
    private array $rows = [];

    /**
     * @param array    $headings 表头
     * @param iterable $rows     数据行
     */
    public function __construct(private array $headings, iterable $rows)
    {
        foreach ($rows as $row) {
            if ($row instanceof Arrayable) {
                $row = $row->toArray();
            } elseif ($row instanceof \JsonSerializable) {
                $row = $row->jsonSerialize();
            } elseif (! is_array($row)) {
                $row = (array) $row;
            }

            $this->rows[] = array_values($row);
        }
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
