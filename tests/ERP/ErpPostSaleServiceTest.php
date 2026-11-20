<?php

namespace Tests\ERP;

use App\Models\ErpCredential;
use App\Models\ErpIdMapping;
use App\Models\ErpPostSaleSync;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Sale;
use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpPostSaleService;
use App\User;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ErpPostSaleServiceTest extends TestCase
{
    // RefreshDatabase / DatabaseTransactions JAAN BUJH KAR use nahi kiye.
    // Har test apni banayi hui rows tearDown() me khud delete karta hai.
    //
    // WARNING: RefreshDatabase kabhi mat lagana jab tak tests ek ALAG test database par
    // na chal rahe hon — wo migrate:fresh chalata hai aur connected DB ka poora data uda deta hai.

    private const REMOTE_PROJECT_ID = '555';

    private const CUSTOM_FIELD_DEFS = [
        ['label' => 'Invoice Number', 'custom_field_id' => 11],
        ['label' => 'Product/Product Variant', 'custom_field_id' => 12],
        ['label' => 'Purchase Date', 'custom_field_id' => 13],
        ['label' => 'LMS Order ID', 'custom_field_id' => 14],
    ];

    protected array $createdUserIds = [];
    protected array $createdProductIds = [];
    protected array $createdProductOrderIds = [];
    protected array $createdOrderIds = [];
    protected array $createdOrderItemIds = [];
    protected array $createdSaleIds = [];
    protected array $createdCredentialIds = [];

    protected function tearDown(): void
    {
        // Sync/mapping rows pehle (ye order/vendor ko refer karti hain).
        ErpPostSaleSync::whereIn('order_id', $this->createdOrderIds)->delete();

        ErpIdMapping::whereIn('vendor_id', $this->createdUserIds)
            ->whereIn('local_id', array_merge($this->createdOrderIds, $this->createdUserIds))
            ->delete();

        OrderItem::whereIn('id', $this->createdOrderItemIds)->delete();
        ProductOrder::whereIn('id', $this->createdProductOrderIds)->delete();
        Order::whereIn('id', $this->createdOrderIds)->delete();
        Sale::whereIn('id', $this->createdSaleIds)->delete();
        Product::whereIn('id', $this->createdProductIds)->delete();
        ErpCredential::whereIn('id', $this->createdCredentialIds)->delete();
        User::whereIn('id', $this->createdUserIds)->delete();

        Mockery::close();

        parent::tearDown();
    }

    protected function makeOrderItem(
        array $productAttrs = [],
        ?array $specifications = null,
        array $orderItemAttrs = []
    ): array {
        $vendor = User::factory()->create();
        $buyer = User::factory()->create(['email' => 'buyer_' . uniqid() . '@example.com']);

        $this->createdUserIds[] = $vendor->id;
        $this->createdUserIds[] = $buyer->id;

        $product = Product::factory()->create(array_merge([
            'creator_id' => $vendor->id,
            'erp_post_sale_enabled' => true,
            'erp_staff_ids' => [1],
            'erp_task_templates' => ['Test Task'],
            'title' => 'TESTA',
        ], $productAttrs));

        $this->createdProductIds[] = $product->id;

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
        ]);

        $this->createdOrderIds[] = $order->id;

        $orderItem = OrderItem::factory()->create(array_merge([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'total_amount' => 100,
        ], $orderItemAttrs));

        $this->createdOrderItemIds[] = $orderItem->id;

        $productOrder = ProductOrder::create([
            'product_id' => $product->id,
            'seller_id' => $vendor->id,
            'quantity' => 1,
            'status' => 'pending',
            'created_at' => time(),
            'specifications' => $specifications ? json_encode($specifications) : null,
        ]);

        $this->createdProductOrderIds[] = $productOrder->id;

        $orderItem->update([
            'product_order_id' => $productOrder->id,
        ]);

        $orderItem->refresh();

        $sale = Sale::factory()->create([
            'seller_id' => $vendor->id,
            'buyer_id' => $buyer->id,
        ]);

        $this->createdSaleIds[] = $sale->id;

        return compact(
            'vendor',
            'buyer',
            'product',
            'order',
            'orderItem',
            'sale'
        );
    }

    protected function makeRealCredential(int $vendorId): ErpCredential
    {
        $baseUrl = env('ERP_TEST_BASE_URL');
        $apiKey = env('ERP_TEST_API_KEY');

        if (empty($baseUrl) || empty($apiKey)) {
            // Ye PHPUnit ka apna fail() hai (message ke saath) — isko rename NAHI karna.
            $this->fail('ERP_TEST_BASE_URL / ERP_TEST_API_KEY .env mein set nahi hain.');
        }

        return $this->storeCredential($vendorId, rtrim($baseUrl, '/'), $apiKey);
    }

    /** Fake credential — mocked tests ke liye (koi HTTP call nahi hoti). */
    protected function makeFakeCredential(int $vendorId): ErpCredential
    {
        return $this->storeCredential($vendorId, 'https://erp.invalid', 'fake-api-key');
    }

    private function storeCredential(int $vendorId, string $baseUrl, string $apiKey): ErpCredential
    {
        $credential = ErpCredential::factory()->create([
            'vendor_id' => $vendorId,
            'type' => 'import_export',
            'is_active' => true,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
        ]);

        $this->createdCredentialIds[] = $credential->id;

        return $credential;
    }

    // -------------------------------------------------------------------------
    // Helpers for mocked (no-HTTP) retry tests
    // -------------------------------------------------------------------------

    /**
     * Service ka partial mock: makeClient() har call par agla ErpClient mock deta hai,
     * aur resolveErpClientId() fixed id deta hai (customer lookup/create ka HTTP nahi).
     */
    private function serviceWithClients(MockInterface ...$clients): ErpPostSaleService
    {
        $service = Mockery::mock(ErpPostSaleService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('makeClient')->andReturnValues($clients);
        $service->shouldReceive('resolveErpClientId')->andReturn('77');

        return $service;
    }

    private function ok(array $body = []): array
    {
        return ['success' => true, 'status' => 200, 'body' => $body];
    }

    /**
     * Failed ERP response. NOTE: naam fail() nahi rakha kyunke PHPUnit ke
     * static Assert::fail() se clash karta hai.
     */
    private function failedResponse(): array
    {
        return ['success' => false, 'status' => 500, 'error' => 'boom', 'body' => []];
    }

    /** 3 task templates wala fixture + fake credential. */
    private function makeThreeTaskFixture(): array
    {
        $data = $this->makeOrderItem([
            'erp_task_templates' => ['Task A', 'Task B', 'Task C'],
            'erp_staff_ids' => [1],
        ]);

        $this->makeFakeCredential($data['vendor']->id);

        return [$data['sale'], $data['orderItem'], $data['order']];
    }

    /** Pehli attempt ka client jisme project + saare tasks OK, custom fields fetch FAIL => partial. */
    private function partialFirstClient(): MockInterface
    {
        $client = Mockery::mock(ErpClient::class);
        $client->shouldReceive('getCustomFields')->once()->andReturn($this->failedResponse());
        $client->shouldReceive('createProject')->once()
            ->andReturn($this->ok(['project_id' => self::REMOTE_PROJECT_ID]));
        $client->shouldReceive('createTask')->times(3)->andReturn($this->ok());

        return $client;
    }

    // -------------------------------------------------------------------------
    // LIVE test (real Perfex) — unchanged behaviour
    // -------------------------------------------------------------------------

    /**
     * Real ERP hit: koi Http::fake() nahi. Customer create/lookup, project create,
     * aur task create sab live. NOTE: Perfex me bane hue records local tearDown se
     * delete nahi hote — wo Perfex se manually hatane honge.
     *
     * @group live
     */
    public function test_real_sync_creates_project_and_tasks_on_live_erp(): void
    {
        ['vendor' => $vendor, 'orderItem' => $orderItem, 'sale' => $sale] =
            $this->makeOrderItem();

        $this->makeRealCredential($vendor->id);

        $service = app(ErpPostSaleService::class);
        $sync = $service->syncSale($sale, $orderItem);

        dump([
            'status' => $sync?->status,
            'error_message' => $sync?->error_message,
            'remote_project_id' => $sync?->remote_project_id,
            'response_payload' => $sync?->response_payload,
        ]);

        $this->assertNotNull($sync, 'syncSale() ne null return kiya — matlab product/order/buyer resolve hi nahi hue.');
        $this->assertContains($sync->status, ['success', 'partial'], 'Sync fail ho gaya: ' . $sync->error_message);
        $this->assertNotEmpty($sync->remote_project_id, 'Perfex se project id wapas nahi aayi.');
    }

    // -------------------------------------------------------------------------
    // RETRY regression tests (mocked ErpClient, NO live HTTP)
    // -------------------------------------------------------------------------

    public function test_retry_does_not_create_duplicate_project_or_duplicate_tasks(): void
    {
        [$sale, $orderItem] = $this->makeThreeTaskFixture();

        // Attempt 1: project OK, Task A + B OK, Task C FAIL.
        $first = Mockery::mock(ErpClient::class);
        $first->shouldReceive('getCustomFields')->once()->andReturn($this->ok(self::CUSTOM_FIELD_DEFS));
        $first->shouldReceive('createProject')->once()
            ->andReturn($this->ok(['project_id' => self::REMOTE_PROJECT_ID]));

        $created = [];
        $first->shouldReceive('createTask')->times(3)->andReturnUsing(function (array $p) use (&$created) {
            if ($p['name'] === 'Task C') {
                return $this->failedResponse();
            }
            $created[] = $p['name'];

            return $this->ok();
        });

        // Attempt 2 (retry): createProject / getCustomFields NEVER, createTask sirf Task C ke liye.
        $second = Mockery::mock(ErpClient::class);
        $second->shouldReceive('createProject')->never();
        $second->shouldReceive('getCustomFields')->never();
        $second->shouldReceive('createTask')->once()
            ->with(Mockery::on(fn (array $p) => $p['name'] === 'Task C' && (string) $p['rel_id'] === self::REMOTE_PROJECT_ID))
            ->andReturn($this->ok());

        $service = $this->serviceWithClients($first, $second);

        $sync = $service->syncSale($sale, $orderItem)->fresh();
        $this->assertSame('failed', $sync->status);
        $this->assertSame(self::REMOTE_PROJECT_ID, (string) $sync->remote_project_id);
        $this->assertSame(['Task A', 'Task B'], $created);

        $sync = $service->syncSale($sale, $orderItem)->fresh();

        $this->assertSame('success', $sync->status);
        $this->assertSame(self::REMOTE_PROJECT_ID, (string) $sync->remote_project_id);
        $this->assertSame(1, ErpPostSaleSync::where('order_id', $orderItem->order_id)->count());
    }

    public function test_partial_status_is_not_converted_to_success_when_custom_fields_still_fail(): void
    {
        [$sale, $orderItem] = $this->makeThreeTaskFixture();

        $first = $this->partialFirstClient();

        // Retry: custom fields fetch phir FAIL. Na project, na task dobara.
        $second = Mockery::mock(ErpClient::class);
        $second->shouldReceive('getCustomFields')->once()->andReturn($this->failedResponse());
        $second->shouldReceive('createProject')->never();
        $second->shouldReceive('createTask')->never();
        $second->shouldReceive('updateProject')->never();

        $service = $this->serviceWithClients($first, $second);

        $sync = $service->syncSale($sale, $orderItem)->fresh();
        $this->assertSame('partial', $sync->status);
        $this->assertFalse($sync->response_payload['custom_fields_applied']);

        $sync = $service->syncSale($sale, $orderItem)->fresh();

        // Retry ke baad bhi partial — success NAHI.
        $this->assertSame('partial', $sync->status);
        $this->assertFalse($sync->response_payload['custom_fields_applied']);
        $this->assertStringContainsString('custom fields', $sync->error_message);
        $this->assertSame(self::REMOTE_PROJECT_ID, (string) $sync->remote_project_id);
    }

    /**
     * Is test ke liye ErpClient me updateProject($projectId, array $payload) hona chahiye.
     */
    public function test_partial_custom_fields_is_restored_on_retry_when_fetch_recovers(): void
    {
        [$sale, $orderItem] = $this->makeThreeTaskFixture();

        $first = $this->partialFirstClient();

        $second = Mockery::mock(ErpClient::class);
        $second->shouldReceive('getCustomFields')->once()->andReturn($this->ok(self::CUSTOM_FIELD_DEFS));
        $second->shouldReceive('createProject')->never();
        $second->shouldReceive('createTask')->never();
        $second->shouldReceive('updateProject')->once()
            ->with(
                self::REMOTE_PROJECT_ID,
                Mockery::on(fn (array $p) => !empty($p['custom_fields']['projects']))
            )
            ->andReturn($this->ok());

        $service = $this->serviceWithClients($first, $second);

        $this->assertSame('partial', $service->syncSale($sale, $orderItem)->fresh()->status);

        $sync = $service->syncSale($sale, $orderItem)->fresh();

        $this->assertSame('success', $sync->status);
        $this->assertTrue($sync->response_payload['custom_fields_applied']);
        $this->assertNull($sync->error_message);
    }

    public function test_partial_stays_partial_when_custom_fields_update_call_fails(): void
    {
        [$sale, $orderItem] = $this->makeThreeTaskFixture();

        $first = $this->partialFirstClient();

        $second = Mockery::mock(ErpClient::class);
        $second->shouldReceive('getCustomFields')->once()->andReturn($this->ok(self::CUSTOM_FIELD_DEFS));
        $second->shouldReceive('updateProject')->once()->andReturn($this->failedResponse());
        $second->shouldReceive('createProject')->never();
        $second->shouldReceive('createTask')->never();

        $service = $this->serviceWithClients($first, $second);

        $service->syncSale($sale, $orderItem);
        $sync = $service->syncSale($sale, $orderItem)->fresh();

        $this->assertSame('partial', $sync->status);
        $this->assertFalse($sync->response_payload['custom_fields_applied']);
    }
}