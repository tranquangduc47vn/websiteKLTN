<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Admin\ProductImageStore;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCatalogTest extends AdminTestCase
{
    protected function tearDown(): void
    {
        $store = app(ProductImageStore::class);
        $rows = DB::table('product')->where('name', 'like', 'B9 %')->get(['image_link', 'image_list']);
        foreach ($rows as $row) {
            $this->deleteGeneratedImage($store, (string) $row->image_link);
            $list = json_decode((string) $row->image_list, true);
            if (is_array($list)) {
                foreach ($list as $name) {
                    $this->deleteGeneratedImage($store, (string) $name);
                }
            }
        }
        parent::tearDown();
    }

    public function test_guest_and_level_user_cannot_open_product_or_catalog(): void
    {
        $this->get('/admin/products')->assertRedirect(route('admin.login'));
        $this->get('/admin/catalog')->assertRedirect(route('admin.login'));
        $this->post('/admin/products/add')->assertRedirect(route('admin.login'));

        $this->withSession([
            'login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0],
        ]);
        $this->get('/admin/products')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());

        $user = $this->makeAdmin(Admin::ROLE_USER);
        $this->actingAs($user, 'admin');
        $this->get('/admin/products')
            ->assertRedirect(route('admin.home'))
            ->assertSessionHas('message_fail', 'Bạn không có quyền truy cập khu vực này!');
        $this->get('/admin/catalog')->assertRedirect(route('admin.home'));
        $this->get('/admin/products?level=0')->assertRedirect(route('admin.home'));
        $this->get('/admin/catalog/add?level=0')->assertRedirect(route('admin.home'));
    }

    public function test_mod_can_manage_catalog_but_cannot_delete(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $name = 'B9 Catalog ' . Str::lower(Str::random(6));

        $this->get('/admin/catalog')->assertOk()->assertSee('Danh mục');
        $this->post('/admin/catalog/add', [
            'name' => $name,
            'description' => 'Mo ta B9',
            'parent_id' => 1,
            'sort_order' => 3,
        ])->assertRedirect(url('admin/catalog'));

        $id = (int) DB::table('catalog')->where('name', $name)->value('id');
        $this->assertGreaterThan(0, $id);
        $this->get('/admin/catalog?q=' . urlencode($name))->assertOk()->assertSee($name);
        $this->get('/admin/catalog/edit/' . $id)->assertOk()->assertSee($name);

        $this->post('/admin/catalog/edit/' . $id, [
            'name' => $name . ' sua',
            'description' => 'Sau',
            'parent_id' => 1,
            'sort_order' => 4,
        ])->assertRedirect(url('admin/catalog'));
        $this->assertSame($name . ' sua', DB::table('catalog')->where('id', $id)->value('name'));

        $this->post('/admin/catalog/del/' . $id)
            ->assertRedirect(url('admin/catalog'))
            ->assertSessionHas('message_fail', 'Bạn không có đủ quyền hạn để xóa danh mục sản phẩm!');
        $this->assertNotNull(DB::table('catalog')->where('id', $id)->first());
        $this->get('/admin/catalog/del/' . $id)->assertNotFound();
    }

    public function test_admin_catalog_crud_validation_and_self_parent(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/catalog/add', [
            'name' => '',
            'parent_id' => 1,
            'sort_order' => 1,
        ])->assertSessionHasErrors('name');

        $name = 'B9 Catalog ' . Str::lower(Str::random(6));
        $this->post('/admin/catalog/add', [
            'name' => $name,
            'parent_id' => 1,
            'sort_order' => 2,
        ])->assertRedirect(url('admin/catalog'));
        $id = (int) DB::table('catalog')->where('name', $name)->value('id');

        $this->post('/admin/catalog/add', [
            'name' => $name,
            'parent_id' => $id,
            'sort_order' => 2,
        ])->assertRedirect(url('admin/catalog'));
        $this->assertSame(2, DB::table('catalog')->where('name', $name)->count());

        $this->post('/admin/catalog/edit/' . $id, [
            'name' => $name,
            'parent_id' => $id,
            'sort_order' => 2,
        ])->assertRedirect(url('admin/catalog/edit/' . $id))
            ->assertSessionHas('message_fail');

        $this->post('/admin/catalog/del/' . $id)->assertRedirect(url('admin/catalog'));
        $this->assertNull(DB::table('catalog')->where('id', $id)->first());
    }

    public function test_product_crud_variants_search_and_pagination(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $leaf = $this->leafCatalogId();

        $this->post('/admin/products/add', [
            'name' => '',
            'catalog_id' => $leaf,
            'price' => '-5',
        ])->assertSessionHasErrors(['name', 'price_value', 'image']);

        $name = 'B9 Product ' . Str::lower(Str::random(6));
        $this->post('/admin/products/add', [
            'name' => $name,
            'catalog_id' => $leaf,
            'price' => '250,000',
            'discount' => '10',
            'content' => '<p>Mo ta B9</p>',
            'color' => ['Đen'],
            'size' => ['M'],
            'image' => UploadedFile::fake()->image('cover.jpg'),
            'list_image' => [UploadedFile::fake()->image('side.png')],
        ])->assertRedirect(url('admin/products'));

        $product = Product::query()->where('name', $name)->first();
        $this->assertNotNull($product);
        $this->assertSame(250000, (int) $product->price);
        $this->assertSame(25000, (int) $product->discount);
        $this->assertSame(10, (int) $product->discount_percent);
        $this->assertSame('percent', $product->discount_type);
        $this->assertSame('SP' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT), $product->code);
        $this->assertSame(0, (int) $product->quantity);
        $variant = ProductVariant::query()->where('product_id', $product->id)->where('status', 1)->first();
        $this->assertNotNull($variant);
        $this->assertSame('Đen', $variant->color);
        $this->assertSame('M', $variant->size);
        $this->assertSame(0, (int) $variant->stock);
        $this->assertNotSame('', (string) $product->image_link);
        $this->assertFileExists(app(ProductImageStore::class)->directory() . DIRECTORY_SEPARATOR . $product->image_link);

        $this->post('/admin/products/add', [
            'name' => $name,
            'catalog_id' => $leaf,
            'price' => '1000',
            'image' => UploadedFile::fake()->image('cover2.jpg'),
        ])->assertRedirect(url('admin/products'));
        $this->assertSame(2, Product::query()->where('name', $name)->count());

        $this->get('/admin/products?name=' . urlencode($name))
            ->assertOk()
            ->assertSee($name)
            ->assertSee('Tìm thấy: 2');

        $this->post('/admin/products/edit/' . $product->id, [
            'name' => $name . ' sua',
            'catalog_id' => $leaf,
            'price' => '200000',
            'discount' => '0',
            'content' => 'Noi dung',
            'status' => '0',
            'color' => ['Đen'],
            'size' => ['M', 'L'],
            'product_image_main' => $product->image_link,
            'product_image_list' => $product->image_list,
            'variant' => [
                $variant->id => ['price' => '180000', 'cost_price' => '90000', 'min_stock' => 3, 'status' => 1],
            ],
        ])->assertRedirect(url('admin/products'));

        $product->refresh();
        $variant->refresh();
        $this->assertSame($name . ' sua', $product->name);
        $this->assertSame(0, (int) $product->status);
        $this->assertSame(180000, (int) $variant->price);
        $this->assertSame(90000, (int) $variant->cost_price);
        $this->assertSame(2, ProductVariant::query()->where('product_id', $product->id)->where('status', 1)->count());

        $other = Product::query()->where('name', $name)->where('id', '!=', $product->id)->first();
        $otherVariant = ProductVariant::query()->where('product_id', $other->id)->first();
        $otherPrice = (int) $otherVariant->price;
        $this->post('/admin/products/edit/' . $product->id, [
            'name' => $product->name,
            'catalog_id' => $leaf,
            'price' => '200000',
            'status' => '0',
            'color' => ['Đen'],
            'size' => ['M', 'L'],
            'product_image_main' => $product->image_link,
            'product_image_list' => $product->image_list,
            'variant' => [
                $otherVariant->id => ['price' => '1', 'status' => 1],
            ],
        ])->assertRedirect(url('admin/products'));
        $this->assertSame($otherPrice, (int) $otherVariant->fresh()->price);

        $this->get('/admin/products/index?name=' . urlencode('B9 Product') . '&status=0')
            ->assertOk()
            ->assertSee($name . ' sua')
            ->assertDontSee('>' . $name . '<', false);

        $this->postJson('/admin/products/del', ['id' => $product->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'success']);
        $this->assertNull(Product::query()->find($product->id));
        $this->assertSame(0, ProductVariant::query()->where('product_id', $product->id)->count());

        $this->get('/admin/products/del')->assertNotFound();
    }

    public function test_mod_cannot_delete_product_and_admin_bulk_deletes(): void
    {
        $leaf = $this->leafCatalogId();
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $ids = [];
        foreach (['B9 Bulk A ' . Str::random(4), 'B9 Bulk B ' . Str::random(4)] as $name) {
            $this->post('/admin/products/add', [
                'name' => $name,
                'catalog_id' => $leaf,
                'price' => '10000',
                'image' => UploadedFile::fake()->image('b.jpg'),
            ])->assertRedirect(url('admin/products'));
            $ids[] = (int) Product::query()->where('name', $name)->value('id');
        }

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/products')->assertOk()->assertDontSee('Xóa mục đã chọn');
        $this->postJson('/admin/products/del', ['id' => $ids[0]])
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => 'failer']);
        $this->assertNotNull(Product::query()->find($ids[0]));
        $this->post('/admin/products/bulk_del', ['checkbox' => $ids])
            ->assertRedirect(url('admin/products'))
            ->assertSessionHas('message_fail', 'Tài khoản nhân viên (MOD) không có quyền xóa hàng loạt sản phẩm!');

        $this->actingAs($admin, 'admin');
        $this->post('/admin/products/bulk_del', ['checkbox' => $ids])
            ->assertRedirect(url('admin/products'))
            ->assertSessionHas('message_success');
        $this->assertSame(0, Product::query()->whereIn('id', $ids)->count());
    }

    public function test_product_list_paginates_ten_per_page(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $leaf = $this->leafCatalogId();
        $token = 'B9 Page ' . Str::lower(Str::random(5));
        for ($i = 1; $i <= 11; $i++) {
            DB::table('product')->insert([
                'catalog_id' => $leaf,
                'name' => $token . ' ' . $i,
                'content' => '',
                'price' => 1000,
                'discount' => 0,
                'discount_type' => 'amount',
                'discount_percent' => 0,
                'image_link' => 'b9-page-placeholder.jpg',
                'image_list' => '[]',
                'view' => 0,
                'buyed' => 0,
                'quantity' => 0,
                'rate_total' => 0,
                'rate_count' => 0,
                'created' => time(),
                'code' => '',
                'status' => 1,
            ]);
        }

        $this->get('/admin/products/index?name=' . urlencode($token))
            ->assertOk()
            ->assertSee('Tìm thấy: 11')
            ->assertSee($token . ' 11')
            ->assertDontSee($token . ' 1</td>', false);
        $this->get('/admin/products/index/10?name=' . urlencode($token))
            ->assertOk()
            ->assertSee($token . ' 1');
    }

    public function test_product_and_catalog_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/catalog/add', ['name' => 'B9 CSRF', 'parent_id' => 1, 'sort_order' => 1])->assertStatus(419);
        $this->post('/admin/products/del', ['id' => 1])->assertStatus(419);
        $this->assertNull(DB::table('catalog')->where('name', 'B9 CSRF')->first());
    }

    private function deleteGeneratedImage(ProductImageStore $store, string $filename): void
    {
        if (preg_match('/^p\d{14}[a-f0-9]{8}\./', $filename) !== 1) {
            return;
        }
        $store->delete($filename);
    }

    private function leafCatalogId(): int
    {
        $id = DB::table('catalog as child')
            ->join('catalog as mid', 'mid.id', '=', 'child.parent_id')
            ->where('mid.parent_id', 1)
            ->value('child.id');
        $this->assertNotNull($id);

        return (int) $id;
    }
}
