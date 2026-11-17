<?php

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Support\Storefront\AiProductCards;
use Tests\LegacyMysqlTestCase;

class AiProductCardsTest extends LegacyMysqlTestCase
{
    public function test_search_returns_real_card_fields_for_product_keyword(): void
    {
        $product = Product::query()->active()->orderByDesc('id')->first();
        if (! $product) {
            $this->markTestSkipped('No active product in DB');
        }

        $cards = AiProductCards::searchForChat('tìm sản phẩm ' . $product->name, 2);
        $this->assertNotEmpty($cards);
        $first = $cards[0];
        $this->assertArrayHasKey('image_url', $first);
        $this->assertArrayHasKey('price_fmt', $first);
        $this->assertArrayHasKey('url', $first);
        $this->assertArrayHasKey('stock_label', $first);
        $this->assertStringContainsString('đ', (string) $first['price_fmt']);
    }

    public function test_non_product_message_returns_empty_cards(): void
    {
        $this->assertSame([], AiProductCards::searchForChat('xin chào bạn khỏe không'));
    }
}