<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * One tabular dataset → a download in any of the four formats the grid
 * "Export" menu offers: csv · txt · xlsx · pdf (print-to-PDF HTML).
 *
 * Shared by Exports (SQL-backed list exports) and any controller that
 * builds its rows in PHP (e.g. the calculator-backed attendance reports).
 *
 *   return Export::stream('xlsx', 'time-card_20260909', 'Time Card',
 *       $headers, $data, ['title' => 'Time Card', 'meta' => '…', 'widths' => [...], 'freeze' => true]);
 */
final class Export
{
    /**
     * @param 'csv'|'txt'|'xlsx'|'pdf'|string $format
     * @param list<string>                    $headers
     * @param list<list<string|int|float|null>> $data
     * @param array{title?:string,meta?:string,widths?:list<int|float>,freeze?:bool} $opts
     */
    public static function stream(string $format, string $fname, string $sheet, array $headers, array $data, array $opts = []): ResponseInterface
    {
        $format = strtolower($format) ?: 'xlsx';
        $fname  = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fname) ?: 'export';

        return match ($format) {
            'csv'   => self::delimited($fname . '.csv', ',', 'text/csv', $headers, $data),
            'txt'   => self::delimited($fname . '.txt', "\t", 'text/plain', $headers, $data),
            'pdf'   => self::printable($opts['title'] ?? $sheet, $opts['meta'] ?? '', $headers, $data),
            default => self::spreadsheet($fname . '.xlsx', $sheet, $headers, $data, $opts),
        };
    }

    private static function spreadsheet(string $filename, string $sheet, array $headers, array $data, array $opts): ResponseInterface
    {
        $xlsx = new Xlsx($sheet);
        if (! empty($opts['title'])) {
            $xlsx->title($opts['title']);
        }
        if (! empty($opts['meta'])) {
            $xlsx->meta($opts['meta']);
        }
        if (! empty($opts['widths'])) {
            $xlsx->widths($opts['widths']);
        }
        $xlsx->headers($headers);
        foreach ($data as $row) {
            $xlsx->row(array_values((array) $row));
        }
        if (! empty($opts['freeze'])) {
            $xlsx->freezeHeader();
        }

        return $xlsx->download($filename);
    }

    /** CSV / TXT — a delimited download built with fputcsv (UTF-8 BOM for Excel). */
    private static function delimited(string $filename, string $sep, string $mime, array $headers, array $data): ResponseInterface
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers, $sep);
        foreach ($data as $row) {
            fputcsv($fh, array_map(static fn ($v) => str_replace("\n", ' / ', (string) $v), array_values((array) $row)), $sep);
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);

        return service('response')
            ->setHeader('Content-Type', $mime . '; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $body);
    }

    /**
     * PDF — no PDF engine is bundled, so return a print-optimised HTML page
     * that auto-opens the browser's print dialog (→ "Save as PDF").
     */
    private static function printable(string $title, string $meta, array $headers, array $data): ResponseInterface
    {
        $safeTitle = esc((string) $title);
        $safeMeta  = esc($meta !== '' ? $meta : (count($data) . ' records · exported ' . date('Y-m-d H:i')));
        $th        = implode('', array_map(static fn ($h) => '<th>' . esc((string) $h) . '</th>', $headers));
        $tr        = '';
        foreach ($data as $row) {
            $tr .= '<tr>' . implode('', array_map(
                static fn ($c) => '<td>' . nl2br(esc((string) $c)) . '</td>',
                array_values((array) $row)
            )) . '</tr>';
        }

        $html = <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>{$safeTitle}</title>
<style>
  *{box-sizing:border-box} body{font:12px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:22px;color:#1a1a1a}
  h1{font-size:17px;margin:0 0 2px;color:#1F4E5F} .meta{color:#666;font-size:11px;margin-bottom:14px}
  table{border-collapse:collapse;width:100%} th,td{border:1px solid #cdd5db;padding:5px 8px;text-align:left;vertical-align:top}
  thead th{background:#1F4E5F;color:#fff;font-weight:600} tbody tr:nth-child(even){background:#f2f7f9}
  @media print{ body{margin:8mm} .noprint{display:none} thead{display:table-header-group} }
</style></head><body>
<div class="noprint" style="margin-bottom:12px">
  <button onclick="window.print()" style="padding:6px 14px;font-size:13px;cursor:pointer">Print / Save as PDF</button>
</div>
<h1>{$safeTitle}</h1>
<div class="meta">{$safeMeta}</div>
<table><thead><tr>{$th}</tr></thead><tbody>{$tr}</tbody></table>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},250);});</script>
</body></html>
HTML;

        return service('response')->setHeader('Content-Type', 'text/html; charset=UTF-8')->setBody($html);
    }
}
