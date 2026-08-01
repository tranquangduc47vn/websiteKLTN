<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class CustomerExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'customers';
	}

	public function buildDefinition()
	{
		$ci = $this->ci();
		$ci->load->model('user_model');

		$def = $this->newDefinition('Báo cáo khách hàng', 'khach-hang');

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'id', 'label' => 'Mã KH', 'align' => 'center', 'type' => 'int'),
			array('key' => 'name', 'label' => 'Họ tên', 'align' => 'left', 'type' => 'text'),
			array('key' => 'email', 'label' => 'Email', 'align' => 'left', 'type' => 'text'),
			array('key' => 'phone', 'label' => 'Điện thoại', 'align' => 'left', 'type' => 'text'),
			array('key' => 'address', 'label' => 'Địa chỉ', 'align' => 'left', 'type' => 'text'),
		);

		$rows = $ci->user_model->get_list(array('order' => array('id', 'DESC')));
		$stt = 0;
		foreach ($rows as $row) {
			$stt++;
			$def->rows[] = array(
				'stt' => $stt,
				'id' => (int) $row->id,
				'name' => (string) $row->name,
				'email' => (string) $row->email,
				'phone' => isset($row->phone) ? (string) $row->phone : '',
				'address' => (string) $row->address,
			);
		}

		$def->addTotal('Tổng khách hàng', count($def->rows), 'int');

		return $def;
	}
}
