<?php

namespace App\Utils;

use App\Exports\ArrayExport;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ExportUtil
{
    public const EXT_CSV = 'csv';
    public const EXT_XLS = 'xls';
    public const EXT_XLSX = 'xlsx';
    private const ALLOWED_EXTENSIONS = [self::EXT_CSV, self::EXT_XLS, self::EXT_XLSX];
    private const EXTENSION_MIME_MAP = [
        self::EXT_CSV => 'text/csv; charset=UTF-8',
        self::EXT_XLS => 'application/vnd.ms-excel',
        self::EXT_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const WRITER_TYPE_MAP = [
        self::EXT_CSV => ExcelWriter::CSV,
        self::EXT_XLS => ExcelWriter::XLS,
        self::EXT_XLSX => ExcelWriter::XLSX,
    ];

    /**
     * 直接导出Excel（CSV格式，Excel可直接打开）
     *
     * @param string   $filename  文件名
     * @param array    $headers   表头
     * @param iterable $rows      数据行
     * @param string   $extension 文件扩展名（csv/xls/xlsx）
     *
     * @return Response
     */
    public static function exportExcelDirect(
        string $filename,
        array $headers,
        iterable $rows,
        string $extension = self::EXT_CSV
    ): Response
    {
        $extension = self::normalizeExtension($extension);
        $filename = self::normalizeFilename($filename, $extension);
        $contentType = self::EXTENSION_MIME_MAP[$extension] ?? self::EXTENSION_MIME_MAP[self::EXT_CSV];

        if ($extension !== self::EXT_CSV) {
            $writerType = self::resolveWriterType($extension);
            $export = new ArrayExport($headers, $rows);

            return Excel::download($export, $filename, $writerType);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            // BOM helps Excel correctly detect UTF-8 encoding.
            fwrite($output, "\xEF\xBB\xBF");

            if (! empty($headers)) {
                fputcsv($output, $headers);
            }

            foreach ($rows as $row) {
                if ($row instanceof Arrayable) {
                    $row = $row->toArray();
                } elseif ($row instanceof \JsonSerializable) {
                    $row = $row->jsonSerialize();
                } elseif (! is_array($row)) {
                    $row = (array) $row;
                }

                fputcsv($output, array_values($row));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * 规范化文件名与扩展名
     *
     * @param string $filename  文件名
     * @param string $extension 扩展名
     *
     * @return string
     */
    private static function normalizeFilename(string $filename, string $extension): string
    {
        $filename = trim($filename);
        $extension = ltrim($extension, '.');

        if ($filename === '') {
            $filename = 'export';
        }

        $existingExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($existingExtension, self::ALLOWED_EXTENSIONS, true)) {
            $filename = substr($filename, 0, - (strlen($existingExtension) + 1));
        }

        if (pathinfo($filename, PATHINFO_EXTENSION) !== $extension) {
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    /**
     * 规范化扩展名
     *
     * @param string $extension 扩展名
     *
     * @return string
     */
    public static function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return self::EXT_CSV;
        }

        return $extension;
    }

    /**
     * 获取Excel写入器类型
     *
     * @param string $extension 扩展名
     *
     * @return string
     */
    public static function resolveWriterType(string $extension): string
    {
        $extension = self::normalizeExtension($extension);

        return self::WRITER_TYPE_MAP[$extension] ?? ExcelWriter::CSV;
    }

}
