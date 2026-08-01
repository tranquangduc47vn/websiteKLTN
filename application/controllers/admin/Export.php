<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/ExportService.php';

class Export extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('export');
	}

	public function excel($module = '')
	{
		$this->_dispatch($module, 'excel');
	}

	public function pdf($module = '')
	{
		$this->_dispatch($module, 'pdf');
	}

	public function preview($module = '')
	{
		$this->_dispatch($module, 'preview');
	}

	protected function _dispatch($module, $format)
	{
		$module = trim((string) $module);
		if ($module === '' || !ExportService::isSupported($module)) {
			show_404();
		}

		ExportService::assertCanExport($module, $this->session->userdata('login'));

		$filter = ExportFilter::fromInput($this->input->get(null, true));
		$login = $this->session->userdata('login');

		if ($format === 'preview') {
			$result = ExportService::renderPreviewHtml($module, $filter, $login);
			$this->output
				->set_content_type('text/html', 'utf-8')
				->set_output($result['content']);
			return;
		}

		if ($format === 'pdf') {
			$result = ExportService::renderPdf($module, $filter, $login);
			$filename = ExportService::filename($result['definition'], 'pdf');
			$this->output
				->set_content_type('application/pdf')
				->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
				->set_output($result['content']);
			return;
		}

		$result = ExportService::renderExcel($module, $filter, $login);
		$filename = ExportService::filename($result['definition'], 'xlsx');
		$this->output
			->set_content_type('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
			->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
			->set_output($result['content']);
	}
}
