<?php

namespace App\Libraries;

/**
 * Minimal .xlsx (OOXML SpreadsheetML) writer — no external dependency, just
 * ZipArchive + a handful of XML parts.
 *
 * Two modes, chosen automatically:
 *
 *  - PLAIN (default) — one sheet, a bold header row, then rows written as
 *    inline strings so Excel never re-types them. Used for import templates.
 *
 *        (new Xlsx('Employees'))
 *            ->headers(['Employee ID', 'First Name'])
 *            ->row(['10001', 'John'])
 *            ->download('employee_import_template.xlsx');
 *
 *  - DESIGNED — call ->title() / ->meta() / ->widths() / ->freezeHeader()
 *    and the sheet gains a merged title banner, a filled + bordered header
 *    row, thin cell borders, zebra striping, frozen header and an autofilter.
 *
 *        (new Xlsx('Time Card'))
 *            ->title('Time Card')
 *            ->meta('2026-07-01 to 2026-07-07 - 143 rows')
 *            ->headers([...])->widths([14, 10, ...])
 *            ->row([...])
 *            ->freezeHeader()
 *            ->download('time-card.xlsx');
 */
class Xlsx
{
    private string $sheetName;

    /** @var list<list<string>> */
    private array $rows = [];

    /** @var list<string> */
    private array $head = [];

    private string $title = '';
    private string $meta  = '';
    private bool $freeze   = false;

    /** @var list<int|float> */
    private array $widths = [];

