<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/ExportFilter.php';
require_once APPPATH . 'services/export/ExportContext.php';
require_once APPPATH . 'services/export/ExportReportDefinition.php';
require_once APPPATH . 'services/export/AbstractModuleExport.php';
require_once APPPATH . 'services/export/OrderExport.php';
require_once APPPATH . 'services/export/ProductExport.php';
require_once APPPATH . 'services/export/CustomerExport.php';
require_once APPPATH . 'services/export/InventoryExport.php';
require_once APPPATH . 'services/export/VoucherExport.php';
require_once APPPATH . 'services/export/RevenueExport.php';
require_once APPPATH . 'services/export/TopProductsExport.php';
require_once APPPATH . 'services/export/DashboardExport.php';
require_once APPPATH . 'services/export/MpdfReportWriter.php';
require_once APPPATH . 'services/export/ExcelReportWriter.php';

class ExportService
{
	protected static $modules = array(
		'orders' => 'OrderExport',
		'products' => 'ProductExport',
		'customers' => 'CustomerExport',
		'inventory' => 'InventoryExport',
		'voucher' => 'VoucherExport',
		'revenue' => 'RevenueExport',
		'top_products' => 'TopProductsExport',
		'dashboard' => 'DashboardExport',
	);

	public static function supportedModules()
	{
		return array_keys(self::$modules);
	}

	public static function isSupported($module)
	{
		return isset(self::$modules[$module]);
	}

	public static function buildDefinition($module, ExportFilter $filter, $login = null)
	{
		self::ensureAutoload();
		$exporter = self::createExporter($module, $filter, $login);
		return $exporter->buildDefinition();
	}

	public static function renderPdf($module, ExportFilter $filter, $login = null)
	{
		self::ensureAutoload();
		$def = self::buildDefinition($module, $filter, $login);
		$writer = new MpdfReportWriter(get_instance());
		return array(
			'definition' => $def,
			'content' => $writer->write($def),
		);
	}

	public static function renderExcel($module, ExportFilter $filter, $login = null)
	{
		self::ensureAutoload();
		$def = self::buildDefinition($module, $filter, $login);
		$writer = new ExcelReportWriter();
		return array(
			'definition' => $def,
			'content' => $writer->write($def),
		);
	}

	public static function renderPreviewHtml($module, ExportFilter $filter, $login = null)
	{
		$def = self::buildDefinition($module, $filter, $login);
		$ci = get_instance();
		return array(
			'definition' => $def,
			'content' => $ci->load->view('admin/export/report_document', array(
				'report' => $def,
				'preview' => true,
			), true),
		);
	}

	protected static function createExporter($module, ExportFilter $filter, $login = null)
	{
		if (!self::isSupported($module)) {
			show_error('Module export không hợp lệ.', 404);
		}
		$context = new ExportContext(get_instance(), $module, $filter, $login);
		$class = self::$modules[$module];
		return new $class($context);
	}

	public static function ensureAutoload()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$file = FCPATH . 'vendor/autoload.php';
		if (!is_file($file)) {
			show_error('Thiếu thư viện Composer. Chạy: composer require mpdf/mpdf phpoffice/phpspreadsheet', 500);
		}
		require_once $file;
		$loaded = true;
	}

	public static function filename($def, $ext)
	{
		$base = isset($def->filename_base) ? $def->filename_base : 'bao-cao';
		$date = date('Y-m-d');
		return $base . '-' . $date . '.' . ltrim($ext, '.');
	}

	public static function assertCanExport($module, $login = null)
	{
		$CI = get_instance();
		$CI->load->helper('export');
		if (!admin_export_can($login)) {
			show_error('Bạn không có quyền xuất báo cáo.', 403);
		}
		if (!admin_export_module_allowed($module, $login)) {
			show_error('Module export không được phép.', 403);
		}
	}
}
