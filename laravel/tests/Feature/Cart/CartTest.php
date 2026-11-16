<?php

namespace Tests\Feature\Cart;

use App\Models\Product;
use App\Support\Storefront\Urls;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * B5 — Storefront cart: đối chiếu hành vi CI3 application/controllers/Cart.php.
 */
class CartTest extends CartTestCase
{
    /* ---------------------------------------------------------------
     | A. Routes
     |--------------------------------------------------------------*/

    public function test_cart_routes_keep_ci3_urls_methods_and_actions(): void
    {
        $expected = [
            'gio-hang' => [['GET', 'HEAD'], 'CartController@index'],
            'gio-hang/them/{id}' => [['GET', 'POST', 'HEAD'], 'CartController@add'],
            'gio-hang/add/{id}' => [['GET', 'POST', 'HEAD'], 'CartController@add'],
            'gio-hang/cap-nhat-thuoc-tinh' => [['GET', 'POST', 'HEAD'], 'CartController@updateOptions'],
            'gio-hang/update' => [['GET', 'POST', 'HEAD'], 'CartController@update'],
            'gio-hang/update/{rowid}' => [['GET', 'POST', 'HEAD'], 'CartController@update'],
            'gio-hang/del' => [['GET', 'POST', 'HEAD'], 'CartController@del'],
            'gio-hang/del/{rowid}' => [['GET', 'POST', 'HEAD'], 'CartController@del'],
            'gio-hang/{path}' => [['GET', 'POST', 'HEAD'], 'CartController@handlePath'],
            'cart' => [['GET', 'POST', 'HEAD'], 'LegacyController@cart'],
            'cart/{path}' => [['GET', 'POST', 'HEAD'], 'LegacyController@cartPath'],
        ];

        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($r) => $r->uri());

        foreach ($expected as $uri => [$methods, $action]) {
            $this->assertTrue($routes->has($uri), "Thiếu route {$uri}");
            $route = $routes->get($uri);
            $this->assertEqualsCanonicalizing($methods, $route->methods(), "Sai method {$uri}");
            $this->assertStringEndsWith($action, $route->getActionName(), "Sai action {$uri}");
            $this->assertContains('web', $route->gatherMiddleware(), "{$uri} phải chạy trong group web (session + CSRF)");
        }

