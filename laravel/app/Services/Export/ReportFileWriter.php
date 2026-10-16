<?php

namespace App\Services\Export;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ghi đúng definition của ReportService. Không đọc thêm database.
 */
class ReportFileWriter
{
    /** @param  array<string, mixed>  $definition */
    public function excel(array $definition): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle($this->sheetTitle((string) $definition['title']));
        $row = 1;
        $sheet->setCellValue('A' . $row, (string) $definition['shop']);
        $row++;
        $sheet->setCellValue('A' . $row, (string) $definition['title']);
        $row++;
        if (($definition['subtitle'] ?? '') !== '') {
            $sheet->setCellValue('A' . $row, (string) $definition['subtitle']);
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'Xuất lúc: ' . $definition['generated_at'] . ' · ' . $definition['scope']);
        $row++;
        foreach ($definition['filters'] as $line) {
            $sheet->setCellValue('A' . $row, $line['label'] . ': ' . $line['value']);
            $row++;
        }
        if ($definition['summary'] !== []) {
            $row++;
            $col = 1;
            foreach ($definition['summary'] as $card) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, (string) $card['label']);
                $this->put($sheet, $col, $row + 1, $card['value'], (string) $card['type']);
                $col++;
            }
            $row += 3;
        }
        $header = $row;
        $col = 1;
        foreach ($definition['columns'] as $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $header, (string) $column['label']);
            $col++;
        }
        $row = $header + 1;
        foreach ($definition['rows'] as $data) {
            $col = 1;
            foreach ($definition['columns'] as $column) {
                $this->put($sheet, $col, $row, $data[$column['key']] ?? '', (string) $column['type']);
                $col++;
            }
            $row++;
        }
        foreach ($definition['totals'] as $total) {
            $sheet->setCellValue('A' . $row, (string) $total['label']);
            $this->put($sheet, 2, $row, $total['value'], (string) $total['type']);
            $row++;
        }
        foreach ($definition['notes'] as $note) {
            $sheet->setCellValue('A' . $row, (string) $note);
            $row++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'b13xlsx');
        (new Xlsx($sheet->getParent()))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** @param  array<string, mixed>  $definition */
    public function pdf(array $definition): string
    {
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => ! empty($definition['landscape']) ? 'A4-L' : 'A4',
            'tempDir' => $dir,
        ]);
        $mpdf->compress = false;
        $mpdf->WriteHTML($this->html($definition));

        return $mpdf->Output('', 'S');
    }

    /** @param  array<string, mixed>  $definition */
    public function html(array $definition): string
    {
        $esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $html = '<h1>' . $esc($definition['shop']) . '</h1>';
        $html .= '<h2>' . $esc($definition['title']) . '</h2>';
        if (($definition['subtitle'] ?? '') !== '') {
            $html .= '<p>' . $esc($definition['subtitle']) . '</p>';
        }
        $html .= '<p>Xuất lúc: ' . $esc($definition['generated_at']) . ' · ' . $esc($definition['scope']) . '</p>';
        foreach ($definition['filters'] as $line) {
            $html .= '<p>' . $esc($line['label'] . ': ' . $line['value']) . '</p>';
        }
        if ($definition['summary'] !== []) {
            $html .= '<ul>';
            foreach ($definition['summary'] as $card) {
                $html .= '<li>' . $esc($card['label']) . ': ' . $esc($this->display($card['value'], (string) $card['type'])) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '<table border="1" cellpadding="4"><thead><tr>';
        foreach ($definition['columns'] as $column) {
            $html .= '<th>' . $esc($column['label']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($definition['rows'] as $data) {
            $html .= '<tr>';
            foreach ($definition['columns'] as $column) {
                $html .= '<td>' . $esc($this->display($data[$column['key']] ?? '', (string) $column['type'])) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        foreach ($definition['totals'] as $total) {
            $html .= '<p>' . $esc($total['label']) . ': ' . $esc($this->display($total['value'], (string) $total['type'])) . '</p>';
        }

        return $html;
    }

    private function put($sheet, int $col, int $row, mixed $value, string $type): void
    {
        $cell = Coordinate::stringFromColumnIndex($col) . $row;
        if ($type === 'money' || $type === 'int') {
            $sheet->setCellValue($cell, (float) $value);

            return;
        }
        $sheet->setCellValue($cell, (string) $value);
    }

    private function display(mixed $value, string $type): string
    {
        if ($type === 'money' && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }
        if ($type === 'int' && is_numeric($value)) {
            return number_format((int) $value, 0, ',', '.');
        }

        return (string) $value;
    }

    private function sheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?: 'Bao cao';

        return mb_substr($title, 0, 31);
    }
}
