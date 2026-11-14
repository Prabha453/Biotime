<?php

namespace App\Libraries;

/**
 * Minimal .xlsx reader — pulls the first worksheet back as a list of string
 * rows. Handles shared strings, inline strings and plain numeric cells, and
 * respects column gaps (A,C -> ['A'=>.., ''=>'' , 'C'=>..]). Enough to read a
 * filled-in import template; not a general-purpose spreadsheet parser.
 */
class XlsxReader
{
    /**
     * @return list<list<string>>  rows of cell values, gaps padded with ''
     */
    public static function rows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Not a readable .xlsx file.');
        }

        // shared strings
        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = @simplexml_load_string($ss);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $shared[] = self::siText($si);
                }
            }
        }

        // first worksheet target (fall back to sheet1.xml)
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false
            && ($wb = $zip->getFromName('xl/workbook.xml')) !== false) {
            $relsXml = @simplexml_load_string($rels);
            $wbXml   = @simplexml_load_string($wb);
            if ($relsXml !== false && $wbXml !== false) {
                $wbXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $first = $wbXml->sheets->sheet[0] ?? null;
                if ($first !== null) {
                    $rid = (string) $first->attributes('r', true)->id;
                    foreach ($relsXml->Relationship as $rel) {
                        if ((string) $rel['Id'] === $rid) {
                            $target = (string) $rel['Target'];
                            $sheetPath = str_starts_with($target, '/')
                                ? ltrim($target, '/') : 'xl/' . $target;
                            break;
                        }
                    }
                }
            }
        }

        $data = $zip->getFromName($sheetPath);
        $zip->close();
        if ($data === false) {
            throw new \RuntimeException('The workbook has no readable sheet.');
        }
        $xml = @simplexml_load_string($data);
        if ($xml === false) {
            throw new \RuntimeException('The worksheet XML could not be parsed.');
        }

        $out = [];
        foreach ($xml->sheetData->row as $row) {
            // Preserve worksheet row numbers even when Excel omits blank rows.
            $rowNumber = (int) $row['r'];
            if ($rowNumber > 1048576) {
                throw new \RuntimeException('Invalid worksheet row number.');
            }
            while (count($out) < $rowNumber - 1) {
                $out[] = [];
            }
            $cells = [];
            $max   = 0;
            foreach ($row->c as $c) {
                $ref  = (string) $c['r'];
                $col  = self::colIndex(preg_replace('/\d+/', '', $ref) ?: 'A');
                $type = (string) $c['t'];
                $val  = '';
                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = self::siText($c->is);
                } elseif ($type === 'str') {
                    $val = (string) $c->v;
                } else {
                    $val = (string) $c->v;
                }
                $cells[$col] = trim($val);
                $max         = max($max, $col);
            }
            if (! $cells) {
                $out[] = [];
                continue;
            }
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $out[] = $line;
        }

        return $out;
    }

    private static function siText(\SimpleXMLElement $si): string
    {
        if (isset($si->t)) {
            return (string) $si->t;
        }
        $s = '';
        foreach ($si->r ?? [] as $r) {          // rich-text runs (empty inline cells have no runs)
            $s .= (string) $r->t;
        }

        return $s;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
