<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Danh sách và chi tiết tin tức. Offset trên URL là OFFSET SQL, 8 bài một trang.
 */
class NewsController extends Controller
{
    private const PER_PAGE = 8;

    public function index(Request $request, int $offset = 0): View
    {
        $offset = max(0, $offset);
        $query = ContentPage::query()->where('type', ContentPage::TYPE_NEWS)->orderByDesc('id');

        return view('site.news.index', [
            'sf_temp' => 'site/news/index',
            'posts' => (clone $query)->offset($offset)->limit(self::PER_PAGE)->get(),
            'total' => (clone $query)->count(),
            'offset' => $offset,
            'perPage' => self::PER_PAGE,
        ]);
    }

    public function view(Request $request, string $slug, int $id)
    {
        $post = ContentPage::query()
            ->where('type', ContentPage::TYPE_NEWS)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('legacy_id', $id);
            })
            ->orderByRaw('(id = ?) desc', [$id])
            ->first();

        if (! $post) {
            return redirect('/');
        }

        return view('site.news.detail', [
            'sf_temp' => 'site/news_detail',
            'page_title' => $post->title,
            'post' => $post,
            'other_news' => ContentPage::query()->where('type', ContentPage::TYPE_NEWS)->orderByDesc('id')->limit(6)->get(),
        ]);
    }
}
