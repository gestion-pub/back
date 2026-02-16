<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class PlanningsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        return $rows;
    }
}
