<?php

namespace App\Support;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    public static function stream(
        string $filename,
        array $headers,
        iterable $rows,
        ?User $actor,
        string $report,
        array $filters = []
    ): StreamedResponse {
        $filename = self::safeFilename($filename);

        return response()->streamDownload(function () use ($headers, $rows, $actor, $report, $filters): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");

            if ($headers !== []) {
                self::writeRow($output, $headers);
            }

            $rowCount = 0;
            foreach ($rows as $row) {
                self::writeRow($output, $row);
                $rowCount++;
            }

            fclose($output);

            try {
                app(AuditLogger::class)->record(
                    $actor,
                    'report.exported',
                    'report',
                    null,
                    [],
                    [],
                    null,
                    [
                        'format' => 'csv',
                        'report' => $report,
                        'filters' => $filters,
                        'row_count' => $rowCount,
                        'timezone' => config('app.timezone'),
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function neutralize(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        // Numeric values (including negative variances) stay numeric so they
        // cannot become formulas when opened in a spreadsheet.
        if (is_numeric($value)) {
            return $value;
        }

        if (preg_match('/^[\s]*[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    private static function writeRow($output, array $row): void
    {
        fputcsv(
            $output,
            array_map(fn ($value) => self::neutralize($value), $row),
            ',',
            '"',
            ''
        );
    }

    private static function safeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'invoiz-export.csv';

        return str_ends_with(strtolower($filename), '.csv') ? $filename : $filename . '.csv';
    }
}
