<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class DashboardExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'dashboard';
	}

	public function buildDefinition()
	{
		$this->ci()->load->helper('admin_dashboard');

		$period = $this->filter()->get('period') ? $this->filter()->get('period') : '30d';
		$from = trim((string) $this->filter()->get('from'));
		$to = trim((string) $this->filter()->get('to'));

		$dash = admin_dashboard_get_data(array(
			'period' => $period,
			'from' => $from,
			'to' => $to,
		));

		$def = $this->newDefinition('Báo cáo tổng quan', 'tong-quan');
		$def->subtitle = 'KPI kinh doanh';
		$def->addFilterLine('Khoảng thời gian', $dash['range']['label']);

		foreach ($dash['kpis'] as $kpi) {
			$def->addSummaryCard($kpi['label'], $kpi['value'], isset($kpi['format']) ? $kpi['format'] : 'text');
		}

		foreach ($dash['cost_kpis'] as $kpi) {
			$def->addSummaryCard($kpi['label'], $kpi['value'], isset($kpi['format']) ? $kpi['format'] : 'text');
		}

		$def->columns = array(
			array('key' => 'rank', 'label' => '#', 'align' => 'center', 'type' => 'int'),
			array('key' => 'name', 'label' => 'Sản phẩm', 'align' => 'left', 'type' => 'text'),
			array('key' => 'sold', 'label' => 'Đã bán', 'align' => 'center', 'type' => 'int'),
			array('key' => 'revenue', 'label' => 'Doanh thu', 'align' => 'right', 'type' => 'money'),
		);

		$rank = 0;
		foreach ($dash['top_products'] as $item) {
			$rank++;
			$def->rows[] = array(
				'rank' => $rank,
				'name' => isset($item->name) ? (string) $item->name : '',
				'sold' => isset($item->total_sold) ? (int) $item->total_sold : 0,
				'revenue' => isset($item->total_amount) ? (float) $item->total_amount : 0,
			);
		}

		if (empty($def->rows)) {
			$def->notes[] = 'Chưa có dữ liệu sản phẩm bán chạy trong kỳ đã chọn.';
		}

		return $def;
	}
}
