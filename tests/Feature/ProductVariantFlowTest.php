<?php

namespace Tests\Feature;

use App\Livewire\PosPage;
use App\Livewire\ProductsPage;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantFlowTest extends TestCase
{
    use RefreshDatabase;

    private function business(): array
    {
        $outlet = Outlet::create(['name' => 'Outlet Varian', 'code' => 'VAR']);
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@variant.test', 'role' => 'owner', 'password' => 'password']);
        $cashier = User::create(['name' => 'Kasir', 'email' => 'cashier@variant.test', 'role' => 'cashier', 'outlet_id' => $outlet->id, 'password' => 'password']);
        $category = Category::create(['name' => 'Kiloan']);
        $product = Product::create(['name' => 'Laundry Kiloan', 'category_id' => $category->id, 'unit' => 'kg', 'price' => 8000, 'minimum_quantity' => 3, 'rounding_increment' => .5, 'duration_hours' => 72]);
        $customer = Customer::create(['name' => 'Budi', 'phone' => '08123456789', 'outlet_id' => $outlet->id]);

        return compact('outlet', 'owner', 'cashier', 'category', 'product', 'customer');
    }

    public function test_owner_can_create_a_product_with_multiple_service_variants(): void
    {
        ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = $this->business();
        $serviceLevels = ServiceLevel::query()->pluck('id', 'name');

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openForm')
            ->assertSee('Reguler — 72 jam')
            ->assertSee('One Day — 24 jam')
            ->assertDontSee('Durasi (jam)')
            ->set('name', 'Cuci Komplit')
            ->set('category_id', $category->id)
            ->set('outlet_id', $outlet->id)
            ->set('unit', 'kg')
            ->set('minimum_quantity', 3)
            ->set('rounding_increment', .5)
            ->set('variants', [
                ['id' => null, 'service_level_id' => $serviceLevels['Reguler'], 'price' => 8000, 'is_active' => true],
                ['id' => null, 'service_level_id' => $serviceLevels['One Day'], 'price' => 12000, 'is_active' => true],
                ['id' => null, 'service_level_id' => $serviceLevels['Express'], 'price' => 16000, 'is_active' => true],
                ['id' => null, 'service_level_id' => $serviceLevels['Quick'], 'price' => 20000, 'is_active' => true],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $product = Product::where('name', 'Cuci Komplit')->firstOrFail();
        $this->assertSame(8000, $product->price);
        $this->assertSame(72, $product->duration_hours);
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'name' => 'Quick', 'price' => 20000, 'duration_hours' => 3]);
    }

    public function test_product_cannot_use_the_same_master_variant_twice(): void
    {
        ['owner' => $owner] = $this->business();
        $regulerId = ServiceLevel::where('name', 'Reguler')->value('id');

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openForm')
            ->set('name', 'Produk Tidak Valid')
            ->set('variants', [
                ['id' => null, 'service_level_id' => $regulerId, 'price' => 8000, 'is_active' => true],
                ['id' => null, 'service_level_id' => $regulerId, 'price' => 9000, 'is_active' => true],
            ])
            ->call('save')
            ->assertHasErrors(['variants.1.service_level_id']);

        $this->assertDatabaseMissing('products', ['name' => 'Produk Tidak Valid']);
    }

    public function test_product_requires_at_least_one_active_variant(): void
    {
        ['owner' => $owner] = $this->business();
        $serviceLevels = ServiceLevel::query()->pluck('id', 'name');

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openForm')
            ->set('name', 'Produk Nonaktif')
            ->set('variants', [
                ['id' => null, 'service_level_id' => $serviceLevels['Reguler'], 'price' => 8000, 'is_active' => false],
                ['id' => null, 'service_level_id' => $serviceLevels['Quick'], 'price' => 20000, 'is_active' => false],
            ])
            ->call('save')
            ->assertHasErrors(['variants']);

        $this->assertDatabaseMissing('products', ['name' => 'Produk Nonaktif']);
    }

    public function test_pos_defaults_to_the_first_variant_and_uses_the_selected_price_and_duration(): void
    {
        ['cashier' => $cashier, 'product' => $product, 'customer' => $customer] = $this->business();
        $product->variants()->createMany([
            ['name' => 'Reguler', 'price' => 8000, 'duration_hours' => 72, 'is_active' => true, 'sort_order' => 0],
            ['name' => 'Express', 'price' => 16000, 'duration_hours' => 6, 'is_active' => true, 'sort_order' => 1],
        ]);
        $express = $product->variants()->where('name', 'Express')->firstOrFail();
        $this->travelTo(now()->startOfHour());
        $expectedDueAt = now()->addHours(6);

        $component = Livewire::actingAs($cashier)->test(PosPage::class)
            ->call('addProduct', $product->id)
            ->assertSet('hasUnselectedVariants', false)
            ->assertSet('cart.'.$product->id.'.variant_name', 'Reguler')
            ->assertSet('total', 24000)
            ->set('customerId', $customer->id)
            ->call('selectVariant', (string) $product->id, $express->id)
            ->assertSet('cart.'.$product->id.'.variant_name', 'Express')
            ->assertSet('total', 48000)
            ->set('paymentAmount', 48000)
            ->call('saveOrder', true)
            ->assertHasNoErrors()
            ->assertDispatched('print-receipt', fn (string $event, array $parameters): bool => str_contains($parameters['text'], 'Express - 6 jam') && ! str_contains($parameters['fallbackUrl'], 'autoprint'))
            ->assertSee('TRANSAKSI BERHASIL')
            ->assertSee('Detail transaksi Budi');

        $component->assertSet('cart', []);
        $order = Order::latest('id')->firstOrFail();
        $this->assertTrue($order->due_at->equalTo($expectedDueAt));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $express->id,
            'variant_name' => 'Express',
            'duration_hours' => 6,
            'unit_price' => 16000,
            'subtotal' => 48000,
        ]);
    }

    public function test_selected_variant_is_restored_with_the_cart(): void
    {
        ['cashier' => $cashier, 'product' => $product] = $this->business();
        $product->variants()->create(['name' => 'Reguler', 'price' => 8000, 'duration_hours' => 72, 'is_active' => true, 'sort_order' => 0]);
        $quick = $product->variants()->create(['name' => 'Quick', 'price' => 20000, 'duration_hours' => 3, 'is_active' => true, 'sort_order' => 1]);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->call('addProduct', $product->id)
            ->call('selectVariant', (string) $product->id, $quick->id);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->assertSet('cart.'.$product->id.'.product_variant_id', $quick->id)
            ->assertSet('cart.'.$product->id.'.variant_name', 'Quick')
            ->assertSet('total', 60000);
    }

    public function test_pos_cannot_apply_a_variant_from_another_product(): void
    {
        ['cashier' => $cashier, 'product' => $product, 'category' => $category] = $this->business();
        $product->variants()->createMany([
            ['name' => 'Reguler', 'price' => 8000, 'duration_hours' => 72, 'is_active' => true],
            ['name' => 'Quick', 'price' => 20000, 'duration_hours' => 3, 'is_active' => true],
        ]);
        $otherProduct = Product::create(['name' => 'Produk Lain', 'category_id' => $category->id, 'unit' => 'kg', 'price' => 5000, 'minimum_quantity' => 1, 'rounding_increment' => 0, 'duration_hours' => 24]);
        $otherVariant = $otherProduct->variants()->create(['name' => 'Varian Lain', 'price' => 99999, 'duration_hours' => 1, 'is_active' => true]);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->call('addProduct', $product->id)
            ->call('selectVariant', (string) $product->id, $otherVariant->id)
            ->assertSet('cart.'.$product->id.'.variant_name', 'Reguler')
            ->assertSet('cart.'.$product->id.'.price', 8000)
            ->assertSet('hasUnselectedVariants', false);
    }

    public function test_variant_snapshot_is_visible_on_the_receipt_after_master_data_changes(): void
    {
        ['cashier' => $cashier, 'outlet' => $outlet, 'product' => $product, 'customer' => $customer] = $this->business();
        $variant = $product->variants()->create(['name' => 'One Day', 'price' => 12000, 'duration_hours' => 24, 'is_active' => true]);
        $order = Order::create([
            'number' => 'VAR-001', 'outlet_id' => $outlet->id, 'customer_id' => $customer->id,
            'user_id' => $cashier->id, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone,
            'subtotal' => 36000, 'total' => 36000, 'paid_amount' => 0, 'due_at' => now()->addDay(),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name,
            'variant_name' => 'One Day', 'duration_hours' => 24, 'unit' => 'kg', 'quantity' => 3,
            'unit_price' => 12000, 'subtotal' => 36000,
        ]);
        $variant->update(['name' => 'Nama Baru', 'price' => 99999, 'duration_hours' => 1]);

        $this->actingAs($cashier)->get(route('transactions.receipt', $order))
            ->assertOk()
            ->assertSee('One Day')
            ->assertSee('24 jam')
            ->assertDontSee('Nama Baru');
    }

    public function test_owner_can_soft_delete_a_product_without_removing_transaction_history(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'product' => $product, 'customer' => $customer] = $this->business();
        $variant = $product->variants()->create(['name' => 'Reguler', 'price' => 8000, 'duration_hours' => 72, 'is_active' => true]);
        $order = Order::create([
            'number' => 'DELETE-001', 'outlet_id' => $outlet->id, 'customer_id' => $customer->id,
            'user_id' => $cashier->id, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone,
            'subtotal' => 24000, 'total' => 24000, 'paid_amount' => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name,
            'variant_name' => $variant->name, 'duration_hours' => 72, 'unit' => 'kg', 'quantity' => 3,
            'unit_price' => 8000, 'subtotal' => 24000,
        ]);

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('confirmDeleteProduct', $product->id)
            ->assertSet('deletingProductId', $product->id)
            ->assertSee('Hapus Laundry Kiloan?')
            ->call('deleteProduct')
            ->assertSet('deletingProductId', null)
            ->assertDispatched('notify')
            ->assertDontSee($product->name);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertModelExists($variant);
        $this->assertModelExists($orderItem);
        $this->assertTrue($orderItem->fresh()->product->is($product));
    }

    public function test_cashier_cannot_execute_the_product_delete_action(): void
    {
        ['cashier' => $cashier, 'product' => $product] = $this->business();

        Livewire::actingAs($cashier)->test(ProductsPage::class)
            ->call('confirmDeleteProduct', $product->id)
            ->assertStatus(403);

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_owner_can_manage_service_level_master_from_the_product_page(): void
    {
        ['owner' => $owner] = $this->business();

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openServiceLevelMaster')
            ->assertSet('showServiceLevelMaster', true)
            ->assertSee('Jenis & durasi layanan', false)
            ->assertSee('Reguler')
            ->set('serviceLevelName', 'Same Day')
            ->set('serviceLevelDurationHours', 12)
            ->call('saveServiceLevel')
            ->assertHasNoErrors()
            ->assertDispatched('notify')
            ->call('closeServiceLevelMaster')
            ->call('openForm')
            ->assertSee('Same Day — 12 jam');

        $this->assertDatabaseHas('service_levels', ['name' => 'Same Day', 'duration_hours' => 12]);
    }

    public function test_editing_service_level_updates_product_variants_but_not_order_snapshots(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'product' => $product, 'customer' => $customer] = $this->business();
        $serviceLevel = ServiceLevel::where('name', 'Reguler')->firstOrFail();
        $variant = $product->variants()->create([
            'service_level_id' => $serviceLevel->id, 'name' => 'Reguler', 'price' => 8000,
            'duration_hours' => 72, 'is_active' => true,
        ]);
        $order = Order::create([
            'number' => 'MASTER-001', 'outlet_id' => $outlet->id, 'customer_id' => $customer->id,
            'user_id' => $cashier->id, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone,
            'subtotal' => 24000, 'total' => 24000, 'paid_amount' => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name,
            'variant_name' => 'Reguler', 'duration_hours' => 72, 'unit' => 'kg', 'quantity' => 3,
            'unit_price' => 8000, 'subtotal' => 24000,
        ]);

        Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openServiceLevelMaster')
            ->call('editServiceLevel', $serviceLevel->id)
            ->set('serviceLevelName', 'Reguler Baru')
            ->set('serviceLevelDurationHours', 48)
            ->call('saveServiceLevel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Reguler Baru', 'duration_hours' => 48]);
        $this->assertSame('Reguler', $orderItem->fresh()->variant_name);
        $this->assertSame(72, $orderItem->duration_hours);
    }

    public function test_used_service_level_cannot_be_deleted_but_unused_service_level_can(): void
    {
        ['owner' => $owner, 'product' => $product] = $this->business();
        $usedServiceLevel = ServiceLevel::where('name', 'Reguler')->firstOrFail();
        $product->variants()->create([
            'service_level_id' => $usedServiceLevel->id, 'name' => $usedServiceLevel->name, 'price' => 8000,
            'duration_hours' => $usedServiceLevel->duration_hours, 'is_active' => true,
        ]);
        $unusedServiceLevel = ServiceLevel::create(['name' => 'Premium', 'duration_hours' => 2, 'is_active' => true, 'sort_order' => 10]);
        $component = Livewire::actingAs($owner)->test(ProductsPage::class)
            ->call('openServiceLevelMaster')
            ->call('confirmDeleteServiceLevel', $usedServiceLevel->id)
            ->call('deleteServiceLevel')
            ->assertHasErrors(['deleteServiceLevel'])
            ->assertSee('Master varian masih digunakan oleh produk');

        $this->assertModelExists($usedServiceLevel);

        $component
            ->call('cancelDeleteServiceLevel')
            ->call('confirmDeleteServiceLevel', $unusedServiceLevel->id)
            ->call('deleteServiceLevel')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertModelMissing($unusedServiceLevel);
    }

    public function test_cashier_cannot_open_service_level_master(): void
    {
        ['cashier' => $cashier] = $this->business();

        Livewire::actingAs($cashier)->test(ProductsPage::class)
            ->call('openServiceLevelMaster')
            ->assertStatus(403);
    }
}
