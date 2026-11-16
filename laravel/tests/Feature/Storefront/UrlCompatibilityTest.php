<?php

namespace Tests\Feature\Storefront;

use App\Models\Catalog;
use App\Support\Storefront\Slug;
use App\Support\Storefront\Urls;
use Tests\LegacyMysqlTestCase;

class UrlCompatibilityTest extends LegacyMysqlTestCase
{
    public function test_legacy_catalog_offset_is_canonical_and_duplicates_redirect(): void
    {
        $catalog = $this->openCatalog();
        $slug = Slug::make($catalog->name);
        $this->assertNotSame('', $slug);
        $this->assertFalse(Urls::isReservedCatalogSlug($slug));

        $this->get('/' . $slug)->assertOk()->assertSee('<link rel="canonical"', false);
        $this->get('/' . $slug . '/8?sort=price_asc')->assertOk();
        $this->get('/' . $slug . '/_/8?sort=price_asc')
            ->assertRedirect('/' . $slug . '/8?sort=price_asc');
        $this->get('/' . $slug . '?page=2&sort=price_asc')
            ->assertRedirect('/' . $slug . '/8?sort=price_asc');
        $this->get('/danh-muc/' . $slug)->assertRedirect('/' . $slug);
    }

    public function test_reserved_category_uses_namespace_and_functional_routes_stay(): void
    {
        $catalog = null;
        foreach (Catalog::query()->orderBy('id')->get() as $row) {
            if (in_array(Slug::make($row->name), ['ban-chay', 'khuyen-mai', 'tin-tuc'], true)) {
                $catalog = $row;
                break;
            }
        }
        $this->assertNotNull($catalog);
        $slug = Slug::make($catalog->name);
        $target = Urls::category($catalog);

        $this->assertStringContainsString('/danh-muc/' . $slug, $target);
        $this->get('/' . $slug)->assertOk()
            ->assertSee('rel="canonical" href="' . url($slug) . '"', false);
        $this->get($target)->assertOk()
            ->assertSee('rel="canonical" href="' . $target . '"', false);
        $this->get('/' . $slug . '-c' . $catalog->id)->assertRedirect($target);
        $this->get('/danh-muc/' . $slug . '/8')->assertOk();
        $this->get('/danh-muc/' . $slug . '?page=2&sort=new')
            ->assertRedirect('/danh-muc/' . $slug . '/8?sort=new');
    }

    public function test_private_pages_are_noindex_and_local_robots_is_not_production(): void
    {
        $this->get('/dang-nhap')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
        $this->get('/gio-hang')->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertSee('local-or-test: not a production robots policy', false)
            ->assertDontSee('Sitemap:', false);
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_production_sitemap_uses_namespaced_category_and_skips_private_urls(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $catalog = null;
        foreach (Catalog::query()->orderBy('id')->get() as $row) {
            if (Urls::isReservedCatalogSlug(Slug::make($row->name)) && Slug::make($row->name) !== 'danh-muc') {
                $catalog = $row;
                break;
            }
        }
        $this->assertNotNull($catalog);

        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $sitemap->assertSee(Urls::category($catalog), false);
        $sitemap->assertDontSee(url('gio-hang') . '</loc>', false);
        $sitemap->assertDontSee(url('admin') . '</loc>', false);
        $sitemap->assertDontSee(url('thanh-toan') . '</loc>', false);
    }

    private function openCatalog(): Catalog
    {
        foreach (Catalog::query()->orderBy('id')->get() as $row) {
            $slug = Slug::make($row->name);
            if ($slug !== '' && ! Urls::isReservedCatalogSlug($slug)) {
                return $row;
            }
        }

        $this->fail('Khong co danh muc mo de kiem tra phan trang.');
    }
}
