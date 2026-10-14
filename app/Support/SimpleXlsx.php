<?php

namespace App\Support;

/**
 * A small Excel (.xlsx) writer with no dependencies: one sheet, a bold green header row that stays in view and
 * has filter buttons, column widths, real numbers (money with thousands separators) and an optional totals row.
 * An .xlsx is a zip of XML files; this writes them uncompressed, so it needs neither the zip extension nor a library.
 */
final class SimpleXlsx
{
    public const TEXT = 'text';

    public const MONEY = 'money';

    public const INTEGER = 'integer';

    /**
     * @param  list<array{title: string, width?: float|int, type?: string}>  $columns
     * @param  iterable<list<scalar|null>>  $rows  values in the order of $columns
     * @param  list<scalar|null>|null  $totals  a bold row after the data (same order as $columns)
     */
    public static function build(string $sheetName, array $columns, iterable $rows, ?array $totals = null): string
    {
        $types = array_map(fn (array $column) => $column['type'] ?? self::TEXT, $columns);
        $lastColumn = self::column(count($columns));

        $sheetRows = '<row r="1" ht="22" customHeight="1">';
        foreach ($columns as $i => $column) {
            $sheetRows .= self::text(self::column($i + 1).'1', $column['title'], 1);
        }
        $sheetRows .= '</row>';

        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            $sheetRows .= '<row r="'.$rowNumber.'">';
            foreach (array_values($row) as $i => $value) {
                $sheetRows .= self::cell(self::column($i + 1).$rowNumber, $value, $types[$i] ?? self::TEXT, false);
            }
            $sheetRows .= '</row>';
        }
        $lastDataRow = $rowNumber;

        if ($totals !== null) {
            $rowNumber++;
            $sheetRows .= '<row r="'.$rowNumber.'">';
            foreach (array_values($totals) as $i => $value) {
                $sheetRows .= self::cell(self::column($i + 1).$rowNumber, $value, $types[$i] ?? self::TEXT, true);
            }
            $sheetRows .= '</row>';
        }

        $cols = '';
        foreach ($columns as $i => $column) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.($column['width'] ?? 16).'" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .($lastDataRow > 1 ? '<autoFilter ref="A1:'.$lastColumn.$lastDataRow.'"/>' : '')
            .'</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F5C45"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top style="thin"><color rgb="FF1F5C45"/></top><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="4" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';

        $name = self::xml(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $sheetName) ?: 'Sheet1', 0, 31));

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    /** "A", "B", ... "Z", "AA" for the 1-based column number. */
    private static function column(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + $number % 26).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private static function xml(string $value): string
    {
        // Control characters other than tab and line breaks are not allowed in XML at all.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function text(string $ref, mixed $value, int $style): string
    {
        return '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.self::xml((string) $value).'</t></is></c>';
    }

    private static function cell(string $ref, mixed $value, string $type, bool $total): string
    {
        if ($value === null || $value === '') {
            return $total ? '<c r="'.$ref.'" s="3"/>' : '';
        }
        if ($type === self::MONEY && is_numeric($value)) {
            return '<c r="'.$ref.'" s="'.($total ? 4 : 2).'"><v>'.(0 + $value).'</v></c>';
        }
        if ($type === self::INTEGER && is_numeric($value)) {
            return '<c r="'.$ref.'" s="'.($total ? 3 : 5).'"><v>'.(int) $value.'</v></c>';
        }

        return self::text($ref, $value, $total ? 3 : 0);
    }

    /** @param  array<string, string>  $files */
    private static function zip(array $files): string
    {
        $local = '';
        $central = '';
        $count = 0;
        $time = ((int) date('H') << 11) | ((int) date('i') << 5) | ((int) date('s') >> 1);
        $date = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');

        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $size = strlen($data);
            $offset = strlen($local);
            $header = pack('vvvvvVVVvv', 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0);
            $local .= "PK\x03\x04".$header.$name.$data;
            $central .= "PK\x01\x02".pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
            $count++;
        }

        return $local.$central."PK\x05\x06".pack('vvvvVVv', 0, 0, $count, $count, strlen($central), strlen($local), 0);
    }
}
