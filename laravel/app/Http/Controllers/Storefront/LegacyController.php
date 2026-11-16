<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Storefront\Urls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirect 301 từ URL cũ sang URL SEO (CI3 application/controllers/Legacy.php).
 *
 * Giỏ, đơn, tin tức và URL sản phẩm/danh mục cũ chuyển 301.
 */
class LegacyController extends Controller
{
    /** CI3 Legacy::cart(): /cart → /gio-hang */
    public function cart(): RedirectResponse
    {
        return redirect()->to(Urls::cart(), 301);
    }

    /** CI3 Legacy::cart_path(): /cart/{path} → /gio-hang/{path} */
    public function cartPath(Request $request, string $path = ''): RedirectResponse
    {
        return redirect()->to(url('gio-hang' . ($path !== '' ? '/' . $path : '')), 301);
    }

    /** CI3 Legacy::order(): /order → /thanh-toan */
    public function order(): RedirectResponse
    {
        return redirect()->to(Urls::checkout(), 301);
    }

    /** CI3 Legacy::order_path(): /order/{path} → /thanh-toan/{path} */
    public function orderPath(Request $request, string $path = ''): RedirectResponse
    {
        return redirect()->to(url('thanh-toan' . ($path !== '' ? '/' . $path : '')), 301);
    }

    /** CI3 Legacy::posts(): /posts và /news → /tin-tuc */
    public function posts(Request $request, string $path = ''): RedirectResponse
    {
        $target = 'tin-tuc' . ($path !== '' ? '/' . ltrim($path, '/') : '');

        return redirect()->to(url($target), 301);
    }

    /** CI3 Legacy::posts_page() */
    public function postsPage(int $offset = 0): RedirectResponse
    {
        return redirect()->to(url($offset > 0 ? 'tin-tuc/' . $offset : 'tin-tuc'), 301);
    }

    /** CI3 Legacy::post_view() */
    public function postView(int $id = 0): RedirectResponse
    {
        $post = \App\Models\ContentPage::query()
            ->where('type', \App\Models\ContentPage::TYPE_NEWS)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('legacy_id', $id);
            })
            ->first();

        return redirect()->to($post ? \App\Support\Storefront\Urls::newsPost($post) : url('tin-tuc'), 301);
    }

    /** CI3 Legacy::admin_posts(): /admin/news → /admin/posts */
    public function adminPosts(Request $request, string $path = ''): RedirectResponse
    {
        $target = 'admin/posts' . ($path !== '' ? '/' . ltrim($path, '/') : '');

        return redirect()->to(url($target), 301);
    }

    /** CI3 Legacy::catalog() và catalog_c(): /product/catalog/{id} và /{slug}-c{id}. */
    public function catalog(Request $request, int $catalogId = 0, int $offset = 0): RedirectResponse
    {
        $price = $request->query('price_range');
        $target = Urls::category($catalogId, is_string($price) ? $price : null);
        $path = (string) parse_url($target, PHP_URL_PATH);
        if (str_contains($path, '/product/catalog/')) {
            return redirect()->to(url('/'), 301);
        }
        if ($offset > 0) {
            $target = rtrim($target, '/') . '/' . $offset;
        }

        $query = $request->query();
        unset($query['price_range']);
        if ($query !== []) {
            $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($query);
        }

        return redirect()->to($target, 301);
    }

    /** CI3 Legacy::catalog_c(). Slug trên URL bị bỏ, chỉ dùng id. */
    public function catalogC(Request $request, string $slug = '', int $id = 0, int $offset = 0): RedirectResponse
    {
        return $this->catalog($request, $id, $offset);
    }

    /** CI3 Legacy::product(): /product/view/{id} và /product/detail/{id}. */
    public function product(int $id = 0): RedirectResponse
    {
        return redirect()->to(Urls::product($id), 301);
    }

    /** CI3 Legacy::product_search(). */
    public function productSearch(Request $request): RedirectResponse
    {
        $keyword = trim((string) $request->query('key-search', ''));
        $page = (int) $request->query('per_page', 0);

        return redirect()->to(Urls::search($keyword, $page > 0 ? $page : 0), 301);
    }
}
