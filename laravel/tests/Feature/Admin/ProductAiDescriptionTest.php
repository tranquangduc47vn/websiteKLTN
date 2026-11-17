<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
class ProductAiDescriptionTest extends AdminTestCase
{
    private const KEY = 'b16-ai-desc-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.gemini_api_key' => self::KEY]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_cannot_generate_description(): void
    {
        $this->postJson('/admin/products/ai-description', ['name' => 'SP test'])
            ->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_staff_can_generate_with_sanitized_html(): void
    {
        $html = '<h2>Áo test</h2><p>Mô tả B16.</p><script>alert(1)</script>';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $html]]]]],
            ], 200),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin');
        $product = Product::query()->where('status', 'active')->orderByDesc('id')->first();
        $this->assertNotNull($product);

        $response = $this->postJson('/admin/products/ai-description', [
            'product_id' => $product->id,
            'name' => $product->name,
            'catalog_id' => $product->category_id,
        ])->assertOk()->json();

        $this->assertTrue($response['ok']);
        $this->assertStringContainsString('Mô tả B16', (string) $response['html']);
        $this->assertStringNotContainsString('script', strtolower((string) $response['html']));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', self::KEY));
    }

    public function test_missing_name_returns_validation_error(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');
        $this->postJson('/admin/products/ai-description', [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'error' => 'missing_name']);
        Http::assertNothingSent();
    }

    public function test_missing_api_key_returns_friendly_message(): void
    {
        config(['ai.gemini_api_key' => '']);
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->postJson('/admin/products/ai-description', ['name' => 'Áo B16 không key'])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'missing_api_key',
            ]);
        Http::assertNothingSent();
    }
}