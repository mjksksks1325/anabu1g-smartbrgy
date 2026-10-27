<?php

namespace App\Actions;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Writes official, branded Excel (.xlsx) exports: barangay logo, letterhead, report title,
 * preparer, styled column headers, frozen header row and filters. Cells are always written
 * as inline text or numbers, so spreadsheet formulas can never be injected through record data.
 */
class WriteRecordsSpreadsheet
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const HEADER_ROW = 6;

    private const LOGO_WIDTH = 104;

    private const LOGO_HEIGHT = 90;

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function download(string $title, string $filename, array $headers, iterable $rows, ?string $preparedBy = null): BinaryFileResponse
    {
        $path = $this->write($title, $headers, $rows, $preparedBy);

        return response()->download($path, $filename, [
            'Content-Type' => self::CONTENT_TYPE,
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function write(string $title, array $headers, iterable $rows, ?string $preparedBy = null): string
    {
        $columnCount = max(count($headers), 2);
        $lastColumn = $this->column($columnCount);
        $widths = array_map(fn (string $header): int => mb_strlen($header) + 4, $headers);
        $widths[0] = max($widths[0] ?? 0, 18);

        $dataPath = tempnam(sys_get_temp_dir(), 'xlsx-data');
        $data = fopen($dataPath, 'w');
        $rowNumber = self::HEADER_ROW;
        foreach ($rows as $row) {
            $rowNumber++;
            $cells = '';
            foreach (array_values($row) as $index => $value) {
                $cells .= $this->cell($this->column($index + 1).$rowNumber, $value, 4);
                $widths[$index] = min(max($widths[$index] ?? 10, mb_strlen((string) $value) + 2), 60);
            }
            fwrite($data, "<row r=\"{$rowNumber}\">{$cells}</row>");
        }
        fclose($data);
        $recordCount = $rowNumber - self::HEADER_ROW;

        $letterhead = [
            1 => ['Republic of the Philippines', 2],
            2 => ['Barangay Anabu I-G, City of Imus, Province of Cavite', 1],
            3 => [$title, 5],
            4 => ['Generated '.now()->format('F j, Y g:i A').($preparedBy ? ' · Prepared by '.$preparedBy : '').' · '.$recordCount.' '.($recordCount === 1 ? 'record' : 'records'), 2],
        ];
        $sheetPath = tempnam(sys_get_temp_dir(), 'xlsx-sheet');
        $sheet = fopen($sheetPath, 'w');
        $cols = '';
        foreach (range(1, $columnCount) as $index) {
            $cols .= '<col min="'.$index.'" max="'.$index.'" width="'.($widths[$index - 1] ?? 12).'" customWidth="1"/>';
        }
        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$lastColumn.max($rowNumber, self::HEADER_ROW).'"/>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.self::HEADER_ROW.'" topLeftCell="A'.(self::HEADER_ROW + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/><cols>'.$cols.'</cols><sheetData>');
        foreach ($letterhead as $number => [$text, $style]) {
            fwrite($sheet, '<row r="'.$number.'" ht="'.($number === 2 ? 22 : 18).'" customHeight="1">'.$this->cell('B'.$number, $text, $style).'</row>');
        }
        $headerCells = '';
        foreach ($headers as $index => $header) {
            $headerCells .= $this->cell($this->column($index + 1).self::HEADER_ROW, $header, 3);
        }
        fwrite($sheet, '<row r="'.self::HEADER_ROW.'" ht="30" customHeight="1">'.$headerCells.'</row>');
        $data = fopen($dataPath, 'r');
        stream_copy_to_stream($data, $sheet);
        fclose($data);
        unlink($dataPath);
        $merges = implode('', array_map(fn (int $number): string => '<mergeCell ref="B'.$number.':'.$lastColumn.$number.'"/>', array_keys($letterhead)));
        fwrite($sheet, '</sheetData>'
            .'<autoFilter ref="A'.self::HEADER_ROW.':'.$lastColumn.max($rowNumber, self::HEADER_ROW).'"/>'
            .'<mergeCells count="'.count($letterhead).'">'.$merges.'</mergeCells>'
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<drawing r:id="rId1"/></worksheet>');
        fclose($sheet);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relationships(['rId1' => ['officeDocument', 'xl/workbook.xml']]));
        $zip->addFromString('xl/workbook.xml', $this->workbook($title, $lastColumn, max($rowNumber, self::HEADER_ROW)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relationships(['rId1' => ['worksheet', 'worksheets/sheet1.xml'], 'rId2' => ['styles', 'styles.xml']]));
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->relationships(['rId1' => ['drawing', '../drawings/drawing1.xml']]));
        $zip->addFromString('xl/drawings/drawing1.xml', $this->drawing());
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->relationships(['rId1' => ['image', '../media/logo.jpg']]));
        $zip->addFile(public_path('images/anabu-logo.jpg'), 'xl/media/logo.jpg');
        $zip->close();
        unlink($sheetPath);

        return $path;
    }

    private function cell(string $reference, mixed $value, int $style): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
        }

        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) ($value ?? '')) ?? '';

        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }

    private function column(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $number = intdiv($number - 1, 26);
        }

        return $letters;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="jpg" ContentType="image/jpeg"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            .'</Types>';
    }

    /** @param array<string, array{0: string, 1: string}> $relationships */
    private function relationships(array $relationships): string
    {
        $items = '';
        foreach ($relationships as $id => [$type, $target]) {
            $items .= '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="'.$target.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$items.'</Relationships>';
    }

    private function workbook(string $title, string $lastColumn, int $lastRow): string
    {
        $sheetName = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $title) ?: 'Records', 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.str_replace("'", "''", $sheetName).'\'!$A$'.self::HEADER_ROW.':$'.$lastColumn.'$'.$lastRow.'</definedName>'
            .'<definedName name="_xlnm.Print_Titles" localSheetId="0">\''.str_replace("'", "''", $sheetName).'\'!$'.self::HEADER_ROW.':$'.self::HEADER_ROW.'</definedName></definedNames>'
            .'</workbook>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="15"/><color rgb="FF0B3D91"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF555555"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0B3D91"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function drawing(): string
    {
        $width = self::LOGO_WIDTH * 9525;
        $height = self::LOGO_HEIGHT * 9525;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>76200</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>19050</xdr:rowOff></xdr:from>'
            .'<xdr:ext cx="'.$width.'" cy="'.$height.'"/>'
            .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Barangay Anabu I-G logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            .'<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
            .'<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }
}
