<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Đánh giá sản phẩm. Port product_review_model + product/raty.
 * Khách được gửi. Không bắt đã mua. Một user (hoặc một session khách) một review / sản phẩm.
 * Review mới ở trạng thái chờ duyệt. Sao trung bình chỉ tính review đã duyệt.
 */
class ReviewService
{
    /**
     * @return array{reviews: \Illuminate\Support\Collection, breakdown: array<int, int>, user_review: ProductReview|null, pending: bool}
     */
    public function forProduct(int $productId, string $guestToken): array
    {
        $own = $this->ownReview($productId, $guestToken);
        $breakdown = $this->breakdown($productId);
        $count = array_sum($breakdown);
        $total = 0;
        foreach ($breakdown as $star => $n) {
            $total += $star * $n;
        }

        return [
            'reviews' => ProductReview::query()
                ->where('product_id', $productId)
                ->where('status', ProductReview::STATUS_APPROVED)
                ->orderByDesc('created')
                ->limit(50)
                ->get(),
            'breakdown' => $breakdown,
            'rate_count' => $count,
            'rate_total' => $total,
            'user_review' => $own,
            'pending' => $own !== null && (int) $own->status === ProductReview::STATUS_PENDING,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(int $productId, int $score, string $comment, string $guestToken): array
    {
        $fail = static fn (string $msg): array => ['complete' => false, 'msg' => $msg];

        if ($productId <= 0 || $score < 1 || $score > 5) {
            return $fail('Dữ liệu đánh giá không hợp lệ');
        }
        if ($comment === '') {
            return $fail('Vui lòng nhập nội dung đánh giá');
        }
        if (mb_strlen($comment) > 2000) {
            return $fail('Nội dung đánh giá tối đa 2000 ký tự');
        }
        if (! Product::query()->where('id', $productId)->exists()) {
            return $fail('Sản phẩm không tồn tại');
        }

        $user = Auth::guard('web')->user();
        $userId = $user instanceof User ? (int) $user->id : 0;
        $payload = [
            'stars' => $score,
            'user_name' => ($user instanceof User && $user->name !== '') ? (string) $user->name : 'Khách hàng',
            'comment' => $comment,
            'created' => time(),
            'status' => ProductReview::STATUS_PENDING,
            'reviewed_at' => 0,
            'reviewed_by' => 0,
        ];

        $existing = $this->ownReview($productId, $guestToken);
        if ($existing) {
            if ((int) $existing->stars === $score
                && trim((string) $existing->comment) === $comment
                && (int) $existing->status === ProductReview::STATUS_PENDING) {
                return [
                    'complete' => true,
                    'pending' => true,
                    'msg' => 'Bạn đang đánh giá ' . $score . ' sao',
                ];
            }
            $existing->fill($payload)->save();
            $this->syncProductStats($productId);

            return [
                'complete' => true,
                'updated' => true,
                'pending' => true,
                'msg' => 'Đã cập nhật đánh giá thành ' . $score . ' sao',
            ];
        }

        ProductReview::query()->create(array_merge($payload, [
            'product_id' => $productId,
            'user_id' => $userId,
            'session_token' => $userId > 0 ? '' : $guestToken,
        ]));
        $this->syncProductStats($productId);

        return [
            'complete' => true,
            'pending' => true,
            'msg' => 'Cảm ơn bạn đã đánh giá ' . $score . ' sao',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function undo(int $productId, string $guestToken): array
    {
        if ($productId <= 0) {
            return ['complete' => false, 'msg' => 'Dữ liệu không hợp lệ'];
        }
        if (! Product::query()->where('id', $productId)->exists()) {
            return ['complete' => false, 'msg' => 'Sản phẩm không tồn tại'];
        }

        $existing = $this->ownReview($productId, $guestToken);
        if (! $existing) {
            return ['complete' => false, 'msg' => 'Bạn chưa đánh giá sản phẩm này'];
        }
        $existing->delete();
        $this->syncProductStats($productId);

        return ['complete' => true, 'msg' => 'Đã hoàn tác đánh giá. Bạn có thể đánh giá lại.'];
    }

    public function ownReview(int $productId, string $guestToken): ?ProductReview
    {
        $user = Auth::guard('web')->user();
        $query = ProductReview::query()->where('product_id', $productId);
        if ($user instanceof User) {
            return $query->where('user_id', (int) $user->id)->first();
        }
        if ($guestToken === '') {
            return null;
        }

        return $query->where('user_id', 0)->where('session_token', $guestToken)->first();
    }

    /**
     * @return array<int, int>
     */
    public function breakdown(int $productId): array
    {
        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $rows = DB::table('product_review')
            ->selectRaw('stars, COUNT(*) AS total')
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED)
            ->groupBy('stars')
            ->get();
        foreach ($rows as $row) {
            $star = (int) $row->stars;
            if ($star >= 1 && $star <= 5) {
                $breakdown[$star] = (int) $row->total;
            }
        }

        return $breakdown;
    }

    public function syncProductStats(int $productId): void
    {
        $row = DB::table('product_review')
            ->where('product_id', $productId)
            ->where('status', ProductReview::STATUS_APPROVED)
            ->selectRaw('COUNT(*) AS rate_count, COALESCE(SUM(stars), 0) AS rate_total')
            ->first();

        Product::query()->where('id', $productId)->update([
            'rate_count' => (int) ($row->rate_count ?? 0),
            'rate_total' => (int) ($row->rate_total ?? 0),
        ]);
    }

    /**
     * @return array{list: \Illuminate\Support\Collection, counts: array{pending: int, approved: int, rejected: int}}
     */
    public function adminList(string $status, string $keyword): array
    {
        $query = DB::table('product_review')
            ->leftJoin('product', 'product.id', '=', 'product_review.product_id')
            ->select(['product_review.*', 'product.name as product_name']);

        if ($status === 'pending') {
            $query->where('product_review.status', ProductReview::STATUS_PENDING);
        } elseif ($status === 'approved') {
            $query->where('product_review.status', ProductReview::STATUS_APPROVED);
        } elseif ($status === 'rejected') {
            $query->where('product_review.status', ProductReview::STATUS_REJECTED);
        }

        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $query->where(function ($inner) use ($like, $keyword) {
                $inner->where('product.name', 'like', $like)
                    ->orWhere('product_review.user_name', 'like', $like)
                    ->orWhere('product_review.comment', 'like', $like)
                    ->orWhere('product_review.product_id', (int) $keyword);
            });
        }

        return [
            'list' => $query->orderByDesc('product_review.created')->limit(100)->get(),
            'counts' => [
                'pending' => (int) ProductReview::query()->where('status', ProductReview::STATUS_PENDING)->count(),
                'approved' => (int) ProductReview::query()->where('status', ProductReview::STATUS_APPROVED)->count(),
                'rejected' => (int) ProductReview::query()->where('status', ProductReview::STATUS_REJECTED)->count(),
            ],
        ];
    }

    public function setStatus(int $id, int $status, int $adminId): bool
    {
        $review = ProductReview::query()->find($id);
        if (! $review) {
            return false;
        }
        $review->fill([
            'status' => $status,
            'reviewed_at' => time(),
            'reviewed_by' => $adminId,
        ])->save();
        $this->syncProductStats((int) $review->product_id);

        return true;
    }

    public function delete(int $id): bool
    {
        $review = ProductReview::query()->find($id);
        if (! $review) {
            return false;
        }
        $productId = (int) $review->product_id;
        $review->delete();
        $this->syncProductStats($productId);

        return true;
    }
}
