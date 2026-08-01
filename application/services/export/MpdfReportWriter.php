<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Mpdf\Mpdf;

class MpdfReportWriter
{
	/** @var CI_Controller */
	protected $ci;

	public function __construct($ci = null)
	{
		$this->ci = $ci ? $ci : get_instance();
	}

	public function write(ExportReportDefinition $def)
	{
		$html = $this->ci->load->view('admin/export/report_document', array(
			'report' => $def,
			'preview' => false,
		), true);

		$landscape = $def->landscape || $def->columnCount() > 6;

		$config = array(
			'mode' => 'utf-8',
			'format' => $landscape ? 'A4-L' : 'A4',
			'default_font' => 'dejavusans',
			'margin_left' => 12,
			'margin_right' => 12,
			'margin_top' => 14,
			'margin_bottom' => 14,
			'tempDir' => $this->_tempDir(),
		);

		$mpdf = new Mpdf($config);
		$mpdf->SetTitle($def->title);
		$mpdf->SetAuthor($def->shop_name);
		$mpdf->WriteHTML($html);

		return $mpdf->Output('', 'S');
	}

	protected function _tempDir()
	{
		$dir = FCPATH . 'storage/temp/mpdf';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return $dir;
	}
}
