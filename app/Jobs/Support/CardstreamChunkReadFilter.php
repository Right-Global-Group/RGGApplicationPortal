<?php

namespace App\Jobs\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class CardstreamChunkReadFilter implements IReadFilter
{
    public function __construct(
        public int $startRow,
        public int $endRow
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
