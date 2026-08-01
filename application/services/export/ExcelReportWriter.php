<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelReportWriter
{
	public function write(ExportReportDefinition $def)
	{
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle($this->_sheetTitle($def->title));

		$row = 1;
		$sheet->setCellValue('A' . $row, $def->shop_name);
		$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
		$row++;

		$sheet->setCellValue('A' . $row, $def->title);
		$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
		$row++;

		if ($def->subtitle !== '') {
			$sheet->setCellValue('A' . $row, $def->subtitle);
			$row++;
		}

		$sheet->setCellValue('A' . $row, 'Xuất lúc: ' . $def->generated_at . ' · ' . $def->scope_label);
		$row++;

		foreach ($def->filter_lines as $line) {
			$sheet->setCellValue('A' . $row, $line['label'] . ': ' . $line['value']);
			$row++;
		}

		if (!empty($def->summary_cards)) {
			$row++;
			$col = 1;
			foreach ($def->summary_cards as $card) {
				$cell = $this->_colLetter($col) . $row;
				$sheet->setCellValue($cell, $card['label']);
				$sheet->getStyle($cell)->getFont()->setBold(true);
				$sheet->setCellValue($this->_colLetter($col) . ($row + 1), $this->_formatValue($card['value'], $card['type']));
				$col++;
			}
			$row += 3;
		}

		$headerRow = $row;
		$col = 1;
		foreach ($def->columns as $column) {
			$cell = $this->_colLetter($col) . $headerRow;
			$sheet->setCellValue($cell, $column['label']);
			$col++;
		}

		$lastCol = $this->_colLetter(count($def->columns));
		$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
		$sheet->getStyle($headerRange)->applyFromArray(array(
			'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
			'fill' => array(
				'fillType' => Fill::FILL_SOLID,
				'startColor' => array('rgb' => '1F2937'),
			),
			'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER),
			'borders' => array(
				'allBorders' => array('borderStyle' => Border::BORDER_THIN),
			),
		));

		$row = $headerRow + 1;
		foreach ($def->rows as $dataRow) {
			$col = 1;
			foreach ($def->columns as $column) {
				$key = $column['key'];
				$value = isset($dataRow[$key]) ? $dataRow[$key] : '';
				$cell = $this->_colLetter($col) . $row;
				if ($column['type'] === 'money' && is_numeric($value)) {
					$sheet->setCellValue($cell, (float) $value);
					$sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
				} elseif ($column['type'] === 'int' && is_numeric($value)) {
					$sheet->setCellValue($cell, (int) $value);
				} else {
					$sheet->setCellValue($cell, $this->_formatValue($value, $column['type']));
				}
				$col++;
			}
			$row++;
		}

		if (!empty($def->totals)) {
			$row++;
			foreach ($def->totals as $total) {
				$sheet->setCellValue('A' . $row, $total['label']);
				$sheet->getStyle('A' . $row)->getFont()->setBold(true);
				$sheet->setCellValue('B' . $row, $this->_formatValue($total['value'], $total['type']));
				if ($total['type'] === 'money' && is_numeric($total['value'])) {
					$sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');
				}
				$row++;
			}
		}

		for ($c = 1; $c <= count($def->columns); $c++) {
			$sheet->getColumnDimension($this->_colLetter($c))->setAutoSize(true);
		}

		$writer = new Xlsx($spreadsheet);
		ob_start();
		$writer->save('php://output');
		return ob_get_clean();
	}

	protected function _formatValue($value, $type)
	{
		if ($type === 'money' && is_numeric($value)) {
			return number_format((float) $value, 0, ',', '.');
		}
		if ($type === 'int' && is_numeric($value)) {
			return (int) $value;
		}
		return (string) $value;
	}

	protected function _colLetter($index)
	{
		$index = (int) $index;
		$letter = '';
		while ($index > 0) {
			$mod = ($index - 1) % 26;
			$letter = chr(65 + $mod) . $letter;
			$index = (int) (($index - $mod) / 26);
		}
		return $letter;
	}

	protected function _sheetTitle($title)
	{
		$title = preg_replace('/[\\\\\\/*\\[\\]:?]/', ' ', (string) $title);
		$title = trim($title);
		if ($title === '') {
			$title = 'Bao cao';
		}
		return mb_substr($title, 0, 31, 'UTF-8');
	}
}
