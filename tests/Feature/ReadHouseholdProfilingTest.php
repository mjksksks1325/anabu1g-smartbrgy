<?php

use App\Actions\ReadHouseholdProfiling;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

function profilingWorkbook(string $sheet, string $properties = '', string $shared = ''): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'profiling-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.$properties.'<sheets><sheet name="Residents" sheetId="7" r:id="rId7"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId7" Target="worksheets/sheet7.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet7.xml', $sheet);
    if ($shared !== '') {
        $zip->addFromString('xl/sharedStrings.xml', $shared);
    }
    $zip->close();
    $contents = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('profiling.xlsx', $contents);
}

it('reads the first workbook relationship with shared strings inline strings and sparse cells', function () {
    $file = profilingWorkbook('<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="inlineStr"><is><t>date_of_birth</t></is></c></row><row r="2"><c r="A2" t="s"><v>1</v></c><c r="C2"><v>29355</v></c></row></sheetData></worksheet>', '', '<sst><si><t>first_name</t></si><si><r><t>Juan</t></r><r><t> Jose</t></r></si></sst>');

    expect(app(ReadHouseholdProfiling::class)->read($file))->toBe(['headers' => ['first_name', '', 'date_of_birth'], 'rows' => [['Juan Jose', '', '29355']]]);
});

it('rejects formulas invalid XML and unsupported workbook dates', function (string $sheet, string $properties) {
    expect(fn () => app(ReadHouseholdProfiling::class)->read(profilingWorkbook($sheet, $properties)))->toThrow(ValidationException::class);
})->with([
    'formula' => ['<worksheet><sheetData><row><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>', ''],
    'invalid XML' => ['<worksheet><sheetData>', ''],
    'entity' => ['<!DOCTYPE worksheet [<!ENTITY value "Juan">]><worksheet/>', ''],
    '1904 dates' => ['<worksheet/>', '<workbookPr date1904="1"/>'],
]);

it('reads UTF8 CSV headers with BOM and supported delimiters', function (string $delimiter) {
    $file = UploadedFile::fake()->createWithContent('profiling.csv', "\xEF\xBB\xBFfirst_name{$delimiter}last_name\nJuan{$delimiter}Dela Cruz\n\n");

    expect(app(ReadHouseholdProfiling::class)->read($file))->toBe(['headers' => ['first_name', 'last_name'], 'rows' => [['Juan', 'Dela Cruz']]]);
})->with([',', ';', "\t"]);

it('rejects excessive rows columns and unsupported cell text', function (string $contents) {
    expect(fn () => app(ReadHouseholdProfiling::class)->read(UploadedFile::fake()->createWithContent('profiling.csv', $contents)))->toThrow(ValidationException::class);
})->with([
    '501 residents' => ["first_name\n".str_repeat("Juan\n", 501)],
    '61 columns' => ["first_name\n".implode(',', array_fill(0, 61, 'Juan'))],
    'long cell' => ["first_name\n".str_repeat('a', 1001)],
    'invalid encoding' => ["first_name\n\xFF"],
]);
