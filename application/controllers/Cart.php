<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cart extends MY_Frontend_Controller {
    
    public function __construct()
    {
        parent::__construct();
        $this->load->model('product_model');
        $this->load->model('product_variant_model');
        $this->load->library('product_service');
    }

    private function _check_login()
    {
        if (!$this->session->userdata('user')) {
            $this->session->set_userdata('redirect_back', current_url());
            set_auth_flash('fail', 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng.');
            redirect(build_login_url());
        }
    }

    private function _parse_product_variants($product)
    {
        $colors = array();
        $sizes = array();

        if (!empty($product->color)) {
            foreach (explode(',', $product->color) as $color) {
                $color = trim($color);
                if ($color !== '') {
                    $colors[] = $color;
                }
            }
        }
        if (empty($colors)) {
            $colors[] = 'Mặc định';
        }

        if (!empty($product->size)) {
            foreach (explode(',', $product->size) as $size) {
                $size = trim($size);
                if ($size !== '') {
                    $sizes[] = $size;
                }
            }
        }
        if (empty($sizes)) {
            $sizes[] = 'Freesize';
        }

        return array(
            'colors' => $colors,
            'sizes' => $sizes,
        );
    }

    private function _default_variant($product)
    {
        $variants = $this->_parse_product_variants($product);
        return array(
            'size' => $variants['sizes'][0],
            'color' => $variants['colors'][0],
        );
    }

    private function _normalize_variant($product, $size, $color)
    {
        $variants = $this->_parse_product_variants($product);
        $size = trim((string) $size);
        $color = trim((string) $color);

        if ($size === '' || !in_array($size, $variants['sizes'], true)) {
            $size = $variants['sizes'][0];
        }
        if ($color === '' || !in_array($color, $variants['colors'], true)) {
            $color = $variants['colors'][0];
        }

        return array(
            'size' => $size,
            'color' => $color,
            'variants' => $variants,
        );
    }

    private function _build_rowid($id, $size, $color)
    {
        return $id . '_' . md5($size . '_' . $color);
    }

    private function _enrich_cart_items($carts)
    {
        $items = array();
        foreach ($carts as $rowid => $item) {
            $product = $this->product_model->get_info($item['id']);
            if ($product) {
                $item['variants'] = $this->_parse_product_variants($product);
                $items[$rowid] = $item;
            }
        }
        return $items;
    }

    private function _recalculate_cart_prices($carts)
    {
        if (!is_array($carts) || empty($carts)) {
            return array(
                'cart' => array(),
                'removed' => array(),
                'changed' => FALSE,
            );
        }

        $clean = array();
        $removed = array();
        $changed = FALSE;

        foreach ($carts as $rowid => $item) {
            $product_id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($product_id <= 0) {
                $changed = TRUE;
                continue;
            }

            $product = $this->product_model->get_info($product_id);
            if (empty($product)) {
                $removed[] = !empty($item['name']) ? (string) $item['name'] : ('#' . $product_id);
                $changed = TRUE;
                continue;
            }

            $size = isset($item['options']['size']) ? (string) $item['options']['size'] : '';
            $color = isset($item['options']['color']) ? (string) $item['options']['color'] : '';
            $qty = max(1, (int) (isset($item['qty']) ? $item['qty'] : 1));

            $check = $this->product_service->validate_cart_quantity($product_id, $color, $size, $qty, 0, FALSE);
            if (!$check['ok']) {
                $removed[] = (string) $product->name;
                $changed = TRUE;
                continue;
            }

            $price = floatval($product->price);
            if ($product->discount > 0) {
                $price = floatval($product->price - $product->discount);
            }
            if ($price <= 0) {
                $price = 1000;
            }

            $subtotal = $price * $qty;
            if (!isset($item['options']) || !is_array($item['options'])) {
                $item['options'] = array();
            }

            $item['price'] = $price;
            $item['qty'] = $qty;
            $item['subtotal'] = $subtotal;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['name'] = $product->name;
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];
            if (empty($item['options']['image_link']) && !empty($product->image_link)) {
                $item['options']['image_link'] = $product->image_link;
            }

            if (!isset($carts[$rowid])
                || (float) $carts[$rowid]['price'] !== $price
                || (float) $carts[$rowid]['subtotal'] !== $subtotal
                || (int) $carts[$rowid]['variant_id'] !== (int) $check['variant_id']) {
                $changed = TRUE;
            }

            $clean[$rowid] = $item;
        }

        if (count($clean) !== count($carts)) {
            $changed = TRUE;
        }

        return array(
            'cart' => $clean,
            'removed' => $removed,
            'changed' => $changed,
        );
	}

	private function _persist_cart(array $carts)
	{
		$this->load->helper('cart');
		cart_persist_session($carts);
	}

    private function _sanitize_cart_session()
    {
        $carts = $this->session->userdata('custom_cart');
        if (!is_array($carts) || empty($carts)) {
            return;
        }

        $items = $this->_enrich_cart_items($carts);
        if (count($items) === count($carts)) {
            return;
        }

        $removed = count($carts) - count($items);
        $this->_persist_cart($items);
        set_store_flash(
            'fail',
            $removed . ' sản phẩm trong giỏ không còn bán và đã được xóa tự động.'
        );
    }

    private function _wants_json()
    {
        return $this->input->is_ajax_request();
    }

    private function _json_response($payload, $status = 200)
    {
        if ($this->config->item('csrf_protection') === TRUE) {
            $payload = array_merge($payload, csrf_json_meta());
        }
        $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function _cart_totals($carts)
    {
        $total_items = 0;
        $total_price = 0;
        foreach ((array) $carts as $item) {
            $total_items += (int) $item['qty'];
            $total_price += (float) $item['subtotal'];
        }
        return array(
            'total_items' => $total_items,
            'total_price' => $total_price,
        );
    }

    private function _cart_success_payload($carts, $message = '', $extra = array())
    {
        $totals = $this->_cart_totals($carts);
        return array_merge(array(
            'ok' => true,
            'message' => $message,
            'total_items' => $totals['total_items'],
            'total_price' => $totals['total_price'],
        ), $extra);
    }

    private function _cart_stock_notice_payload($extra)
    {
        if (!isset($extra['stock'])) {
            return null;
        }

        $payload = array(
            'product_name' => isset($extra['product_name']) ? (string) $extra['product_name'] : '',
            'size' => isset($extra['size']) ? (string) $extra['size'] : '',
            'color' => isset($extra['color']) ? (string) $extra['color'] : '',
            'stock' => (int) $extra['stock'],
        );

        if (isset($extra['product_id'])) {
            $payload['product_id'] = (int) $extra['product_id'];
        }
        if (!empty($extra['rowid'])) {
            $payload['rowid'] = (string) $extra['rowid'];
        }

        return $payload;
    }

    private function _stock_notice_applies_to_cart($notice, $carts)
    {
        if (!is_array($notice) || empty($carts) || !is_array($carts)) {
            return false;
        }

        if (!empty($notice['rowid']) && isset($carts[$notice['rowid']])) {
            return true;
        }

        if (empty($notice['product_id'])) {
            return false;
        }

        $product_id = (int) $notice['product_id'];
        $notice_size = isset($notice['size']) ? (string) $notice['size'] : '';
        $notice_color = isset($notice['color']) ? (string) $notice['color'] : '';

        foreach ($carts as $item) {
            $item_id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($item_id !== $product_id) {
                continue;
            }

            if ($notice_size !== '' || $notice_color !== '') {
                $item_size = isset($item['options']['size']) ? (string) $item['options']['size'] : '';
                $item_color = isset($item['options']['color']) ? (string) $item['options']['color'] : '';
                if ($notice_size !== '' && $notice_size !== $item_size) {
                    continue;
                }
                if ($notice_color !== '' && $notice_color !== $item_color) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    private function _cart_fail($message, $extra = array())
    {
        if ($this->_wants_json()) {
            $carts = $this->session->userdata('custom_cart');
            if (!is_array($carts)) {
                $carts = array();
            }
            $this->_json_response(array_merge(array(
                'ok' => false,
                'message' => $message,
            ), $this->_cart_totals($carts), $extra), 200);
            return true;
        }

        $this->load->helper('common');
        set_store_flash('fail', $message);
        redirect(build_cart_url());
        return true;
    }

    private function _cart_finish($payload)
    {
        if ($this->_wants_json()) {
            $this->_json_response($payload);
            return;
        }
        if (!empty($payload['message'])) {
            $type = (isset($payload['ok']) && $payload['ok'] === false) ? 'fail' : 'success';
            $this->load->helper('common');
            set_store_flash($type, $payload['message']);
        }
        redirect(build_cart_url());
    }

    private function _require_login_for_cart()
    {
        if ($this->session->userdata('user')) {
            return true;
        }
        if ($this->_wants_json()) {
            $this->_json_response(array(
                'ok' => false,
                'message' => 'Bạn cần đăng nhập tài khoản để thực hiện chức năng này!',
                'login_required' => true,
            ), 401);
            return false;
        }
        $this->session->set_userdata('redirect_back', current_url());
        $this->load->helper('common');
        set_store_flash('fail', 'Bạn cần đăng nhập tài khoản để thực hiện chức năng này!');
        redirect(build_login_url());
        return false;
    }

    public function index()
    {
        $this->_check_login();

        $this->load->helper('common');
        load_store_flash_to($this->data);
        // Cart never auto-shows stock_notice from flash (stale notices from failed add elsewhere).
        $this->session->flashdata('stock_notice');

        $carts = $this->session->userdata('custom_cart');
        if (!is_array($carts)) { 
            $carts = array(); 
        }

        $this->_sanitize_cart_session();
        $carts = $this->session->userdata('custom_cart');
        if (!is_array($carts)) {
            $carts = array();
        }

        $recalc = $this->_recalculate_cart_prices($carts);
        $carts = $recalc['cart'];
        if ($recalc['changed']) {
            $this->_persist_cart($carts);
        }
        if (!empty($recalc['removed'])) {
            set_store_flash(
                'fail',
                'Một số sản phẩm đã được cập nhật giá hoặc không còn khả dụng: ' . implode(', ', $recalc['removed'])
            );
        }
        
        $this->data['carts'] = $this->_enrich_cart_items($carts);

        $total_items = 0;
        foreach ($carts as $item) {
            $total_items += $item['qty'];
        }
        $this->data['total_items'] = $total_items;
        
        $this->render_frontend_sub('site/cart/index');
    }

    public function add()
    {
        $this->_check_login();

        $id = $this->input->post('id');
        $qty = $this->input->post('qty');
        $size = $this->input->post('size');
        $color = $this->input->post('color');

        if (empty($id)) {
            $id = $this->uri->rsegment(3);
        }

        $id = intval($id);
        $qty = intval($qty);
        if ($qty <= 0) { $qty = 1; }

        $product = $this->product_model->get_info($id);
        if (empty($product)) {
            set_store_flash('fail', 'Sản phẩm không tồn tại trong hệ thống!');
            redirect(base_url());
        }

        $variant = $this->_normalize_variant($product, $size, $color);
        $size = $variant['size'];
        $color = $variant['color'];

        $current_cart = $this->session->userdata('custom_cart');
        if (!is_array($current_cart)) {
            $current_cart = array();
        }

        $rowid = $this->_build_rowid($id, $size, $color);
        $existing_qty = isset($current_cart[$rowid]) ? (int) $current_cart[$rowid]['qty'] : 0;

        $check = $this->product_service->validate_cart_quantity($id, $color, $size, $qty, $existing_qty, false);
        if (!$check['ok']) {
            set_product_flash($check['message']);
            redirect(build_product_url($product));
        }

        $variant_id = (int) $check['variant_id'];
        $size = $check['size'];
        $color = $check['color'];

        $price = floatval($product->price);
        if ($product->discount > 0) {
            $price = floatval($product->price - $product->discount);
        }
        if ($price <= 0) { $price = 1000; } 

        $rowid = $this->_build_rowid($id, $size, $color);

        $item = array(
            'rowid'    => $rowid,
            'id'       => $id,
            'variant_id' => $variant_id,
            'qty'      => $qty,
            'price'    => $price,
            'name'     => $product->name,
            'subtotal' => $price * $qty,
            'options'  => array(
                'image_link' => !empty($product->image_link) ? $product->image_link : 'no-image.png',
                'size'       => $size,
                'color'      => $color
            )
        );

        if (isset($current_cart[$rowid])) {
            $current_cart[$rowid]['qty'] += $qty;
            $current_cart[$rowid]['variant_id'] = $variant_id;
            $current_cart[$rowid]['subtotal'] = $current_cart[$rowid]['qty'] * $price;
            $current_cart[$rowid]['options']['size'] = $size;
            $current_cart[$rowid]['options']['color'] = $color;
        } else {
            $current_cart[$rowid] = $item;
        }

        $this->_persist_cart($current_cart);
        set_store_flash('success', 'Thêm vào giỏ hàng thành công');
        
        redirect(build_cart_url());
    }

    public function update_options()
    {
        if (!$this->_require_login_for_cart()) {
            return;
        }

        $rowid = trim((string) $this->input->post('rowid'));
        $size = $this->input->post('size');
        $color = $this->input->post('color');

        if ($rowid === '') {
            $this->_cart_fail('Không tìm thấy sản phẩm trong giỏ hàng');
            return;
        }

        $current_cart = $this->session->userdata('custom_cart');
        if (!is_array($current_cart) || !isset($current_cart[$rowid])) {
            $this->_cart_fail('Không tìm thấy sản phẩm trong giỏ hàng');
            return;
        }

        $item = $current_cart[$rowid];
        $product = $this->product_model->get_info($item['id']);
        if (empty($product)) {
            $this->_cart_fail('Sản phẩm không còn tồn tại');
            return;
        }

        $variant = $this->_normalize_variant($product, $size, $color);
        $size = $variant['size'];
        $color = $variant['color'];
        $new_rowid = $this->_build_rowid($item['id'], $size, $color);

        if ($new_rowid === $rowid) {
            $this->_cart_finish($this->_cart_success_payload($current_cart, '', array(
                'action' => 'options',
                'old_rowid' => $rowid,
                'rowid' => $rowid,
                'qty' => (int) $item['qty'],
                'subtotal' => (float) $item['subtotal'],
                'size' => $size,
                'color' => $color,
                'product_name' => (string) $product->name,
            )));
            return;
        }

        $moving_qty = (int) $item['qty'];
        $existing_target_qty = isset($current_cart[$new_rowid]) ? (int) $current_cart[$new_rowid]['qty'] : 0;
        $check = $this->product_service->validate_cart_quantity($item['id'], $color, $size, $moving_qty, $existing_target_qty, false);
        if (!$check['ok']) {
            $this->_cart_fail($check['message'], array(
                'action' => 'options',
                'rowid' => $rowid,
                'product_id' => (int) $item['id'],
                'product_name' => (string) $product->name,
                'size' => $check['size'],
                'color' => $check['color'],
                'stock' => isset($check['stock']) ? (int) $check['stock'] : 0,
            ));
            return;
        }

        if (isset($current_cart[$new_rowid])) {
            $merged_qty = $current_cart[$new_rowid]['qty'] + $moving_qty;
            $current_cart[$new_rowid]['qty'] = $merged_qty;
            $current_cart[$new_rowid]['variant_id'] = (int) $check['variant_id'];
            $current_cart[$new_rowid]['subtotal'] = $merged_qty * $current_cart[$new_rowid]['price'];
            $current_cart[$new_rowid]['options']['size'] = $check['size'];
            $current_cart[$new_rowid]['options']['color'] = $check['color'];
            unset($current_cart[$rowid]);
            $subtotal = (float) $current_cart[$new_rowid]['subtotal'];
            $qty = (int) $merged_qty;
        } else {
            unset($current_cart[$rowid]);
            $item['rowid'] = $new_rowid;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];
            $current_cart[$new_rowid] = $item;
            $subtotal = (float) $item['subtotal'];
            $qty = (int) $item['qty'];
        }

        $this->_persist_cart($current_cart);
        $this->_cart_finish($this->_cart_success_payload($current_cart, 'Đã cập nhật size và màu sắc', array(
            'action' => 'options',
            'old_rowid' => $rowid,
            'rowid' => $new_rowid,
            'qty' => $qty,
            'subtotal' => $subtotal,
            'size' => $check['size'],
            'color' => $check['color'],
            'product_name' => (string) $product->name,
        )));
    }

    public function update()
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        if (!$this->_require_login_for_cart()) {
            return;
        }

        $rowid = trim((string) $this->input->post('rowid'));
        $action = trim((string) $this->input->post('action'));

        if ($rowid === '' || $action === '') {
            $this->_cart_fail('Yêu cầu không hợp lệ');
            return;
        }
        
        $current_cart = $this->session->userdata('custom_cart');
        if (!is_array($current_cart)) { 
            $current_cart = array(); 
        }

        if (!isset($current_cart[$rowid])) {
            $this->_cart_fail('Không tìm thấy sản phẩm trong giỏ hàng');
            return;
        }

        $item = $current_cart[$rowid];
        $product = $this->product_model->get_info($item['id']);
        $product_name = $product ? (string) $product->name : (string) $item['name'];
        $removed = false;

        if ($action == 'sum') {
            $color = isset($item['options']['color']) ? $item['options']['color'] : '';
            $size = isset($item['options']['size']) ? $item['options']['size'] : '';
            $check = $this->product_service->validate_cart_quantity(
                $item['id'],
                $color,
                $size,
                1,
                (int) $item['qty'],
                false
            );
            if (!$check['ok']) {
                $this->_cart_fail($check['message'], array(
                    'action' => 'qty',
                    'rowid' => $rowid,
                    'product_id' => (int) $item['id'],
                    'product_name' => $product_name,
                    'size' => $check['size'],
                    'color' => $check['color'],
                    'stock' => isset($check['stock']) ? (int) $check['stock'] : 0,
                ));
                return;
            }

            $current_cart[$rowid]['qty'] += 1;
            $current_cart[$rowid]['variant_id'] = (int) $check['variant_id'];
            $current_cart[$rowid]['subtotal'] = $current_cart[$rowid]['qty'] * $current_cart[$rowid]['price'];
        } elseif ($action == 'sub') {
            if ($current_cart[$rowid]['qty'] > 1) {
                $current_cart[$rowid]['qty'] -= 1;
                $current_cart[$rowid]['subtotal'] = $current_cart[$rowid]['qty'] * $current_cart[$rowid]['price'];
            } else {
                unset($current_cart[$rowid]);
                $removed = true;
            }
        }

        $this->_persist_cart($current_cart);

        if ($removed) {
            $this->_cart_finish($this->_cart_success_payload($current_cart, '', array(
                'action' => 'qty',
                'rowid' => $rowid,
                'removed' => true,
            )));
            return;
        }

        $item = $current_cart[$rowid];
        $this->_cart_finish($this->_cart_success_payload($current_cart, '', array(
            'action' => 'qty',
            'rowid' => $rowid,
            'removed' => false,
            'qty' => (int) $item['qty'],
            'subtotal' => (float) $item['subtotal'],
            'product_name' => $product_name,
            'size' => isset($item['options']['size']) ? $item['options']['size'] : '',
            'color' => isset($item['options']['color']) ? $item['options']['color'] : '',
        )));
    }

    public function del()
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        if (!$this->_require_login_for_cart()) {
            return;
        }

        $rowid = trim((string) $this->input->post('rowid'));

        $current_cart = $this->session->userdata('custom_cart');
        if (!is_array($current_cart)) { 
            $current_cart = array(); 
        }

        $message = '';
        if (!empty($rowid) && $rowid !== '0' && $rowid !== 'del') {
            if (isset($current_cart[$rowid])) {
                unset($current_cart[$rowid]);
                $this->_persist_cart($current_cart);
                $message = 'Xóa sản phẩm thành công';
            }
        } else {
            $this->_persist_cart(array());
            $current_cart = array();
            $message = 'Xóa giỏ hàng thành công';
        }

        $this->_cart_finish($this->_cart_success_payload($current_cart, $message, array(
            'action' => 'delete',
            'rowid' => $rowid,
            'removed' => true,
            'empty' => empty($current_cart),
        )));
    }
}
