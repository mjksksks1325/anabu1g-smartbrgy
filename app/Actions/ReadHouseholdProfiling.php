<?php

namespace App\Actions;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ReadHouseholdProfiling
{
    /** @return array{headers: list<string>, rows: list<list<string>>} */
    public function read(UploadedFile $file): array
    {
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? $this->xlsx($file->getPathname()) : $this->csv($file->getPathname());
        if (count($rows) < 2) {
            $this->invalid('The file must contain a header row and at least one resident row.');
        }
        if (count($rows) > 501) {
            $this->invalid('Import up to 500 residents at a time. Split larger files before uploading.');
        }
        $headers = array_shift($rows);
        foreach ([$headers, ...$rows] as $row) {
            if (count($row) > 60) {
                $this->invalid('Files may contain at most 60 columns.');
            }
            foreach ($row as $value) {
                if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 1000) {
                    $this->invalid('Use UTF-8 text with at most 1000 characters per cell.');
                }
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return list<list<string>> */
    private function csv(string $path): array
    {
        $stream = fopen($path, 'r');
        if ($stream === false) {
            $this->invalid('Unable to read the uploaded file.');
        }
        try {
            $first = fgets($stream);
            if ($first === false) {
                return [];
            }
            $delimiter = ',';
            foreach ([';', "\t"] as $candidate) {
                if (count(str_getcsv($first, $candidate, '"', '')) > count(str_getcsv($first, $delimiter, '"', ''))) {
                    $delimiter = $candidate;
                }
            }
            rewind($stream);
            $rows = [];
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }
                $rows[] = array_map(fn (?string $value): string => trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF"), $row);
                if (count($rows) > 501) {
                    break;
                }
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function xml(ZipArchive $zip, string $path): DOMDocument
    {
        $stat = $zip->statName($path);
        if ($stat === false || $stat['size'] > 10 * 1024 * 1024) {
            $this->invalid('The workbook is invalid or too large to preview.');
        }
        $xml = $zip->getFromName($path);
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            $this->invalid('Unsupported workbook XML.');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                $this->invalid('Unable to read workbook XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    /** @return list<list<string>> */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('Upload a valid Excel .xlsx workbook or CSV file.');
        }
        try {
            $workbook = new DOMXPath($this->xml($zip, 'xl/workbook.xml'));
            if (in_array($workbook->evaluate('string(/*[local-name()="workbook"]/*[local-name()="workbookPr"]/@date1904)'), ['1', 'true'], true)) {
                $this->invalid('This workbook uses the 1904 date system. Save it as CSV with YYYY-MM-DD birth dates.');
            }
            $sheetId = $workbook->evaluate('string(//*[local-name()="sheets"]/*[local-name()="sheet"][1]/@*[local-name()="id"])');
            $relationships = new DOMXPath($this->xml($zip, 'xl/_rels/workbook.xml.rels'));
            $target = null;
            foreach ($relationships->query('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                if ($relationship instanceof \DOMElement && $relationship->getAttribute('Id') === $sheetId && $relationship->getAttribute('TargetMode') !== 'External') {
                    $target = $relationship->getAttribute('Target');
                }
            }
            if (! $target || str_contains($target, '..') || str_contains($target, ':')) {
                $this->invalid('The first worksheet could not be read.');
            }
            $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $shared = new DOMXPath($this->xml($zip, 'xl/sharedStrings.xml'));
                foreach ($shared->query('//*[local-name()="si"]') ?: [] as $item) {
                    if (! $item instanceof \DOMNode) {
                        continue;
                    }
                    $text = '';
                    foreach ($shared->query('.//*[local-name()="t"]', $item) ?: [] as $part) {
                        if ($part instanceof \DOMNode) {
                            $text .= $part->textContent;
                        }
                    }
                    $strings[] = $text;
                }
            }
            $sheet = new DOMXPath($this->xml($zip, $sheetPath));
            $rows = [];
            foreach ($sheet->query('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                if (! $row instanceof \DOMNode) {
                    continue;
                }
                $values = [];
                foreach ($sheet->query('./*[local-name()="c"]', $row) ?: [] as $cell) {
                    if (! $cell instanceof \DOMElement) {
                        continue;
                    }
                    $formulas = $sheet->query('./*[local-name()="f"]', $cell);
                    if ($formulas !== false && $formulas->length > 0) {
                        $this->invalid('Formula cells are not imported. Paste values into a copy of the worksheet first.');
                    }
                    preg_match('/^([A-Z]+)/', $cell->getAttribute('r'), $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? 'A') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column < 1 || $column > 60) {
                        $this->invalid('Files may contain at most 60 columns.');
                    }
                    $value = (string) $sheet->evaluate('string(./*[local-name()="v"])', $cell);
                    if ($cell->getAttribute('t') === 's') {
                        $value = $strings[(int) $value] ?? '';
                    } elseif ($cell->getAttribute('t') === 'inlineStr') {
                        $value = '';
                        foreach ($sheet->query('.//*[local-name()="t"]', $cell) ?: [] as $part) {
                            if ($part instanceof \DOMNode) {
                                $value .= $part->textContent;
                            }
                        }
                    }
                    $values[$column - 1] = trim($value);
                }
                if ($values && implode('', $values) !== '') {
                    $filled = array_fill(0, max(array_keys($values)) + 1, '');
                    foreach ($values as $index => $value) {
                        $filled[$index] = $value;
                    }
                    $rows[] = array_values($filled);
                }
                if (count($rows) > 501) {
                    break;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