        // Catch-all danh mục {slug} không được bắt /gio-hang.
        $this->assertSame('storefront.cart.index', app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/gio-hang')
        )->getName());
    }

    /* ---------------------------------------------------------------
     | B. Khách chưa đăng nhập (CI3: cart bắt buộc đăng nhập)
     |--------------------------------------------------------------*/

    public function test_guest_viewing_cart_is_redirected_to_login(): void
    {
        $this->get('/gio-hang')
            ->assertRedirect(Urls::login())
            ->assertSessionHas('message_fail', 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng.')
            ->assertSessionHas('redirect_back', url('gio-hang'));
    }

    public function test_guest_add_is_redirected_to_login_without_touching_session_cart(): void
    {
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1])
            ->assertRedirect(Urls::login())
            ->assertSessionHas('redirect_back', url('gio-hang'))
            ->assertSessionHas('pending_cart_add.id', $product->id)
            ->assertSessionHas('pending_cart_add.size', 'S')
            ->assertSessionHas('pending_cart_add.color', 'Đen');

        $this->assertSame([], $this->sessionCart());
    }

    public function test_guest_ajax_cart_mutation_gets_401_login_required_json(): void
    {
        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => 'x', 'action' => 'sum'])
            ->assertStatus(401)
            ->assertJson([
                'ok' => false,
                'message' => 'Bạn cần đăng nhập tài khoản để thực hiện chức năng này!',
                'login_required' => true,
                'csrf_name' => '_token',
            ]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => 'x'])
            ->assertStatus(401)->assertJson(['login_required' => true]);
        $this->withHeaders(self::AJAX)->post('/gio-hang/del', ['rowid' => 'x'])
            ->assertStatus(401)->assertJson(['login_required' => true]);
    }

    public function test_guest_form_cart_mutation_redirects_to_login_with_store_flash(): void
    {
        $this->post('/gio-hang/del', ['rowid' => 'x'])
            ->assertRedirect(Urls::login())
            ->assertSessionHas('store_message_fail', 'Bạn cần đăng nhập tài khoản để thực hiện chức năng này!');
    }

    public function test_stale_session_cart_of_guest_is_cleared_on_storefront_pages(): void
    {
        $this->withSession(['custom_cart' => ['x' => ['id' => 1, 'qty' => 1]]])
            ->get('/')
            ->assertOk();

        $this->assertNull(session('custom_cart'));
    }

    public function test_update_and_del_reject_get_with_404_like_ci3(): void
    {
        $this->get('/gio-hang/update')->assertNotFound();
        $this->get('/gio-hang/del')->assertNotFound();
        $this->get('/gio-hang/cap-nhat-thuoc-tinh')->assertNotFound();

        $this->actingAs($this->makeUser(), 'web');
        $this->get('/gio-hang/update')->assertNotFound();
        $this->get('/gio-hang/del/abc')->assertNotFound();
        $this->get('/gio-hang/cap-nhat-thuoc-tinh')->assertNotFound();
    }

    /* ---------------------------------------------------------------
     | C. Product detail → add → cart (end-to-end)
     |--------------------------------------------------------------*/

    public function test_product_detail_add_to_cart_end_to_end(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $this->actingAs($user, 'web');

        $this->get(Urls::product($product))
            ->assertOk()
            ->assertSee('action="' . url('gio-hang/them/' . $product->id) . '"', false)
            ->assertSee('id="qdStockNoticeModal"', false);

        $this->addToCart($product, ['id' => $product->id, 'size' => 'S', 'color' => 'Đen', 'qty' => 2])
            ->assertRedirect(Urls::cart())
            ->assertSessionHas('store_message_success', 'Thêm vào giỏ hàng thành công');

        $rowid = $this->rowId($product, 'S', 'Đen');
        $expectedItem = [
            'rowid' => $rowid,
            'id' => $product->id,
            'variant_id' => $this->variantId($product, 'Đen', 'S'),
            'qty' => 2,
            'price' => 250000.0,
            'name' => $product->name,
            'subtotal' => 500000.0,
            'options' => ['image_link' => 'b5-test.jpg', 'size' => 'S', 'color' => 'Đen'],
        ];
        $this->assertSame([$rowid => $expectedItem], $this->sessionCart());
        $this->assertSame(array_keys($expectedItem), array_keys($this->sessionCart()[$rowid]), 'Thứ tự key phải giống CI3');

        // cart_persist_session(): đã đăng nhập thì đồng bộ user_saved_cart.
        $saved = $this->savedCart($user);
        $this->assertSame($rowid, array_key_first($saved));
        $this->assertSame(2, $saved[$rowid]['qty']);
        $this->assertEquals(500000, $saved[$rowid]['subtotal']);

        $page = $this->get('/gio-hang')->assertOk();
        $page->assertSee('jm-cart-lux', false)
            ->assertSee('data-rowid="' . $rowid . '"', false)
            ->assertSee('500.000 ₫')
            ->assertSee('2 sản phẩm')
            ->assertSee('jm-cart-flash jm-cart-flash--success', false)
            ->assertSee('Thêm vào giỏ hàng thành công')
            ->assertSee('alert alert-success', false)
            ->assertSee('<span class="jm-icon-dot wf-chrome-cart-badge">2</span>', false)
            ->assertDontSee('col-xs-12 col-sm-3 col-md-3 col-lg-3 clearpaddingl', false);

        // Refresh: không còn flash, giỏ giữ nguyên.
        $this->get('/gio-hang')->assertOk()
            ->assertDontSee('Thêm vào giỏ hàng thành công')
            ->assertSee('500.000 ₫');
        $this->assertSame([$rowid => $expectedItem], $this->sessionCart());
    }

    public function test_get_add_does_not_mutate_cart_and_post_adds_default_variant(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->get('/gio-hang/them/' . $product->id)->assertNotFound();
        $this->get('/gio-hang/add/' . $product->id)->assertNotFound();
        $this->assertSame([], $this->sessionCart());

        $card = view('site.partials.product-card', ['value' => $product])->render();
        $this->assertStringContainsString('method="post"', $card);
        $this->assertStringContainsString(url('gio-hang/them/' . $product->id), $card);
        $this->assertStringNotContainsString('<a href="' . url('gio-hang/them/' . $product->id), $card);

        $this->post('/gio-hang/them/' . $product->id)->assertRedirect(Urls::cart());
        $rowid = $this->rowId($product, 'S', 'Đen');
        $this->assertSame(1, $this->sessionCart()[$rowid]['qty']);
    }

    public function test_same_options_merge_and_different_options_split_rows(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->addToCart($product, ['size' => 'M', 'color' => 'Đen', 'qty' => 1]);

        $cart = $this->sessionCart();
        $this->assertCount(2, $cart);
        $this->assertSame(3, $cart[$this->rowId($product, 'S', 'Đen')]['qty']);
        $this->assertEquals(750000, $cart[$this->rowId($product, 'S', 'Đen')]['subtotal']);
        $this->assertSame(1, $cart[$this->rowId($product, 'M', 'Đen')]['qty']);
        $this->assertSame($this->variantId($product, 'Đen', 'M'), $cart[$this->rowId($product, 'M', 'Đen')]['variant_id']);
    }

    public function test_unknown_options_fall_back_to_first_choice(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'XXL', 'color' => '<script>', 'qty' => 1])->assertRedirect(Urls::cart());

        $this->assertSame([$this->rowId($product, 'S', 'Đen')], array_keys($this->sessionCart()));
    }

    /* ---------------------------------------------------------------
     | E. Quantity / stock / invalid input
     |--------------------------------------------------------------*/

    public function test_invalid_quantity_is_coerced_to_one_like_ci3(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 0]);
        $this->assertSame(1, $this->sessionCart()[$rowid]['qty']);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => -7]);
        $this->assertSame(2, $this->sessionCart()[$rowid]['qty']);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 'abc']);
        $this->assertSame(3, $this->sessionCart()[$rowid]['qty']);

        $this->addToCart($product, ['size' => ['S'], 'color' => ['x' => 'Đen'], 'qty' => ['1']]);
        $this->assertSame(4, $this->sessionCart()[$rowid]['qty']);
    }

    public function test_add_over_stock_is_rejected_and_redirects_to_product(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 4]);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2])
            ->assertRedirect(Urls::product($product))
            ->assertSessionHas('product_message', $product->name . ' size S màu Đen chỉ còn 5 sản phẩm trong kho.');

        $this->assertSame(4, $this->sessionCart()[$this->rowId($product, 'S', 'Đen')]['qty']);
    }

    public function test_out_of_stock_and_inactive_variants_are_rejected(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Trắng'])
            ->assertRedirect(Urls::product($product))
            ->assertSessionHas('product_message', $product->name . ' size S màu Trắng chỉ còn 0 sản phẩm trong kho.');

        $this->addToCart($product, ['size' => 'M', 'color' => 'Trắng'])
            ->assertRedirect(Urls::product($product))
            ->assertSessionHas('product_message', 'Biến thể sản phẩm không tồn tại hoặc đã ngừng kinh doanh.');

        $this->assertSame([], $this->sessionCart());
    }

    public function test_missing_and_inactive_products_redirect_home(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $inactive = $this->standardProduct(['status' => 0]);
        $missingId = (int) DB::table('products')->max('id') + 1000;

        $this->post('/gio-hang/them/' . $missingId)
            ->assertRedirect(url('/'))
            ->assertSessionHas('store_message_fail', 'Sản phẩm không tồn tại trong hệ thống!');

        $this->addToCart($inactive)
            ->assertRedirect(url('/'))
            ->assertSessionHas('store_message_fail', 'Sản phẩm này hiện không còn bán.');

        $this->assertSame([], $this->sessionCart());
    }

    public function test_client_supplied_price_and_subtotal_are_ignored(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1, 'price' => 1, 'subtotal' => 1, 'variant_id' => 999999]);

        $item = $this->sessionCart()[$this->rowId($product, 'S', 'Đen')];
        $this->assertSame(250000.0, $item['price']);
        $this->assertSame(250000.0, $item['subtotal']);
        $this->assertSame($this->variantId($product, 'Đen', 'S'), $item['variant_id']);
    }

    public function test_price_falls_back_to_1000_when_discount_exceeds_price(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->makeProduct(['price' => 100000, 'discount' => 150000], [['Đen', 'S', 3]]);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);

        $this->assertSame(0.0, $this->sessionCart()[$this->rowId($product, 'S', 'Đen')]['price']);
    }

    public function test_product_without_declared_options_respects_stock_on_repeat_add(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->makeProduct(['color' => null, 'size' => null], [['', '', 2]]);
        $rowid = $this->rowId($product, '', '');

        $this->addToCart($product, ['size' => 'Freesize', 'color' => 'Mặc định', 'qty' => 2])->assertRedirect(Urls::cart());
        $this->assertSame(['size' => '', 'color' => ''], array_intersect_key($this->sessionCart()[$rowid]['options'], ['size' => 1, 'color' => 1]));

        $this->addToCart($product, ['size' => 'Freesize', 'color' => 'Mặc định', 'qty' => 1])
            ->assertRedirect(Urls::product($product));
        $this->assertSame(2, $this->sessionCart()[$rowid]['qty']);
    }

    public function test_ajax_increase_decrease_and_remove_last_unit(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => $rowid, 'action' => 'sum'])
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
                'message' => '',
                'total_items' => 2,
                'total_price' => 500000,
                'action' => 'qty',
                'rowid' => $rowid,
                'removed' => false,
                'qty' => 2,
                'subtotal' => 500000,
                'product_name' => $product->name,
                'size' => 'S',
                'color' => 'Đen',
                'csrf_name' => '_token',
                'csrf_hash' => csrf_token(),
            ]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => $rowid, 'action' => 'sub'])
            ->assertOk()->assertJson(['ok' => true, 'qty' => 1, 'total_items' => 1, 'total_price' => 250000]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => $rowid, 'action' => 'sub'])
            ->assertOk()->assertJson(['ok' => true, 'removed' => true, 'action' => 'qty', 'total_items' => 0]);

        $this->assertSame([], $this->sessionCart());
    }

    public function test_ajax_increase_beyond_stock_returns_stock_notice_payload(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'M', 'color' => 'Đen', 'qty' => 1]);
        $rowid = $this->rowId($product, 'M', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => $rowid, 'action' => 'sum'])
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'message' => $product->name . ' size M màu Đen chỉ còn 1 sản phẩm trong kho.',
                'total_items' => 1,
                'action' => 'qty',
                'rowid' => $rowid,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'size' => 'M',
                'color' => 'Đen',
                'stock' => 1,
            ]);

        $this->assertSame(1, $this->sessionCart()[$rowid]['qty']);
    }

    public function test_form_post_update_redirects_back_to_cart(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->post('/gio-hang/update', ['rowid' => $rowid, 'action' => 'sum'])->assertRedirect(Urls::cart());
        $this->assertSame(2, $this->sessionCart()[$rowid]['qty']);

        $this->post('/gio-hang/update', ['rowid' => 'nope', 'action' => 'sum'])
            ->assertRedirect(Urls::cart())
            ->assertSessionHas('store_message_fail', 'Không tìm thấy sản phẩm trong giỏ hàng');
    }

    public function test_invalid_rowid_and_action_return_ci3_errors(): void
    {
        $this->actingAs($this->makeUser(), 'web');

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => '', 'action' => 'sum'])
            ->assertOk()->assertJson(['ok' => false, 'message' => 'Yêu cầu không hợp lệ', 'total_items' => 0, 'total_price' => 0]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => '1_abc', 'action' => 'sum'])
            ->assertOk()->assertJson(['ok' => false, 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng']);

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => '1_abc', 'size' => 'S'])
            ->assertOk()->assertJson(['ok' => false, 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng']);

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => ['a'], 'action' => ['sum']])
            ->assertOk()->assertJson(['ok' => false, 'message' => 'Yêu cầu không hợp lệ']);
    }

    /* ---------------------------------------------------------------
     | D. Options (size / màu / variant / rowid)
     |--------------------------------------------------------------*/

    public function test_update_options_moves_row_to_new_rowid(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $old = $this->rowId($product, 'S', 'Đen');
        $new = $this->rowId($product, 'M', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => $old, 'size' => 'M', 'color' => 'Đen'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Đã cập nhật size và màu sắc',
                'action' => 'options',
                'old_rowid' => $old,
                'rowid' => $new,
                'qty' => 1,
                'subtotal' => 250000,
                'size' => 'M',
                'color' => 'Đen',
            ]);

        $cart = $this->sessionCart();
        $this->assertSame([$new], array_keys($cart));
        $this->assertSame($new, $cart[$new]['rowid']);
        $this->assertSame($this->variantId($product, 'Đen', 'M'), $cart[$new]['variant_id']);
        $this->assertSame(['image_link' => 'b5-test.jpg', 'size' => 'M', 'color' => 'Đen'], $cart[$new]['options']);
    }

    public function test_update_options_merges_into_existing_row(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->makeProduct([], [['Đen', 'S', 5], ['Đen', 'M', 5]]);
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->addToCart($product, ['size' => 'M', 'color' => 'Đen', 'qty' => 1]);
        $s = $this->rowId($product, 'S', 'Đen');
        $m = $this->rowId($product, 'M', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => $s, 'size' => 'M', 'color' => 'Đen'])
            ->assertOk()->assertJson(['ok' => true, 'rowid' => $m, 'old_rowid' => $s, 'qty' => 3, 'subtotal' => 750000, 'total_items' => 3]);

        $this->assertSame([$m], array_keys($this->sessionCart()));
    }

    public function test_update_options_rejects_target_without_stock(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => $rowid, 'size' => 'M', 'color' => 'Đen'])
            ->assertOk()
            ->assertJson(['ok' => false, 'action' => 'options', 'rowid' => $rowid, 'stock' => 1, 'size' => 'M', 'color' => 'Đen']);

        $this->assertSame([$rowid], array_keys($this->sessionCart()));
    }

    public function test_update_options_with_same_choice_is_noop_success(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/cap-nhat-thuoc-tinh', ['rowid' => $rowid, 'size' => 'S', 'color' => 'Đen'])
            ->assertOk()->assertJson(['ok' => true, 'message' => '', 'rowid' => $rowid, 'old_rowid' => $rowid]);
    }

    /* ---------------------------------------------------------------
     | Delete / clear
     |--------------------------------------------------------------*/

    public function test_delete_single_unknown_and_clear(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->addToCart($product, ['size' => 'M', 'color' => 'Đen']);
        $s = $this->rowId($product, 'S', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/del', ['rowid' => $s])
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Xóa sản phẩm thành công', 'action' => 'delete', 'rowid' => $s, 'removed' => true, 'empty' => false, 'total_items' => 1]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/del', ['rowid' => 'unknown'])
            ->assertOk()->assertJson(['ok' => true, 'message' => '', 'empty' => false]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/del', [])
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Xóa giỏ hàng thành công', 'empty' => true, 'total_items' => 0, 'total_price' => 0]);

        $this->assertSame([], $this->sessionCart());
        $this->assertSame([], $this->savedCart($user));

        $this->get('/gio-hang')->assertOk()->assertSee('Giỏ hàng của bạn đang trống')->assertSee('0 sản phẩm');
    }

    /* ---------------------------------------------------------------
     | F. Tính lại giá / tồn khi mở giỏ (CI3 _recalculate_cart_prices)
     |--------------------------------------------------------------*/

    public function test_cart_page_resyncs_prices_and_drops_unavailable_items(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $keep = $this->standardProduct();
        $stopped = $this->standardProduct();
        $deleted = $this->standardProduct();
        $this->addToCart($keep, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->addToCart($stopped, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);
        $this->addToCart($deleted, ['size' => 'S', 'color' => 'Đen', 'qty' => 1]);

        Product::query()->whereKey($keep->id)->update(['list_price' => 400000, 'discount_amount' => 100000]);
        Product::query()->whereKey($stopped->id)->update(['status' => 'inactive']);
        Product::query()->whereKey($deleted->id)->delete();

        // CI3 set_store_flash('fail') 2 lần cùng key: thông báo sau (tính lại giá) ghi đè thông báo trước.
        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('600.000 ₫')
            ->assertSee('Một số sản phẩm đã được cập nhật giá hoặc không còn khả dụng: ' . $stopped->name)
            ->assertDontSee('1 sản phẩm trong giỏ không còn bán và đã được xóa tự động.')
            ->assertDontSee($deleted->name);

        $cart = $this->sessionCart();
        $rowid = $this->rowId($keep, 'S', 'Đen');
        $this->assertSame([$rowid], array_keys($cart));
        $this->assertSame(300000.0, $cart[$rowid]['price']);
        $this->assertSame(600000.0, $cart[$rowid]['subtotal']);
        $this->assertArrayNotHasKey('variants', $cart[$rowid], 'Khóa hiển thị không được ghi vào session');
        // json_encode (như CI3, không JSON_PRESERVE_ZERO_FRACTION) lưu 300000.0 thành 300000.
        $this->assertEquals($cart, $this->savedCart($user));
    }

    public function test_cart_page_reports_items_whose_product_was_deleted(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $keep = $this->standardProduct();
        $deleted = $this->standardProduct();
        $this->addToCart($keep, ['size' => 'S', 'color' => 'Đen']);
        $this->addToCart($deleted, ['size' => 'S', 'color' => 'Đen']);
        Product::query()->whereKey($deleted->id)->delete();

        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('1 sản phẩm trong giỏ không còn bán và đã được xóa tự động.');

        $this->assertSame([$this->rowId($keep, 'S', 'Đen')], array_keys($this->sessionCart()));
    }

    public function test_cart_page_drops_item_whose_stock_fell_below_quantity(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 3]);

        DB::table('product_variants')->where('id', $this->variantId($product, 'Đen', 'S'))->update(['stock' => 2]);

        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('Một số sản phẩm đã được cập nhật giá hoặc không còn khả dụng: ' . $product->name)
            ->assertSee('Giỏ hàng của bạn đang trống');

        $this->assertSame([], $this->sessionCart());
    }

    /* ---------------------------------------------------------------
     | G/H. Đăng nhập / đăng xuất
     |--------------------------------------------------------------*/

    public function test_login_restores_saved_cart_and_logout_syncs_it(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $rowid = $this->rowId($product, 'S', 'Đen');
        $saved = [$rowid => [
            'rowid' => $rowid, 'id' => $product->id, 'variant_id' => $this->variantId($product, 'Đen', 'S'),
            'qty' => 2, 'price' => 250000, 'name' => $product->name, 'subtotal' => 500000,
            'options' => ['image_link' => 'b5-test.jpg', 'size' => 'S', 'color' => 'Đen'],
        ]];
        DB::table('user_saved_carts')->insert([
            'user_id' => $user->id,
            'cart_json' => json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        // Guest không giữ được giỏ (CI3 MY_Controller xóa custom_cart của guest).
        $this->get('/')->assertOk();
        $this->assertNull(session('custom_cart'));

        $this->post('/dang-nhap', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(url('gio-hang'));
        $this->assertSame($saved, $this->sessionCart());

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1])->assertRedirect(Urls::cart());
        $this->assertSame(3, $this->savedCart($user)[$rowid]['qty']);

        $this->post('/user/logout')->assertRedirect(route('storefront.home'));
        $this->assertNull(session('custom_cart'));
        $this->assertSame(3, $this->savedCart($user)[$rowid]['qty']);
    }

    public function test_redirect_back_after_login_completes_pending_add(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();

        $this->post('/gio-hang/them/' . $product->id, ['size' => 'S', 'color' => 'Đen', 'qty' => 2])->assertRedirect(Urls::login());

        $this->post('/dang-nhap', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(Urls::cart());

        $rowid = $this->rowId($product, 'S', 'Đen');
        $this->assertSame(2, $this->sessionCart()[$rowid]['qty']);
        $this->assertNull(session('pending_cart_add'));
    }

    public function test_logged_in_user_with_empty_session_gets_saved_cart_on_any_page(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $rowid = $this->rowId($product, 'S', 'Đen');
        DB::table('user_saved_carts')->insert([
            'user_id' => $user->id,
            'cart_json' => json_encode([$rowid => ['rowid' => $rowid, 'id' => $product->id, 'qty' => 1, 'subtotal' => 250000, 'options' => []]]),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'web')->get('/')
            ->assertOk()
            ->assertSee('<span class="jm-icon-dot wf-chrome-cart-badge">1</span>', false);

        $this->assertSame([$rowid], array_keys($this->sessionCart()));
    }

    /* ---------------------------------------------------------------
     | J. Không đụng giỏ của user khác
     |--------------------------------------------------------------*/

    public function test_cart_actions_only_touch_current_user_saved_cart(): void
    {
        $other = $this->makeUser();
        $otherJson = json_encode(['keep' => ['id' => 1, 'qty' => 9]]);
        DB::table('user_saved_carts')->insert(['user_id' => $other->id, 'cart_json' => $otherJson, 'updated_at' => now()]);
        $before = DB::table('user_saved_carts')->where('user_id', $other->id)->value('updated_at');

        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'user_id' => $other->id]);
        $this->withHeaders(self::AJAX)->post('/gio-hang/del', ['rowid' => 'keep', 'user_id' => $other->id]);

        $row = DB::table('user_saved_carts')->where('user_id', $other->id)->first();
        $this->assertSame($otherJson, $row->cart_json);
        $this->assertEquals($before, $row->updated_at);
    }

    /* ---------------------------------------------------------------
     | handlePath + legacy
     |--------------------------------------------------------------*/

    public function test_default_routing_paths_and_unknown_methods(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->get('/gio-hang/index')->assertOk()->assertSee('jm-cart-lux', false);
        $this->get('/gio-hang/khong-ton-tai')->assertNotFound();
        $this->get('/gio-hang/_recalculate_cart_prices')->assertNotFound();

        $this->post('/gio-hang/add', ['id' => $product->id, 'size' => 'S', 'color' => 'Đen'])->assertRedirect(Urls::cart());
        $rowid = $this->rowId($product, 'S', 'Đen');

        $this->withHeaders(self::AJAX)->post('/gio-hang/update_options', ['rowid' => $rowid, 'size' => 'M', 'color' => 'Đen'])
            ->assertOk()->assertJson(['ok' => true, 'rowid' => $this->rowId($product, 'M', 'Đen')]);

        $this->withHeaders(self::AJAX)->post('/gio-hang/update/' . $this->rowId($product, 'M', 'Đen'), ['rowid' => $this->rowId($product, 'M', 'Đen'), 'action' => 'sub'])
            ->assertOk()->assertJson(['ok' => true, 'removed' => true]);
    }

    public function test_legacy_cart_urls_redirect_301(): void
    {
        $this->get('/cart')->assertStatus(301)->assertRedirect(url('gio-hang'));
        $this->get('/cart/update')->assertStatus(301)->assertRedirect(url('gio-hang/update'));
    }
}
