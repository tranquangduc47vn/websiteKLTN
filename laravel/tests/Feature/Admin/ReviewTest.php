<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ProductReview;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewTest extends AdminTestCase
{
    /** @var array<int, array{rate_count: int, rate_total: int}> */
    private array $rates = [];

    protected function tearDown(): void
    {
        DB::table('product_review')->where('comment', 'like', 'B14-%')->delete();
        foreach ($this->rates as $id => $rate) {
            DB::table('product')->where('id', $id)->update($rate);
        }
        parent::tearDown();
    }

    public function test_guest_fake_session_and_levels(): void
    {
        $this->get('/admin/product-reviews')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'name' => 'x', 'email' => 'x@example.test', 'level' => 0]])
            ->get('/admin/product-reviews')
            ->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/product-reviews')->assertRedirect('/admin');
        $this->get('/admin/product-reviews?level=0')->assertRedirect('/admin');

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/product-reviews?level=0')->assertOk()->assertSee('Duyệt đánh giá sản phẩm');

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->get('/admin/product-reviews/approve/1')->assertNotFound();
        $this->get('/admin/product-reviews/delete/1')->assertNotFound();
    }

    public function test_admin_filters_approves_and_rejects_without_touching_stock(): void
    {
        $productId = $this->productId();
        $stock = (int) DB::table('product_variants')->sum('stock');
        $approvedBefore = (int) DB::table('product_review')->where('product_id', $productId)->where('status', 1)->count();
        $sumBefore = (int) DB::table('product_review')->where('product_id', $productId)->where('status', 1)->sum('stars');
        $id = (int) DB::table('product_review')->insertGetId([
            'product_id' => $productId,
            'user_id' => 0,
            'user_name' => 'B14 Admin',
            'stars' => 5,
            'comment' => 'B14-admin-row',
            'status' => ProductReview::STATUS_PENDING,
            'reviewed_at' => 0,
            'reviewed_by' => 0,
            'session_token' => '',
            'created' => time(),
        ]);

        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->get('/admin/product-reviews?q=B14-admin-row')->assertOk()->assertSee('B14-admin-row')->assertSee('Chờ duyệt');

        $this->post('/admin/product-reviews/approve/' . $id)->assertRedirect('/admin/product-reviews');
        $this->assertSame(ProductReview::STATUS_APPROVED, (int) DB::table('product_review')->where('id', $id)->value('status'));
        $this->assertSame($approvedBefore + 1, (int) DB::table('product')->where('id', $productId)->value('rate_count'));
        $this->assertSame($sumBefore + 5, (int) DB::table('product')->where('id', $productId)->value('rate_total'));

        $this->post('/admin/product-reviews/reject/' . $id)->assertRedirect();
        $this->assertSame(ProductReview::STATUS_REJECTED, (int) DB::table('product_review')->where('id', $id)->value('status'));
        $this->assertSame($approvedBefore, (int) DB::table('product')->where('id', $productId)->value('rate_count'));

        $this->post('/admin/product-reviews/delete/' . $id)->assertRedirect();
        $this->assertNull(DB::table('product_review')->where('id', $id)->first());
        $this->post('/admin/product-reviews/approve/99999999')->assertRedirect()->assertSessionHas('message_fail');
        $this->assertSame($stock, (int) DB::table('product_variants')->sum('stock'));
    }

    public function test_admin_review_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/product-reviews/approve/1')->assertStatus(419);
        $this->post('/admin/product-reviews/delete/1')->assertStatus(419);
    }

    private function productId(): int
    {
        $id = (int) DB::table('product')->orderBy('id')->value('id');
        if (! isset($this->rates[$id])) {
            $row = DB::table('product')->where('id', $id)->first(['rate_count', 'rate_total']);
            $this->rates[$id] = [
                'rate_count' => (int) $row->rate_count,
                'rate_total' => (int) $row->rate_total,
            ];
        }

        return $id;
    }
}
