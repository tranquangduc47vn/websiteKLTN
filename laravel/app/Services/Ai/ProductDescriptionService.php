<?php

namespace App\Services\Ai;

use App\Models\Catalog;
use App\Models\Product;
use App\Support\Admin\ProductImageStore;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Gợi ý mô tả sản phẩm cho admin qua Gemini (tái dùng GeminiClient).
 */
class ProductDescriptionService
{
    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly ProductImageStore $images,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, html: string, message: string, error: string}
     */
    public function generate(array $input, ?Product $product = null): array
    {
        if (! $this->allowRate()) {
            return [
                'ok' => false,
                'html' => '',
                'message' => 'Bạn thao tác quá nhanh. Vui lòng đợi vài giây rồi thử lại.',
                'error' => 'rate_limited',
            ];
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' && $product) {
            $name = trim((string) $product->name);
        }
        if ($name === '') {
            return [
                'ok' => false,
                'html' => '',
                'message' => 'Vui lòng nhập tên sản phẩm trước khi tạo mô tả.',
                'error' => 'missing_name',
            ];
        }

        $catalogName = $this->catalogName($input, $product);
        $colors = $this->stringList($input['color'] ?? $input['colors'] ?? [], $product?->colorList() ?? []);
        $sizes = $this->stringList($input['size'] ?? $input['sizes'] ?? [], $product?->sizeList() ?? []);
        $priceLine = $this->priceLine($input, $product);

        $imageParts = $this->collectImageParts($input, $product);
        $hasImages = $imageParts !== [];

        $facts = $this->buildFactsBlock($name, $catalogName, $colors, $sizes, $priceLine, $hasImages);

        $system = <<<'SYS'
Bạn là copywriter thời trang cho cửa hàng online Việt Nam.
Viết tiếng Việt tự nhiên, chuyên nghiệp, thanh lịch, trẻ trung; có sức thuyết phục thương mại vừa phải; tối ưu SEO nhẹ (không nhồi từ khóa).
Chỉ dựa trên dữ kiện được cung cấp và hình ảnh (nếu có). Không bịa chất liệu, xuất xứ, thương hiệu, công dụng, phom dáng cụ thể nếu không thấy rõ trong ảnh/dữ liệu.
Không đề cập tồn kho. Không đổi tên sản phẩm hay giá. Không suy diễn số đo cơ thể hay bảng size chuẩn.
Trả về DUY NHẤT một đoạn HTML (không markdown, không ```), gồm:
- <h2> tiêu đề hấp dẫn (giữ đúng tên sản phẩm),
- <p> giới thiệu 50–100 từ,
- <h3>Đặc điểm nổi bật</h3> + <ul><li>…</li></ul> (3–6 ý có căn cứ),
- <h3>Thông tin sản phẩm</h3> + <ul> tên, màu, size, giá (nếu có),
- <p> một câu kết khuyến khích tham khảo/lựa chọn nhẹ nhàng.
SYS;

        $userText = "Dữ kiện sản phẩm (chỉ được dùng các thông tin này):\n" . $facts;
        if (! $hasImages) {
            $userText .= "\n\nKhông có hình ảnh đính kèm — mô tả theo tên và danh mục, tránh mô tả chi tiết ngoại hình không chắc chắn.";
        } else {
            $userText .= "\n\nPhân tích hình ảnh đính kèm; chỉ mô tả đặc điểm bạn nhìn thấy rõ ràng.";
        }

        $parts = [['text' => $userText]];
        foreach ($imageParts as $imagePart) {
            $parts[] = $imagePart;
        }

        $cfg = config('ai.product_description', []);
        $generation = [
            'temperature' => (float) ($cfg['temperature'] ?? 0.45),
            'maxOutputTokens' => (int) ($cfg['max_output_tokens'] ?? 2048),
            'timeout' => max(1, (int) ($cfg['timeout'] ?? 30)),
        ];

        $result = $this->gemini->generateFromUserParts($parts, $system, $generation);
        if (
            ! $result['ok']
            && $hasImages
            && in_array($result['error'], ['timeout', 'server_error', 'rate_limited'], true)
        ) {
            $fallbackText = $userText . "\n\n(Gọi AI kèm ảnh quá chậm — hãy viết mô tả chỉ từ dữ kiện chữ, không phân tích ảnh.)";
            $result = $this->gemini->generateFromUserParts(
                [['text' => $fallbackText]],
                $system,
                $generation
            );
        }
        if (! $result['ok']) {
            return [
                'ok' => false,
                'html' => '',
                'message' => $this->humanError($result['error']),
                'error' => $result['error'],
            ];
        }

        $html = $this->normalizeModelHtml($result['content']);
        $html = HtmlSanitizer::clean($html);
        if (trim(strip_tags($html)) === '') {
            return [
                'ok' => false,
                'html' => '',
                'message' => 'AI không trả về nội dung hợp lệ. Vui lòng thử lại.',
                'error' => 'empty_response',
            ];
        }

        return [
            'ok' => true,
            'html' => $html,
            'message' => 'Đã tạo mô tả. Xem trước, chỉnh sửa nếu cần rồi chèn vào trình soạn thảo.',
            'error' => '',
        ];
    }

    private function buildFactsBlock(
        string $name,
        string $catalogName,
        array $colors,
        array $sizes,
        string $priceLine,
        bool $hasImages,
    ): string {
        $lines = ['- Tên: ' . $name];
        if ($catalogName !== '') {
            $lines[] = '- Danh mục: ' . $catalogName;
        }
        if ($colors !== []) {
            $lines[] = '- Màu: ' . implode(', ', $colors);
        }
        if ($sizes !== []) {
            $lines[] = '- Size: ' . implode(', ', $sizes);
        }
        if ($priceLine !== '') {
            $lines[] = '- Giá bán: ' . $priceLine;
        }
        $lines[] = '- Có ảnh phân tích: ' . ($hasImages ? 'có' : 'không');

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function catalogName(array $input, ?Product $product): string
    {
        $id = (int) ($input['catalog_id'] ?? $input['category_id'] ?? 0);
        if ($id <= 0 && $product) {
            $id = (int) $product->category_id;
        }
        if ($id <= 0) {
            return '';
        }

        return (string) (Catalog::query()->whereKey($id)->value('name') ?? '');
    }

    /**
     * @param  mixed  $fromRequest
     * @param  list<string>  $fromProduct
     * @return list<string>
     */
    private function stringList(mixed $fromRequest, array $fromProduct): array
    {
        $items = [];
        if (is_array($fromRequest)) {
            foreach ($fromRequest as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $items[] = $value;
                }
            }
        }
        if ($items === [] && $fromProduct !== []) {
            $items = $fromProduct;
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function priceLine(array $input, ?Product $product): string
    {
        $list = $this->parseMoney($input['price'] ?? $input['list_price'] ?? null);
        if ($list <= 0 && $product) {
            $list = (float) $product->list_price;
        }
        $discountPercent = $this->parsePercent($input['discount'] ?? null);
        if ($discountPercent <= 0 && $product && (float) $product->list_price > 0) {
            $discountPercent = (int) round(((float) $product->discount_amount / (float) $product->list_price) * 100);
        }
        if ($list <= 0) {
            return '';
        }
        $final = $list;
        if ($discountPercent > 0) {
            $final = max(0, $list - ($list * $discountPercent / 100));
        }

        return number_format($final, 0, ',', '.') . 'đ';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{inlineData: array{mimeType: string, data: string}}>
     */
    private function collectImageParts(array $input, ?Product $product): array
    {
        $maxImages = max(1, (int) config('ai.product_description.max_images', 4));
        $maxBytes = max(1, (int) config('ai.product_description.max_image_bytes', 5 * 1024 * 1024));
        $parts = [];

        $mainUpload = $input['image_upload'] ?? null;
        if ($mainUpload instanceof UploadedFile && $mainUpload->isValid()) {
            $part = $this->partFromUpload($mainUpload, $maxBytes);
            if ($part) {
                $parts[] = $part;
            }
        }

        $galleryUploads = $input['gallery_uploads'] ?? [];
        if (is_array($galleryUploads)) {
            foreach ($galleryUploads as $file) {
                if (count($parts) >= $maxImages) {
                    break;
                }
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $part = $this->partFromUpload($file, $maxBytes);
                if ($part) {
                    $parts[] = $part;
                }
            }
        }

        $mainPath = $this->images->sanitize($input['product_image_main'] ?? '');
        if ($mainPath === '' && $product) {
            $mainPath = $this->images->sanitize($product->image_link);
        }
        if ($mainPath !== '' && count($parts) < $maxImages) {
            $part = $this->partFromStoredFile($mainPath, $maxBytes);
            if ($part) {
                $parts[] = $part;
            }
        }

        $gallery = [];
        $listJson = $input['product_image_list'] ?? null;
        if (is_string($listJson) && $listJson !== '') {
            $decoded = json_decode($listJson, true);
            if (is_array($decoded)) {
                $gallery = $decoded;
            }
        }
        if ($gallery === [] && $product) {
            $gallery = $product->imageList();
        }
        foreach ($gallery as $file) {
            if (count($parts) >= $maxImages) {
                break;
            }
            $path = $this->images->sanitize((string) $file);
            if ($path === '' || $path === $mainPath) {
                continue;
            }
            $part = $this->partFromStoredFile($path, $maxBytes);
            if ($part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private function partFromUpload(UploadedFile $file, int $maxBytes): ?array
    {
        if ($file->getSize() > $maxBytes) {
            return null;
        }
        $mime = (string) $file->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }
        $bytes = @file_get_contents((string) $file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $this->buildImagePart($bytes, $mime);
    }

    private function partFromStoredFile(string $filename, int $maxBytes): ?array
    {
        $path = $this->images->directory() . DIRECTORY_SEPARATOR . $filename;
        if (! is_file($path)) {
            return null;
        }
        $size = (int) filesize($path);
        if ($size <= 0 || $size > $maxBytes) {
            return null;
        }
        $mime = (string) mime_content_type($path);
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $this->buildImagePart($bytes, $mime);
    }

    /**
     * @return array{inlineData: array{mimeType: string, data: string}}|null
     */
    private function buildImagePart(string $bytes, string $mime): ?array
    {
        $optimized = $this->optimizeImageBytes($bytes, $mime);
        if ($optimized === null) {
            return null;
        }

        return [
            'inlineData' => [
                'mimeType' => $optimized['mime'],
                'data' => base64_encode($optimized['bytes']),
            ],
        ];
    }

    /**
     * @return array{bytes: string, mime: string}|null
     */
    private function optimizeImageBytes(string $bytes, string $mime): ?array
    {
        $target = max(100_000, (int) config('ai.product_description.optimize_target_bytes', 450_000));
        $maxEdge = max(640, (int) config('ai.product_description.max_image_edge', 1280));

        if (strlen($bytes) <= $target && str_starts_with($mime, 'image/')) {
            return ['bytes' => $bytes, 'mime' => $mime];
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return strlen($bytes) <= (int) config('ai.product_description.max_image_bytes', 5 * 1024 * 1024)
                ? ['bytes' => $bytes, 'mime' => $mime]
                : null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return strlen($bytes) <= $target ? ['bytes' => $bytes, 'mime' => $mime] : null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1.0, $maxEdge / max($width, $height));
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));

        $canvas = $newW === $width && $newH === $height
            ? $source
            : imagecreatetruecolor($newW, $newH);

        if ($canvas !== $source) {
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($source);
        }

        $quality = 82;
        $out = '';
        do {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $out = (string) ob_get_clean();
            $quality -= 8;
        } while (strlen($out) > $target && $quality >= 52);
        imagedestroy($canvas);

        if ($out === '') {
            return null;
        }

        return ['bytes' => $out, 'mime' => 'image/jpeg'];
    }

    private function normalizeModelHtml(string $raw): string
    {
        $text = trim($raw);
        if (preg_match('/^```(?:html)?\s*(.*?)```$/is', $text, $m)) {
            $text = trim((string) $m[1]);
        }

        return $text;
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $digits = preg_replace('/[^\d]/', '', (string) $value);

        return $digits === '' ? 0.0 : (float) $digits;
    }

    private function parsePercent(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return max(0, min(100, (int) preg_replace('/[^\d]/', '', (string) $value)));
    }

    private function humanError(string $code): string
    {
        return match ($code) {
            'missing_api_key' => 'Chưa cấu hình khóa Gemini. Liên hệ quản trị hệ thống.',
            'timeout' => 'AI phản hồi quá lâu (mạng chậm hoặc ảnh lớn). Hệ thống có thể đã thử lại không ảnh — bấm 「Tạo lại」 hoặc chờ vài phút.',
            'server_error' => 'Máy chủ Google AI tạm bận (503). Vui lòng thử lại sau 1–2 phút.',
            'rate_limited' => 'Dịch vụ AI đang bận. Vui lòng đợi và thử lại.',
            'unauthorized' => 'Khóa API AI không hợp lệ.',
            default => 'Không tạo được mô tả. Vui lòng thử lại sau.',
        };
    }

    private function allowRate(): bool
    {
        $cfg = config('ai.product_description', []);
        $window = max(1, (int) ($cfg['rate_limit_window'] ?? 60));
        $max = max(1, (int) ($cfg['rate_limit_max'] ?? 5));
        $adminId = (int) (Auth::guard('admin')->id() ?? 0);
        $key = 'admin_product_ai_rate_' . $adminId;
        $now = time();
        $log = session($key, []);
        $log = array_values(array_filter(is_array($log) ? $log : [], fn ($ts) => ($now - (int) $ts) <= $window));
        if (count($log) >= $max) {
            session([$key => $log]);

            return false;
        }
        $log[] = $now;
        session([$key => $log]);

        return true;
    }
}