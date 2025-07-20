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
        'address' => str_pad(decbin($row[0]), 4, '0', STR_PAD_LEFT),
        'instruction' => $row[1],
        'value' => $row[2], // <- Make sure your Excel has this
    ]);
}
}
