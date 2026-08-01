<?php
defined('BASEPATH') OR exit('No direct script access allowed');

abstract class AbstractModuleExport
{
	/** @var ExportContext */
	protected $context;

	public function __construct(ExportContext $context)
	{
		$this->context = $context;
	}

	abstract public function moduleKey();

	abstract public function buildDefinition();

	protected function ci()
	{
		return $this->context->ci;
	}

	protected function filter()
	{
		return $this->context->filter;
	}

	protected function newDefinition($title, $filename_base = '')
	{
		$def = new ExportReportDefinition();
		$def->module = $this->moduleKey();
		$def->title = $title;
		$def->filename_base = $filename_base !== '' ? $filename_base : $this->moduleKey();
		$def->shop_name = function_exists('shop_name') ? shop_name() : 'Webshop';
		$def->generated_at = date('d/m/Y H:i');
		$def->scope_label = $this->filter()->isPageScope() ? 'Trang hiện tại' : 'Toàn bộ dữ liệu lọc';
		return $def;
	}

	protected function formatMoney($amount)
	{
		return number_format((float) $amount, 0, ',', '.') . ' ₫';
	}

	protected function formatDateTime($timestamp)
	{
		$ts = (int) $timestamp;
		if ($ts <= 0) {
			return '—';
		}
		return date('d/m/Y H:i', $ts);
	}

	protected function orderStatusLabel($status)
	{
		$map = array(
			'0' => 'Chờ xử lý',
			'1' => 'Đã xác nhận',
			'2' => 'Đang giao',
			'3' => 'Thành công',
			'4' => 'Đã hủy / Hoàn',
		);
		$key = (string) $status;
		return isset($map[$key]) ? $map[$key] : $key;
	}

	protected function paymentLabel($payment)
	{
		return (isset($payment) && $payment === 'Chuyển khoản') ? 'Chuyển khoản' : 'COD';
	}

	protected function applyLimitOffset($default_per_page = 0)
	{
		if (!$this->filter()->isPageScope()) {
			return array(null, null);
		}
		$per = $this->filter()->perPage();
		if ($per <= 0) {
			$per = (int) $default_per_page;
		}
		if ($per <= 0) {
			return array(null, null);
		}
		return array($per, $this->filter()->offset());
	}
}
