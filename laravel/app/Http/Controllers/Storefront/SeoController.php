<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\ContentPage;
use App\Models\Product;
use App\Support\Storefront\Urls;
use Illuminate\Http\Response;

/**
 * robots.txt và sitemap.xml theo APP_ENV.
 * Local và testing không phát hành sitemap production.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        if (! app()->environment('production')) {
            $body = "User-agent: *\nDisallow: /\n# local-or-test: not a production robots policy\n";

            return $this->text($body);
        }

        $lines = [
            'User-agent: *',
            'Disallow: /gio-hang',
            'Disallow: /thanh-toan',
            'Disallow: /dang-nhap',
            'Disallow: /dang-ky',
            'Disallow: /quen-mat-khau',
            'Disallow: /dat-lai-mat-khau',
            'Disallow: /user',
            'Disallow: /admin',
            'Allow: /',
            'Sitemap: ' . url('sitemap.xml'),
        ];

        return $this->text(implode("\n", $lines) . "\n");
    }

    public function sitemap(): Response
    {
        if (! app()->environment('production')) {
            return response('Sitemap is not published outside production.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $urls = [url('/')];
        foreach (Catalog::query()->orderBy('id')->get(['id', 'name']) as $catalog) {
            $urls[] = Urls::category($catalog);
        }
        foreach (Product::query()->active()->inStock()->orderBy('id')->get(['id', 'name']) as $product) {
            $urls[] = Urls::product($product);
        }
        foreach (ContentPage::query()->where('type', ContentPage::TYPE_NEWS)->orderBy('id')->get(['id', 'title']) as $post) {
            $urls[] = Urls::newsPost($post);
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach (array_unique($urls) as $url) {
            $xml[] = '<url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc></url>';
        }
        $xml[] = '</urlset>';

        return response(implode('', $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function text(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => app()->environment('production') ? 'noindex' : 'noindex, nofollow',
        ]);
    }
}
