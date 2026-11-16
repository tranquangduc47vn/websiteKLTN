<?php

namespace Tests\Feature\Storefront;

use App\Models\Catalog;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\Storefront\Urls;
use Illuminate\Support\Facades\DB;
use Tests\LegacyMysqlTestCase;

class PriorityCleanupTest extends LegacyMysqlTestCase
{
    public function test_legacy_product_and_catalog_urls_redirect_without_a_loop(): void
    {
        $product = Product::query()->orderBy('id')->first();
        $this->assertNotNull($product);
        $target = Urls::product($product);

        $this->get('/product/view/' . $product->id)->assertRedirect($target);
        $this->get('/product/detail/' . $product->id)->assertRedirect($target);
        $this->get($target)->assertOk();
        $this->get('/product/view/99999999')->assertRedirect('/');

        $catalog = Catalog::query()->orderBy('id')->first();
        $this->assertNotNull($catalog);
        $category = Urls::category($catalog);
        $this->get('/product/catalog/' . $catalog->id . '?sort=new')->assertRedirect($category . '?sort=new');
        $this->get('/sai-slug-c' . $catalog->id)->assertRedirect($category);
        $this->get($category)->assertOk();

        $this->get('/product/search?key-search=ao')->assertRedirect();
        $this->get('/product/search')->assertRedirect('/tim-kiem');
    }

    public function test_legacy_redirect_is_the_same_for_a_logged_in_customer(): void
    {
        $user = User::query()->orderBy('id')->first();
        $product = Product::query()->orderBy('id')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($product);

        $this->actingAs($user, 'web')
            ->get('/product/view/' . $product->id)
            ->assertRedirect(Urls::product($product));
    }

    public function test_html_formatting_stays_and_scripts_are_removed(): void
    {
        $this->assertSame('B171-fmt', trim(strip_tags(HtmlSanitizer::clean('<p>B171-fmt</p><script>alert(1)</script>'))));
        $this->assertStringNotContainsString('onerror', HtmlSanitizer::clean('<img src="/upload/news/a.jpg" onerror="alert(1)">'));
        $this->assertStringNotContainsString('javascript:', HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>'));
        $this->assertStringContainsString('/upload/news/a.jpg', HtmlSanitizer::clean('<img src="/upload/news/a.jpg" alt="anh">'));

        $product = Product::query()->orderBy('id')->first();
        $this->assertNotNull($product);
        $original = (string) $product->content;
        try {
            DB::table('products')->where('id', $product->id)->update([
                'content' => '<p>B171-fmt</p><script>alert(1)</script><img src="/upload/news/a.jpg" onerror="alert(1)">',
            ]);
            $this->get(Urls::product($product))
                ->assertOk()
                ->assertSee('B171-fmt')
                ->assertDontSee('alert(1)', false)
                ->assertDontSee('onerror="alert(1)"', false);
        } finally {
            DB::table('products')->where('id', $product->id)->update(['content' => $original]);
        }

        $id = (int) DB::table('content_pages')->insertGetId([
            'type' => ContentPage::TYPE_NEWS,
            'legacy_id' => 0,
            'title' => 'B171 tin',
            'slug' => 'b171',
            'intro' => 'mo ta',
            'content' => '<h2>B171-tin</h2><script>alert(1)</script>',
            'image' => 'default.jpg',
            'created_at' => now(),
        ]);
        try {
            $this->get('/tin-tuc/b171-n' . $id)
                ->assertOk()
                ->assertSee('B171-tin')
                ->assertDontSee('<script>alert(1)</script>', false);
        } finally {
            DB::table('content_pages')->where('id', $id)->delete();
        }
    }

    public function test_gioi_thieu_page_renders(): void
    {
        $page = ContentPage::query()->where('type', ContentPage::TYPE_PAGE)->where('slug', 'gioi-thieu')->first();
        $response = $this->get('/gioi-thieu')->assertOk();
        if ($page) {
            $response->assertSee($page->title);
        } else {
            $response->assertSee('Nội dung giới thiệu đang được cập nhật');
        }
    }
}
