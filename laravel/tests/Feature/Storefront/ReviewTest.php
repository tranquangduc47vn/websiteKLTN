<?php

namespace Tests\Feature\Storefront;

use App\Models\ProductReview;
use App\Models\User;
use App\Support\LegacyPassword;
use App\Support\Storefront\Urls;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

class ReviewTest extends LegacyMysqlTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var array<int, array{rate_count: int, rate_total: int}> */
    private array $rates = [];

    private int $stockBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockBefore = (int) DB::table('product_variants')->sum('stock');
    }

    protected function tearDown(): void
    {
        DB::table('product_reviews')->where('comment', 'like', 'B14-%')->delete();
        if ($this->userIds !== []) {
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        foreach ($this->rates as $id => $rate) {
            DB::table('products')->where('id', $id)->update($rate);
        }
        parent::tearDown();
    }

    public function test_guest_product_page_shows_review_form_and_hides_pending(): void
    {
        $productId = $this->productId();
        $this->post('/product/raty', [
            'id' => $productId,
            'score' => 4,
            'comment' => 'B14-pending-hidden',
            'user_id' => 1,
        ])->assertOk()->assertJsonPath('complete', true);

        $this->flushSession();
        $html = $this->get(Urls::product($productId))->assertOk()->assertSee('Viết đánh giá')->getContent();
        $this->assertStringNotContainsString('B14-pending-hidden', (string) $html);
        $this->assertSame(0, (int) DB::table('product_reviews')->where('comment', 'B14-pending-hidden')->value('user_id'));
    }

    public function test_logged_in_user_can_review_without_a_purchase_and_cannot_duplicate(): void
    {
        $productId = $this->productId();
        $user = $this->makeUser();
        $orders = (int) DB::table('orders')->where('user_id', $user->id)->count();
        $this->actingAs($user, 'web');

        $this->post('/product/raty', [
            'id' => $productId,
            'score' => 5,
            'comment' => 'B14-first',
            'user_id' => 999999,
        ])->assertOk()
            ->assertJsonPath('complete', true)
            ->assertJsonPath('msg', 'Cảm ơn bạn đã đánh giá 5 sao');

        $this->assertSame(1, (int) DB::table('product_reviews')->where('comment', 'B14-first')->count());
        $this->assertSame($user->id, (int) DB::table('product_reviews')->where('comment', 'B14-first')->value('user_id'));
        $this->assertSame(ProductReview::STATUS_PENDING, (string) DB::table('product_reviews')->where('comment', 'B14-first')->value('status'));
        $this->assertSame($orders, (int) DB::table('orders')->where('user_id', $user->id)->count());

        $this->post('/product/raty', ['id' => $productId, 'score' => 5, 'comment' => 'B14-first'])
            ->assertJsonPath('msg', 'Bạn đang đánh giá 5 sao');
        $this->assertSame(1, (int) DB::table('product_reviews')->where('user_id', $user->id)->where('product_id', $productId)->count());

        $this->post('/product/raty', ['id' => $productId, 'score' => 2, 'comment' => 'B14-updated'])
            ->assertJsonPath('msg', 'Đã cập nhật đánh giá thành 2 sao');
        $this->assertSame(1, (int) DB::table('product_reviews')->where('user_id', $user->id)->where('product_id', $productId)->count());
        $this->assertSame(2, (int) DB::table('product_reviews')->where('comment', 'B14-updated')->value('stars'));
    }

    public function test_invalid_rating_content_and_product_are_rejected(): void
    {
        $productId = $this->productId();
        $before = (int) DB::table('product_reviews')->count();
        foreach ([0, -1, 6, 999, 'abc', null] as $score) {
            $this->post('/product/raty', ['id' => $productId, 'score' => $score, 'comment' => 'B14-bad-score'])
                ->assertOk()
                ->assertJsonPath('complete', false)
                ->assertJsonPath('msg', 'Dữ liệu đánh giá không hợp lệ');
        }
        $this->post('/product/raty', ['id' => $productId, 'score' => 3, 'comment' => '   '])
            ->assertJsonPath('msg', 'Vui lòng nhập nội dung đánh giá');
        $this->post('/product/raty', ['id' => $productId, 'score' => 3, 'comment' => 'B14-' . str_repeat('a', 2001)])
            ->assertJsonPath('msg', 'Nội dung đánh giá tối đa 2000 ký tự');
        $this->post('/product/raty', ['id' => 99999999, 'score' => 3, 'comment' => 'B14-missing'])
            ->assertJsonPath('msg', 'Sản phẩm không tồn tại');
        $this->assertSame($before, (int) DB::table('product_reviews')->count());
    }

    public function test_user_cannot_undo_another_users_review_and_xss_is_escaped(): void
    {
        $productId = $this->productId();
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($owner, 'web');
        $this->post('/product/raty', [
            'id' => $productId,
            'score' => 4,
            'comment' => 'B14-<script>alert(1)</script>',
        ])->assertJsonPath('complete', true);
        $reviewId = (int) DB::table('product_reviews')->where('user_id', $owner->id)->where('product_id', $productId)->value('id');

        Auth::guard('web')->logout();
        $this->actingAs($other, 'web');
        $this->post('/product/raty-undo', ['id' => $productId, 'review_id' => $reviewId])
            ->assertOk()
            ->assertJsonPath('complete', false)
            ->assertJsonPath('msg', 'Bạn chưa đánh giá sản phẩm này');
        $this->assertNotNull(DB::table('product_reviews')->where('id', $reviewId)->first());

        DB::table('product_reviews')->where('id', $reviewId)->update(['status' => ProductReview::STATUS_APPROVED]);
        $html = $this->get(Urls::product($productId))->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', (string) $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $html);

        Auth::guard('web')->logout();
        $this->actingAs($owner, 'web');
        $this->post('/product/raty-undo', ['id' => $productId])->assertJsonPath('complete', true);
        $this->assertNull(DB::table('product_reviews')->where('id', $reviewId)->first());
    }

    public function test_fake_session_user_is_not_the_reviewer_and_csrf_is_required(): void
    {
        $productId = $this->productId();
        $this->withSession(['user' => ['id' => 10, 'name' => 'Fake', 'email' => 'fake@example.test']])
            ->post('/product/raty', ['id' => $productId, 'score' => 3, 'comment' => 'B14-fake-session'])
            ->assertJsonPath('complete', true);
        $this->assertSame(0, (int) DB::table('product_reviews')->where('comment', 'B14-fake-session')->value('user_id'));

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/product/raty', ['id' => $productId, 'score' => 3, 'comment' => 'B14-csrf'])->assertStatus(419);
        $this->post('/product/raty-undo', ['id' => $productId])->assertStatus(419);
    }

    public function test_product_detail_rating_queries_stay_aggregated(): void
    {
        $productId = $this->productId();
        $price = (int) DB::table('products')->where('id', $productId)->value('list_price');
        DB::enableQueryLog();
        $this->get(Urls::product($productId))->assertOk()->assertSee('Viết đánh giá');
        $reviewQueries = 0;
        foreach (DB::getQueryLog() as $row) {
            if (str_contains(strtolower((string) $row['query']), 'product_review')) {
                $reviewQueries++;
            }
        }
        $this->assertLessThanOrEqual(3, $reviewQueries);
        $this->assertSame($price, (int) DB::table('products')->where('id', $productId)->value('list_price'));
        $this->assertSame($this->stockBefore, (int) DB::table('product_variants')->sum('stock'));
    }

    private function productId(): int
    {
        $id = (int) DB::table('products')->orderBy('id')->value('id');
        if (! isset($this->rates[$id])) {
            $row = DB::table('products')->where('id', $id)->first(['rating_count', 'rating_total']);
            $this->rates[$id] = [
                'rating_count' => (int) $row->rating_count,
                'rating_total' => (int) $row->rating_total,
            ];
        }

        return $id;
    }

    private function makeUser(): User
    {
        $user = User::query()->create([
            'name' => 'B14 Reviewer',
            'email' => 'b14review.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('B14-Secret#1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $user->id;

        return $user;
    }
}
