<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ExportContext
{
	/** @var CI_Controller */
	public $ci;

	/** @var ExportFilter */
	public $filter;

	/** @var string */
	public $module;

	/** @var object|null */
	public $login;

	public function __construct($ci, $module, ExportFilter $filter, $login = null)
	{
		$this->ci = $ci;
		$this->module = (string) $module;
		$this->filter = $filter;
		$this->login = $login;
	}
}
