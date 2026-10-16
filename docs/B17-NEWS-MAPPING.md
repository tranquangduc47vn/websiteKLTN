# B17 — mapping tin tức

| CI3 | Laravel |
|-----|---------|
| `controllers/News.php` | `Storefront\NewsController` |
| `controllers/Tintuc.php` | `Storefront\TintucController` |
| `controllers/admin/News.php` | `Admin\NewsController` |
| `controllers/Legacy.php` posts / post_view / admin_posts | `Storefront\LegacyController` |
| `models/News_model.php` (`content_pages`, type `news`) | `ContentPage` |
| `libraries/Upload_library.php` | `NewsImageStore` |
| `helpers/seo_url_helper.php` `build_news_*` | `Urls::newsIndex` / `newsPost` (đã có) |
| `views/site/news/index.php` | `site/news/index.blade.php` |
| `views/site/news_detail.php` | `site/news/detail.blade.php` |
| `views/site/tintuc_index.php` | `site/news/tintuc.blade.php` |
| `views/admin/posts/*` | `admin/news/index.blade.php`, `form.blade.php` |
| Không có `admin_can` | Route nằm trong `admin.auth`, không thêm permission |

URL:

- `GET /tin-tuc`, `GET /tin-tuc/{offset}` offset SQL, 8 bài
- `GET /tin-tuc/{slug}-n{id}`
- `GET /tintuc`
- `GET|POST /posts`, `/posts/view/{id}`, `/news` → 301
- `GET /admin/posts`, `POST /admin/posts/add|edit/{id}|del/{id}`
- `GET|POST /admin/news` → 301 `/admin/posts`