    public function __construct(string $sheetName = 'Sheet1')
    {
        // Excel sheet names: <=31 chars, none of []:*?/\
        $this->sheetName = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $sheetName), 0, 31) ?: 'Sheet1';
    }

    /** @param list<string> $cells */
    public function headers(array $cells): self
    {
        $this->head = array_values(array_map('strval', $cells));

        return $this;
    }

    /** @param list<string|int|float|null> $cells */
    public function row(array $cells): self
    {
        $this->rows[] = array_values(array_map(static fn ($v) => $v === null ? '' : (string) $v, $cells));

        return $this;
    }

    /** Merged banner on row 1 — switches the sheet into DESIGNED mode. */
    public function title(string $text): self
    {
        $this->title = $text;

        return $this;
    }

    /** Merged sub-line under the title (date range, row count, timestamp…). */
    public function meta(string $text): self
    {
        $this->meta = $text;

        return $this;
    }

    /** Explicit column widths (Excel width units). Also enables DESIGNED mode. */
    public function widths(array $widths): self
    {
        $this->widths = array_values(array_map('floatval', $widths));

        return $this;
    }

    /** Freeze every row down to and including the header. */
    public function freezeHeader(): self
    {
        $this->freeze = true;

        return $this;
    }

    private function designed(): bool
    {
        return $this->title !== '' || $this->meta !== '' || $this->widths !== [];
    }

    /** Build the .xlsx and return its bytes. */
    public function build(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->esc($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** Stream the workbook to the browser as a download response. */
    public function download(string $filename): \CodeIgniter\HTTP\ResponseInterface
    {
        return service('response')
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . str_replace('"', '', $filename) . '"')
            ->setBody($this->build());
    }

    // ------------------------------------------------------------------
    //  styles
    // ------------------------------------------------------------------

    /**
     * cellXfs indices:
     *   0 default · 1 bold (plain header)
     *   2 title · 3 meta · 4 header (white on teal, bordered, centred)
     *   5 data (bordered, top, wrap) · 6 data zebra
     */
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF1F4E5F"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF7F7F7F"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E5F"/><bgColor rgb="FF1F4E5F"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F7F9"/><bgColor rgb="FFF2F7F9"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFD0D7DE"/></left><right style="thin"><color rgb="FFD0D7DE"/></right>'
            . '<top style="thin"><color rgb="FFD0D7DE"/></top><bottom style="thin"><color rgb="FFD0D7DE"/></bottom><diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    // ------------------------------------------------------------------
    //  sheet
    // ------------------------------------------------------------------

    private function sheetXml(): string
    {
        $designed = $this->designed();
        $colCount = max(count($this->head), ...array_map('count', $this->rows ?: [[]]));
        $colCount = max(1, $colCount);
        $lastCol  = $this->colLetter($colCount - 1);

        $rowsXml = '';
        $merges  = [];
        $r       = 1;

        if ($designed && $this->title !== '') {
            $rowsXml .= '<row r="' . $r . '" ht="22" customHeight="1">'
                . '<c r="A' . $r . '" s="2" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($this->title) . '</t></is></c>'
                . '</row>';
            $merges[] = 'A' . $r . ':' . $lastCol . $r;
            $r++;
        }
        if ($designed && $this->meta !== '') {
            $rowsXml .= '<row r="' . $r . '" ht="15" customHeight="1">'
                . '<c r="A' . $r . '" s="3" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($this->meta) . '</t></is></c>'
                . '</row>';
            $merges[] = 'A' . $r . ':' . $lastCol . $r;
            $r++;
        }

        $headerRow = 0;
        if ($this->head) {
            $headerRow = $r;
            $hs        = $designed ? '4' : '1';
            $ht        = $designed ? ' ht="26" customHeight="1"' : '';
            $cells     = '';
            foreach ($this->head as $c => $val) {
                $cells .= '<c r="' . $this->colLetter($c) . $r . '" s="' . $hs . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->esc((string) $val) . '</t></is></c>';
            }
            $rowsXml .= '<row r="' . $r . '"' . $ht . '>' . $cells . '</row>';
            $r++;
        }

        $firstData = $r;
        foreach ($this->rows as $i => $cells) {
            $s = $designed ? ($i % 2 ? '6' : '5') : '';
            $sAttr = $s === '' ? '' : ' s="' . $s . '"';
            $cellsXml = '';
            foreach ($cells as $c => $val) {
                $ref = $this->colLetter($c) . $r;
                $cellsXml .= '<c r="' . $ref . '"' . $sAttr . ' t="inlineStr"><is><t xml:space="preserve">'
                    . $this->esc((string) $val) . '</t></is></c>';
            }
            $rowsXml .= '<row r="' . $r . '">' . $cellsXml . '</row>';
            $r++;
        }
        $lastRow = $r - 1;

        // ---- worksheet assembly (child order matters to Excel) ----
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastCol . max(1, $lastRow) . '"/>';

        if ($this->freeze && $headerRow > 0) {
            $xml .= '<sheetViews><sheetView workbookViewId="0">'
                . '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A' . ($headerRow + 1) . '" sqref="A' . ($headerRow + 1) . '"/>'
                . '</sheetView></sheetViews>';
        }

        $cols = $this->colsXml($colCount);
        if ($cols !== '') {
            $xml .= $cols;
        }

        $xml .= '<sheetData>' . $rowsXml . '</sheetData>';

        if ($designed && $headerRow > 0 && $lastRow >= $firstData) {
            $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastCol . $lastRow . '"/>';
        }
        if ($merges) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $m) {
                $xml .= '<mergeCell ref="' . $m . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        return $xml . '</worksheet>';
    }

    private function colsXml(int $colCount): string
    {
        if (! $this->designed()) {
            return '';
        }
        $widths = $this->widths;
        if ($widths === []) {
            // auto: size each column to its widest cell (header or body), clamped
            for ($c = 0; $c < $colCount; $c++) {
                $max = mb_strlen($this->head[$c] ?? '');
                foreach ($this->rows as $row) {
                    foreach (explode("\n", (string) ($row[$c] ?? '')) as $line) {
                        $max = max($max, mb_strlen($line));
                    }
                }
                $widths[$c] = min(48, max(10, $max + 3));
            }
        }
        $out = '<cols>';
        foreach ($widths as $i => $w) {
            $out .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . round((float) $w, 2) . '" customWidth="1"/>';
        }

        return $out . '</cols>';
    }

    private function colLetter(int $i): string
    {
        $s = '';
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26) . $s;
        }

        return $s;
    }

    private function esc(string $s): string
    {
        // normalise newlines so wrapText renders them
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
