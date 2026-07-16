<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Captures every row of an uploaded CSV/XLSX as an associative collection
 * keyed by its (slugified) column heading — reused for every importable
 * module rather than writing one Import class per module.
 */
class GenericHeadingRowImport implements ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
