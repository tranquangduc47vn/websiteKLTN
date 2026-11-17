<?php

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Support\Storefront\AiChatCommerce;
use App\Support\Storefront\Urls;
use Tests\Feature\Cart\CartTestCase;

class AiChatCommerceTest extends CartTestCase
{
    public function test_product_options_requires_login(): void
    {
        $product = $this->standardProduct();
        $this->getJson('/ai-assistant/product/' . $product->id . '/options')
            ->assertOk()
            ->assertJson(['ok' => false, 'login_required' => true]);
    }

    public function test_product_options_returns_variants_from_db(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->getJson('/ai-assistant/product/' . $product->id . '/options')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['colors', 'sizes', 'variants', 'default_color', 'default_size']);
    }

    public function test_actions_for_order_message(): void
    {
        $actions = AiChatCommerce::actionsForMessage('Tra cứu đơn hàng của tôi', []);
        $this->assertCount(1, $actions);
        $this->assertSame('Tra cứu đơn hàng', $actions[0]['label']);
    }

    public function test_actions_for_checkout_message(): void
    {
        $actions = AiChatCommerce::actionsForMessage('Hỏi thanh toán COD', []);
        $this->assertCount(2, $actions);
        $this->assertSame(Urls::cart(), $actions[0]['url']);
        $this->assertSame(Urls::checkout(), $actions[1]['url']);
    }

    public function test_cart_add_ajax_uses_existing_service(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();
        $token = csrf_token();

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->post('/gio-hang/them/' . $product->id, [
                '_token' => $token,
                'size' => 'S',
                'color' => 'Đen',
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['message', 'total_items', 'total_price']);

        $this->assertSame(1, $this->sessionCart()[$this->rowId($product, 'S', 'Đen')]['qty']);
    }
}