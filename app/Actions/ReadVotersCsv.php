<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ReadVotersCsv
{
    public const HEADERS = ['resident_number', 'comelec_voter_number', 'precinct_number', 'cluster_number', 'registration_date'];

    /** @return list<array{row: int, fields: array<string, string>}> */
    public function read(UploadedFile $file): array
    {
        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read the CSV file.']);
        }

        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if ($headers === false) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $headers = array_map(fn (?string $value): string => strtolower(trim((string) $value)), $headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            if (count($headers) !== count(self::HEADERS) || array_diff(self::HEADERS, $headers) !== []) {
                throw ValidationException::withMessages(['file' => 'Required CSV headers: '.implode(', ', self::HEADERS).'.']);
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($rows) >= 500) {
                    throw ValidationException::withMessages(['file' => 'Import a maximum of 500 voters per file.']);
                }
                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => "CSV row {$line}: column count does not match the headers."]);
                }
                $values = array_map(fn (?string $value): string => trim((string) $value), $values);
                foreach ($values as $value) {
                    if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                        throw ValidationException::withMessages(['file' => "CSV row {$line}: use UTF-8 text without null characters."]);
                    }
                }
                $rows[] = ['row' => $line, 'fields' => array_combine($headers, $values)];
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'The CSV must contain at least one voter row.']);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
