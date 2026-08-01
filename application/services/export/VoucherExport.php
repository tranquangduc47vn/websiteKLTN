<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class VoucherExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'voucher';
	}

	public function buildDefinition()
	{
		$ci = $this->ci();
		$ci->load->model('voucher_model');
		$ci->load->helper('loyalty');

		$def = $this->newDefinition('Báo cáo voucher', 'voucher');
		$def->landscape = true;

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'code', 'label' => 'Mã', 'align' => 'left', 'type' => 'text'),
			array('key' => 'name', 'label' => 'Tên', 'align' => 'left', 'type' => 'text'),
			array('key' => 'discount', 'label' => 'Giảm', 'align' => 'left', 'type' => 'text'),
			array('key' => 'tier_min', 'label' => 'Hạng tối thiểu', 'align' => 'left', 'type' => 'text'),
			array('key' => 'used', 'label' => 'Đã dùng', 'align' => 'center', 'type' => 'text'),
			array('key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'type' => 'text'),
			array('key' => 'valid', 'label' => 'Hiệu lực', 'align' => 'left', 'type' => 'text'),
		);

		$rows = $ci->voucher_model->get_list(array('order' => array('id', 'DESC')));
		$stt = 0;
		foreach ($rows as $row) {
			$stt++;
			$disc = ($row->discount_type === 'percent')
				? (int) $row->discount_value . '%'
				: $this->formatMoney((int) $row->discount_value);
			$used = (int) $row->used_count;
			$limit = (int) $row->usage_limit;
			$used_txt = $limit > 0 ? $used . ' / ' . $limit : (string) $used;

			$valid_from = (int) $row->valid_from;
			$valid_to = (int) $row->valid_to;
			$valid = '';
			if ($valid_from > 0) {
				$valid .= 'Từ ' . date('d/m/Y', $valid_from);
			}
			if ($valid_to > 0) {
				$valid .= ($valid !== '' ? ' · ' : '') . 'Đến ' . date('d/m/Y', $valid_to);
			}
			if ($valid === '') {
				$valid = 'Không giới hạn';
			}

			$def->rows[] = array(
				'stt' => $stt,
				'code' => (string) $row->code,
				'name' => (string) $row->name,
				'discount' => $disc,
				'tier_min' => loyalty_tier_label($row->tier_min),
				'used' => $used_txt,
				'status' => ((int) $row->is_active === 1) ? 'Đang bật' : 'Tắt',
				'valid' => $valid,
			);
		}

		$def->addTotal('Số voucher', count($def->rows), 'int');

		return $def;
	}
}
