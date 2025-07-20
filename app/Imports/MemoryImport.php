<?php

namespace App\Imports;

use App\Models\Memory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MemoryImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Memory([
            'address' => str_pad($row['address'], 4, '0', STR_PAD_LEFT),
            'instruction' => $row['instruction'],
            'value' => $row['value'] ?? null, // allow null if column is empty
        ]);
    }
}
