<?php

namespace App\Jobs\Support;

use XMLReader;
use ZipArchive;

/**
 * Streams rows out of a large .xlsx without going through PhpSpreadsheet's
 * reader, which loads the entire shared-strings table into memory as rich
 * object structures on every load() call. Files with many unique strings
 * (transaction IDs, timestamps, free-text fields) can make that table tens
 * of MB / hundreds of thousands of entries, so re-parsing it per chunk is
 * what was crashing the worker. This reads the shared strings table once as
 * plain strings, then pulls the sheet XML row-by-row via XMLReader so peak
 * memory stays bounded regardless of file size.
 */
class XlsxStreamReader
{
    private array $sharedStrings = [];
    private string $sheetPath;
    private string $tmpDir;

    public function __construct(private string $filePath)
    {
        $this->tmpDir = sys_get_temp_dir() . '/xlsx_stream_' . uniqid();
        mkdir($this->tmpDir);

        $zip = new ZipArchive();
        $zip->open($this->filePath);
        $zip->extractTo($this->tmpDir, ['xl/sharedStrings.xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels']);

        $this->sheetPath = $this->resolveFirstSheetPath($zip);
        $zip->extractTo($this->tmpDir, [$this->sheetPath]);
        $zip->close();

        $this->loadSharedStrings();
    }

    private function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = simplexml_load_file($this->tmpDir . '/xl/workbook.xml');
        $workbookXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $firstSheet = $workbookXml->xpath('//*[local-name()="sheet"]')[0] ?? null;
        $rId = (string) $firstSheet->attributes('r', true)->id;

        $relsPath = $this->tmpDir . '/xl/_rels/workbook.xml.rels';
        if ($rId && file_exists($relsPath)) {
            $rels = simplexml_load_file($relsPath);
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rId) {
                    $target = (string) $rel['Target'];
                    return 'xl/' . ltrim($target, '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function loadSharedStrings(): void
    {
        $path = $this->tmpDir . '/xl/sharedStrings.xml';
        if (!file_exists($path)) {
            return;
        }

        $reader = new XMLReader();
        $reader->open($path);

        $index = 0;
        $currentText = '';
        $inSi = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $inSi = true;
                $currentText = '';
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $inSi) {
                $currentText .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $this->sharedStrings[$index] = $currentText;
                $index++;
                $inSi = false;
            }
        }

        $reader->close();
    }

    private static function columnLetterToIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letters = $m[1] ?? 'A';
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function getHighestRow(): int
    {
        $reader = new XMLReader();
        $reader->open($this->tmpDir . '/' . $this->sheetPath);
        $highest = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'dimension') {
                $ref = $reader->getAttribute('ref');
                if ($ref && preg_match('/:[A-Z]+(\d+)$/', $ref, $m)) {
                    $highest = (int) $m[1];
                }
                break;
            }
        }
        $reader->close();

        return $highest;
    }

    /**
     * Yields [rowNumber => array $columns] for every row, columns indexed
     * 0..50 (A..AZ) matching the range the job already expects.
     */
    public function rows(int $maxColumnIndex = 51): \Generator
    {
        $reader = new XMLReader();
        $reader->open($this->tmpDir . '/' . $this->sheetPath);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowNum = (int) $reader->getAttribute('r');
            $rowData = array_fill(0, $maxColumnIndex + 1, null);

            $rowXml = $reader->readOuterXml();
            $rowNode = simplexml_load_string($rowXml);

            foreach ($rowNode->c as $cell) {
                $cellRef = (string) $cell['r'];
                $colIndex = self::columnLetterToIndex($cellRef);
                if ($colIndex > $maxColumnIndex) {
                    continue;
                }

                $type = (string) $cell['t'];
                if ($type === 's') {
                    $value = $this->sharedStrings[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } elseif ($type === 'str' || $type === 'b' || $type === '') {
                    $value = (string) $cell->v;
                } else {
                    $value = (string) $cell->v;
                }

                $rowData[$colIndex] = $value;
            }

            yield $rowNum => $rowData;
        }

        $reader->close();
    }

    public function cleanup(): void
    {
        $files = glob($this->tmpDir . '/xl/*') ?: [];
        foreach ($files as $f) {
            if (is_dir($f)) {
                continue;
            }
            @unlink($f);
        }
        @unlink($this->tmpDir . '/' . $this->sheetPath);
        $this->removeDirRecursive($this->tmpDir);
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
