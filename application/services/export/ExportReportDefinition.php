<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ExportReportDefinition
{
	public $module = '';
	public $title = '';
	public $subtitle = '';
	public $shop_name = '';
	public $generated_at = '';
	public $scope_label = '';
	public $filter_lines = array();
	public $columns = array();
	public $rows = array();
	public $totals = array();
	public $summary_cards = array();
	public $landscape = false;
	public $notes = array();
	public $filename_base = 'bao-cao';

	public function columnCount()
	{
		return count($this->columns);
	}

	public function addFilterLine($label, $value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return;
		}
		$this->filter_lines[] = array(
			'label' => (string) $label,
			'value' => $value,
		);
	}

	public function addTotal($label, $value, $type = 'text')
	{
		$this->totals[] = array(
			'label' => (string) $label,
			'value' => $value,
			'type' => (string) $type,
		);
	}

	public function addSummaryCard($label, $value, $type = 'text')
	{
		$this->summary_cards[] = array(
			'label' => (string) $label,
			'value' => $value,
			'type' => (string) $type,
		);
	}
}
