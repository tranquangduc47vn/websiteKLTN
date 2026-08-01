<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class RevenueExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'revenue';
	}

	public function buildDefinition()
	{
		$range = $this->_resolveReportRange();
		$where = $range['where'];
		$where_t = $range['where_t'];

		$def = $this->newDefinition('Báo cáo doanh thu', 'doanh-thu');
		$def->subtitle = 'Thống kê đơn hàng hoàn thành';

		foreach ($range['filter_lines'] as $line) {
			$def->addFilterLine($line['label'], $line['value']);
		}

		$db = $this->ci()->db;

		$sql = "
			SELECT COUNT(id) total_orders, SUM(amount) total_revenue
			FROM transaction
			WHERE {$where}
		";
		$result = $db->query($sql)->row();
		$total_orders = $result && $result->total_orders ? (int) $result->total_orders : 0;
		$total_revenue = $result && $result->total_revenue ? (float) $result->total_revenue : 0;

		$sql = "
			SELECT SUM(o.qty) total_qty
			FROM `order` o
			JOIN transaction t ON o.transaction_id = t.id
			WHERE {$where_t}
		";
		$result = $db->query($sql)->row();
		$total_qty = $result && $result->total_qty ? (int) $result->total_qty : 0;

		$def->addSummaryCard('Đơn hàng thành công', $total_orders, 'int');
		$def->addSummaryCard('Số lượng bán ra', $total_qty, 'int');
		$def->addSummaryCard('Doanh thu thực thu', $total_revenue, 'money');

		$def->columns = array(
			array('key' => 'month', 'label' => 'Tháng', 'align' => 'left', 'type' => 'text'),
			array('key' => 'revenue', 'label' => 'Doanh thu (VNĐ)', 'align' => 'right', 'type' => 'money'),
		);

		$year = date('Y');
		for ($m = 1; $m <= 12; $m++) {
			$month = str_pad($m, 2, '0', STR_PAD_LEFT);
			$start_month = strtotime("$year-$month-01 00:00:00");
			$end_month = strtotime(date('Y-m-t 23:59:59', strtotime("$year-$month-01")));

			$sql = "
				SELECT SUM(amount) revenue
				FROM transaction
				WHERE status = 3 AND created BETWEEN {$start_month} AND {$end_month}
			";
			$row = $db->query($sql)->row();
			$revenue = $row && $row->revenue ? (float) $row->revenue : 0;
			$def->rows[] = array(
				'month' => 'Tháng ' . $m . '/' . $year,
				'revenue' => $revenue,
			);
		}

		$def->addTotal('Tổng doanh thu năm ' . $year, array_sum(array_column($def->rows, 'revenue')), 'money');
		$def->notes[] = 'Biểu đồ theo tháng chỉ tính đơn hoàn thành (status = 3).';

		return $def;
	}

	protected function _resolveReportRange()
	{
		$type = $this->filter()->get('type');
		$date_from = trim((string) $this->filter()->get('date_from'));
		$date_to = trim((string) $this->filter()->get('date_to'));

		$where = 'status = 3';
		$start = 0;
		$end = 0;
		$filter_lines = array();

		if ($type !== '') {
			switch ($type) {
				case 'day':
					$start = strtotime(date('Y-m-d 00:00:00'));
					$end = strtotime(date('Y-m-d 23:59:59'));
					$filter_lines[] = array('label' => 'Kỳ', 'value' => 'Hôm nay');
					break;
				case 'week':
					$start = strtotime('monday this week');
					$end = strtotime('sunday this week 23:59:59');
					$filter_lines[] = array('label' => 'Kỳ', 'value' => 'Tuần này');
					break;
				case 'month':
					$start = strtotime(date('Y-m-01 00:00:00'));
					$end = strtotime(date('Y-m-t 23:59:59'));
					$filter_lines[] = array('label' => 'Kỳ', 'value' => 'Tháng này');
					break;
				case 'year':
					$start = strtotime(date('Y-01-01 00:00:00'));
					$end = strtotime(date('Y-12-31 23:59:59'));
					$filter_lines[] = array('label' => 'Kỳ', 'value' => 'Năm nay');
					break;
			}
		} else {
			if ($date_from !== '') {
				$start = strtotime($date_from . ' 00:00:00');
				$filter_lines[] = array('label' => 'Từ ngày', 'value' => $date_from);
			}
			if ($date_to !== '') {
				$end = strtotime($date_to . ' 23:59:59');
				$filter_lines[] = array('label' => 'Đến ngày', 'value' => $date_to);
			}
		}

		if ($start && $end) {
			$where .= " AND created BETWEEN {$start} AND {$end}";
		} elseif ($start) {
			$where .= " AND created >= {$start}";
		} elseif ($end) {
			$where .= " AND created <= {$end}";
		}

		$where_t = str_replace(
			array('status', 'created'),
			array('t.status', 't.created'),
			$where
		);

		return array(
			'where' => $where,
			'where_t' => $where_t,
			'filter_lines' => $filter_lines,
		);
	}
}
