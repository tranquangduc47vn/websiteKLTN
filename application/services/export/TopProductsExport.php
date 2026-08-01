<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/RevenueExport.php';

class TopProductsExport extends RevenueExport
{
	public function moduleKey()
	{
		return 'top_products';
	}

	public function buildDefinition()
	{
		$range = $this->_resolveReportRange();
		$where_t = $range['where_t'];

		$def = $this->newDefinition('Báo cáo sản phẩm bán chạy', 'san-pham-ban-chay');
		$def->subtitle = 'Theo số lượng đã bán (đơn hoàn thành)';
		$def->landscape = true;

		foreach ($range['filter_lines'] as $line) {
			$def->addFilterLine($line['label'], $line['value']);
		}

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'id', 'label' => 'Mã SP', 'align' => 'center', 'type' => 'int'),
			array('key' => 'name', 'label' => 'Tên sản phẩm', 'align' => 'left', 'type' => 'text'),
			array('key' => 'price', 'label' => 'Giá bán', 'align' => 'right', 'type' => 'money'),
			array('key' => 'sold', 'label' => 'Đã bán', 'align' => 'center', 'type' => 'int'),
			array('key' => 'stock', 'label' => 'Tồn kho', 'align' => 'center', 'type' => 'int'),
			array('key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'type' => 'text'),
		);

		$sql = "
			SELECT
				p.id,
				p.name,
				p.quantity,
				(
					SELECT SUM(o.qty)
					FROM `order` o
					JOIN transaction t ON o.transaction_id = t.id
					WHERE o.product_id = p.id AND {$where_t}
				) da_ban,
				COALESCE(
					(
						SELECT o.amount / o.qty
						FROM `order` o
						JOIN transaction t ON o.transaction_id = t.id
						WHERE o.product_id = p.id AND {$where_t}
						LIMIT 1
					),
					p.price
				) price
			FROM product p
			ORDER BY da_ban DESC
		";

		$rows = $this->ci()->db->query($sql)->result();
		if ($this->filter()->isPageScope()) {
			list($limit, $offset) = $this->applyLimitOffset(9999);
			if ($limit !== null) {
				$rows = array_slice($rows, $offset, $limit);
			}
		}

		$stt = 0;
		$total_sold = 0;
		foreach ($rows as $row) {
			$sold = $row->da_ban ? (int) $row->da_ban : 0;
			if ($sold <= 0 && !$this->filter()->isPageScope()) {
				continue;
			}
			$stt++;
			$stock = isset($row->quantity) ? (int) $row->quantity : 0;
			if ($stock <= 0) {
				$status = 'Hết hàng';
			} elseif ($stock <= 5) {
				$status = 'Sắp hết';
			} else {
				$status = 'An toàn';
			}
			$total_sold += $sold;
			$def->rows[] = array(
				'stt' => $stt,
				'id' => (int) $row->id,
				'name' => (string) $row->name,
				'price' => (float) $row->price,
				'sold' => $sold,
				'stock' => $stock,
				'status' => $status,
			);
		}

		$def->addTotal('Tổng sản phẩm', count($def->rows), 'int');
		$def->addTotal('Tổng đã bán', $total_sold, 'int');

		return $def;
	}
}
