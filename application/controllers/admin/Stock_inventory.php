<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @deprecated Legacy v1 inventory UI (product_inventory). Routes redirect to admin/inventory.
 */
class Stock_inventory extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
	}

	public function index()
	{
		redirect(admin_url('inventory'));
	}

	public function movements()
	{
		redirect(admin_url('stock-movements'));
	}

	public function _remap($method)
	{
		if ($method === 'index') {
			$this->index();
			return;
		}
		if ($method === 'movements') {
			$this->movements();
			return;
		}
		redirect(admin_url('inventory'));
	}
}
