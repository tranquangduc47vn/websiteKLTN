<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class OrderExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'orders';
	}

	public function buildDefinition()
	{
		$ci = $this->ci();
		$ci->load->model('transaction_model');

		$q = trim((string) $this->filter()->get('q'));
		$status = $this->filter()->get('status');
		$payment = $this->filter()->get('payment');
		$date_from = trim((string) $this->filter()->get('date_from'));
		$date_to = trim((string) $this->filter()->get('date_to'));

		$def = $this->newDefinition('Báo cáo đơn hàng', 'don-hang');
		$def->landscape = true;

		if ($q !== '') {
			$def->addFilterLine('Tên / SĐT', $q);
		}
		if ($status !== null && $status !== '') {
			$def->addFilterLine('Trạng thái', $this->orderStatusLabel($status));
		}
		if ($payment !== '') {
			$def->addFilterLine('Thanh toán', $payment === 'transfer' ? 'Chuyển khoản' : 'COD');
		}
		if ($date_from !== '') {
			$def->addFilterLine('Từ ngày', $date_from);
		}
		if ($date_to !== '') {
			$def->addFilterLine('Đến ngày', $date_to);
		}

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'id', 'label' => 'Mã ĐH', 'align' => 'center', 'type' => 'int'),
			array('key' => 'user_name', 'label' => 'Khách hàng', 'align' => 'left', 'type' => 'text'),
			array('key' => 'user_phone', 'label' => 'SĐT', 'align' => 'left', 'type' => 'text'),
			array('key' => 'created', 'label' => 'Ngày đặt', 'align' => 'left', 'type' => 'text'),
			array('key' => 'amount', 'label' => 'Giá trị', 'align' => 'right', 'type' => 'money'),
			array('key' => 'payment', 'label' => 'Thanh toán', 'align' => 'center', 'type' => 'text'),
			array('key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'type' => 'text'),
		);

		$ci->db->reset_query();
		$this->_applyOrderFilters($q, $status, $payment, $date_from, $date_to);

		list($limit, $offset) = $this->applyLimitOffset(10);
		$input = array('order' => array('id', 'DESC'));
		if ($limit !== null) {
			$input['limit'] = array($limit, $offset);
		}
		$rows = $ci->transaction_model->get_list($input);

		$total_amount = 0;
		$stt = $offset !== null ? (int) $offset : 0;
		foreach ($rows as $row) {
			$stt++;
			$amount = (float) $row->amount;
			$total_amount += $amount;
			$def->rows[] = array(
				'stt' => $stt,
				'id' => (int) $row->id,
				'user_name' => (string) $row->user_name,
				'user_phone' => (string) $row->user_phone,
				'created' => $this->formatDateTime($row->created),
				'amount' => $amount,
				'payment' => $this->paymentLabel(isset($row->payment) ? $row->payment : ''),
				'status' => $this->orderStatusLabel($row->status),
			);
		}

		$def->addTotal('Số đơn', count($def->rows), 'int');
		$def->addTotal('Tổng giá trị', $total_amount, 'money');

		return $def;
	}

	protected function _applyOrderFilters($keyword, $status, $payment, $date_from, $date_to)
	{
		$db = $this->ci()->db;
		if ($keyword !== '') {
			$db->group_start();
			$db->like('user_name', $keyword);
			$db->or_like('user_phone', $keyword);
			$db->group_end();
		}
		if ($status !== null && $status !== '' && in_array((string) $status, array('0', '1', '2', '3', '4'), true)) {
			$db->where('status', (string) $status);
		}
		if ($payment === 'transfer') {
			$db->where('payment', 'Chuyển khoản');
		} elseif ($payment === 'cod') {
			$db->where("(payment IS NULL OR payment != 'Chuyển khoản')", null, false);
		}
		if ($date_from !== '') {
			$ts_from = strtotime($date_from . ' 00:00:00');
			if ($ts_from) {
				$db->where('created >=', $ts_from);
			}
		}
		if ($date_to !== '') {
			$ts_to = strtotime($date_to . ' 23:59:59');
			if ($ts_to) {
				$db->where('created <=', $ts_to);
			}
		}
	}
}
