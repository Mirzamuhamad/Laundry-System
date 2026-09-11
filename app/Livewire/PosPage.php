<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\BluetoothReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PosPage extends Component
{
    public ?int $outletId = null;

    public string $search = '';

    public ?int $categoryId = null;

    public array $cart = [];

    public ?int $customerId = null;

    public string $customerSearch = '';

    public bool $showCustomer = false;

    public bool $showNewCustomer = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerAddress = '';

    public string $discountType = 'nominal';

    public $discountValue = 0;

    public $paymentAmount = 0;

    public string $paymentMethod = 'cash';

    public string $notes = '';

    public bool $saving = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->outletId = $user->isOwner() ? Outlet::where('is_active', true)->value('id') : $user->outlet_id;
        $this->restoreCart();
    }

    public function updatedOutletId(): void
    {
        $this->synchronizeEmployeeOutlet();
        $this->customerId = null;
        $this->restoreCart();
    }

    public function addProduct(int $id): void
    {
        $this->synchronizeEmployeeOutlet();
        $product = Product::with('activeVariants')->where('is_active', true)->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))->findOrFail($id);
        $key = (string) $id;
        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;
        } else {
            $this->cart[$key] = $this->makeCartItem($product, null, (float) max(1, $product->minimum_quantity));
        }
        $this->normalizeQuantity($key);
        $this->persistCart();
    }

    public function selectVariant(string $key, $variantId): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $productId = (int) $this->cart[$key]['product_id'];
        $variant = ProductVariant::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->find($variantId);

        if (! $variant) {
            $this->cart[$key]['product_variant_id'] = null;
            $this->cart[$key]['variant_name'] = null;
            $this->cart[$key]['duration_hours'] = null;
            $this->cart[$key]['price'] = 0;
        } else {
            $this->cart[$key]['product_variant_id'] = $variant->id;
            $this->cart[$key]['variant_name'] = $variant->name;
            $this->cart[$key]['duration_hours'] = $variant->duration_hours;
            $this->cart[$key]['price'] = $variant->price;
        }

        $this->paymentAmount = min((int) $this->paymentAmount, $this->total);
        $this->resetValidation('cart');
        $this->persistCart();
    }

    public function changeQuantity(string $key, $quantity): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }
        $this->cart[$key]['quantity'] = max(0.01, (float) $quantity);
        $this->normalizeQuantity($key);
        $this->persistCart();
    }

    private function normalizeQuantity(string $key): void
    {
        $item = &$this->cart[$key];
        $qty = max($item['quantity'], $item['minimum']);
        if ($item['rounding'] > 0) {
            $qty = ceil($qty / $item['rounding']) * $item['rounding'];
        }
        $item['quantity'] = round($qty, 2);
        $this->paymentAmount = min((int) $this->paymentAmount, $this->total);
    }

    public function removeItem(string $key): void
    {
        unset($this->cart[$key]);
        $this->paymentAmount = min((int) $this->paymentAmount, $this->total);
        $this->persistCart();
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) collect($this->cart)->sum(fn ($i) => round($i['price'] * $i['quantity']));
    }

    #[Computed]
    public function discountAmount(): int
    {
        $value = max(0, (float) $this->discountValue);

        return (int) min($this->subtotal, $this->discountType === 'percent' ? round($this->subtotal * min($value, 100) / 100) : $value);
    }

    #[Computed]
    public function total(): int
    {
        return max(0, $this->subtotal - $this->discountAmount);
    }

    #[Computed]
    public function hasUnselectedVariants(): bool
    {
        return collect($this->cart)->contains(fn (array $item): bool => $item['variant_required'] && $item['product_variant_id'] === null);
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function selectCustomer(int $id): void
    {
        $this->synchronizeEmployeeOutlet();
        $customer = Customer::where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))->findOrFail($id);
        $this->customerId = $customer->id;
        $this->showCustomer = false;
        $this->customerSearch = '';
    }

    public function createCustomer(): void
    {
        $this->synchronizeEmployeeOutlet();
        $data = $this->validate(['newCustomerName' => 'required|string|max:100', 'newCustomerPhone' => 'required|string|max:30', 'newCustomerAddress' => 'nullable|string|max:500']);
        $phone = preg_replace('/\D+/', '', $data['newCustomerPhone']);
        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $data['newCustomerName'], 'address' => $data['newCustomerAddress'], 'outlet_id' => $this->outletId]);
        $this->customerId = $customer->id;
        $this->showNewCustomer = false;
        $this->showCustomer = false;
        $this->reset(['newCustomerName', 'newCustomerPhone', 'newCustomerAddress']);
        $this->dispatch('notify', 'Pelanggan berhasil dipilih.');
    }

    public function setFullPayment(): void
    {
        $this->paymentAmount = $this->total;
    }

    public function saveOrder(bool $print = true): void
    {
        if ($this->saving) {
            return;
        } $this->saving = true;
        try {
            $this->synchronizeEmployeeOutlet();
            if (! $this->refreshCartPricing()) {
                return;
            }
            $this->validate(['outletId' => 'required|exists:outlets,id', 'customerId' => 'required|exists:customers,id', 'cart' => 'required|array|min:1', 'paymentAmount' => 'required|integer|min:0|max:'.$this->total, 'paymentMethod' => 'required|in:cash,transfer,qris', 'notes' => 'nullable|max:1000']);
            $customer = Customer::findOrFail($this->customerId);
            $order = DB::transaction(function () use ($customer) {
                $maxHours = collect($this->cart)->max('duration_hours') ?? 48;
                $order = Order::create(['number' => 'TMP-'.Str::uuid(), 'outlet_id' => $this->outletId, 'customer_id' => $customer->id, 'user_id' => Auth::id(), 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'subtotal' => $this->subtotal, 'discount_type' => $this->discountValue > 0 ? $this->discountType : null, 'discount_value' => $this->discountValue, 'discount_amount' => $this->discountAmount, 'total' => $this->total, 'paid_amount' => $this->paymentAmount, 'payment_status' => $this->paymentAmount <= 0 ? 'unpaid' : ($this->paymentAmount < $this->total ? 'partial' : 'paid'), 'status' => 'received', 'due_at' => now()->addHours($maxHours), 'notes' => $this->notes]);
                $order->update(['number' => 'LF-'.now()->format('ymd').'-'.str_pad($order->id, 5, '0', STR_PAD_LEFT)]);
                foreach ($this->cart as $item) {
                    $order->items()->create(['product_id' => $item['product_id'], 'product_variant_id' => $item['product_variant_id'], 'product_name' => $item['name'], 'variant_name' => $item['variant_name'], 'duration_hours' => $item['duration_hours'], 'unit' => $item['unit'], 'quantity' => $item['quantity'], 'unit_price' => $item['price'], 'subtotal' => (int) round($item['price'] * $item['quantity'])]);
                }
                if ($this->paymentAmount > 0) {
                    Payment::create(['order_id' => $order->id, 'user_id' => Auth::id(), 'method' => $this->paymentMethod, 'amount' => $this->paymentAmount, 'paid_at' => now()]);
                }

                return $order;
            });
            $url = route('transactions.receipt', ['order' => $order, 'autoprint' => $print ? 1 : 0]);
            $receiptText = resolve(BluetoothReceipt::class)->build($order);
            session()->forget($this->cartSessionKey());
            $this->reset(['cart', 'customerId', 'customerSearch', 'discountValue', 'paymentAmount', 'notes']);
            $this->discountType = 'nominal';
            $this->paymentMethod = 'cash';
            $this->dispatch('notify', 'Transaksi '.$order->number.' berhasil disimpan.');
            $this->dispatch('order-saved');
            if ($print) {
                $this->dispatch('print-receipt', text: $receiptText, fallbackUrl: $url);
            }
        } finally {
            $this->saving = false;
        }
    }

    private function synchronizeEmployeeOutlet(): void
    {
        $user = Auth::user();

        if (! $user->isOwner()) {
            $this->outletId = $user->outlet_id;
        }
    }

    private function restoreCart(): void
    {
        $savedCart = session()->get($this->cartSessionKey(), []);
        $this->cart = [];

        if (! is_array($savedCart) || $savedCart === []) {
            return;
        }

        $products = Product::query()
            ->with('activeVariants')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))
            ->whereIn('id', array_keys($savedCart))
            ->get()
            ->keyBy('id');

        foreach ($savedCart as $productId => $savedItem) {
            $product = $products->get((int) $productId);
            $quantity = is_array($savedItem) ? ($savedItem['quantity'] ?? null) : $savedItem;
            $variantId = is_array($savedItem) ? ($savedItem['product_variant_id'] ?? null) : null;

            if (! $product || ! is_numeric($quantity)) {
                continue;
            }

            $key = (string) $product->id;
            $this->cart[$key] = $this->makeCartItem($product, is_numeric($variantId) ? (int) $variantId : null, (float) $quantity);
            $this->normalizeQuantity($key);
        }

        $this->persistCart();
    }

    private function persistCart(): void
    {
        if ($this->cart === []) {
            session()->forget($this->cartSessionKey());

            return;
        }

        session()->put($this->cartSessionKey(), collect($this->cart)
            ->mapWithKeys(fn (array $item): array => [(string) $item['product_id'] => [
                'quantity' => (float) $item['quantity'],
                'product_variant_id' => $item['product_variant_id'],
            ]])
            ->all());
    }

    private function refreshCartPricing(): bool
    {
        if ($this->cart === []) {
            return true;
        }

        $products = Product::query()
            ->with('activeVariants')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))
            ->whereIn('id', collect($this->cart)->pluck('product_id'))
            ->get()
            ->keyBy('id');
        $refreshedCart = [];

        foreach ($this->cart as $item) {
            $product = $products->get((int) $item['product_id']);

            if (! $product) {
                $this->addError('cart', 'Salah satu produk tidak lagi tersedia pada outlet ini.');

                return false;
            }

            $key = (string) $product->id;
            $refreshedCart[$key] = $this->makeCartItem($product, $item['product_variant_id'] ?? null, (float) $item['quantity']);

            if ($refreshedCart[$key]['variant_required'] && $refreshedCart[$key]['product_variant_id'] === null) {
                $this->addError('cart', 'Pilih varian layanan untuk setiap produk sebelum menyimpan transaksi.');

                return false;
            }
        }

        $this->cart = $refreshedCart;
        $this->persistCart();

        return true;
    }

    private function makeCartItem(Product $product, ?int $selectedVariantId, float $quantity): array
    {
        $variants = $product->activeVariants->map(fn (ProductVariant $variant): array => [
            'id' => $variant->id,
            'name' => $variant->name,
            'price' => $variant->price,
            'duration_hours' => $variant->duration_hours,
        ])->values();

        if ($variants->isEmpty()) {
            $variants->push([
                'id' => null,
                'name' => 'Reguler',
                'price' => $product->price,
                'duration_hours' => $product->duration_hours,
            ]);
        }

        $selectedVariant = $variants->count() === 1
            ? $variants->first()
            : $variants->firstWhere('id', $selectedVariantId);

        return [
            'product_id' => $product->id,
            'product_variant_id' => $selectedVariant['id'] ?? null,
            'name' => $product->name,
            'variant_name' => $selectedVariant['name'] ?? null,
            'duration_hours' => $selectedVariant['duration_hours'] ?? null,
            'unit' => $product->unit,
            'price' => $selectedVariant['price'] ?? 0,
            'quantity' => $quantity,
            'minimum' => (float) $product->minimum_quantity,
            'rounding' => (float) $product->rounding_increment,
            'variant_required' => $variants->count() > 1,
            'variants' => $variants->all(),
        ];
    }

    private function cartSessionKey(): string
    {
        return 'pos_cart_'.Auth::id().'_'.($this->outletId ?? 'none');
    }

    public function render()
    {
        $this->synchronizeEmployeeOutlet();
        $products = Product::with(['category', 'activeVariants'])->where('is_active', true)->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))->orderBy('name')->get();
        $customers = $this->showCustomer ? Customer::where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $this->outletId))->when($this->customerSearch, fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$this->customerSearch.'%')->orWhere('phone', 'like', '%'.$this->customerSearch.'%')))->latest()->limit(8)->get() : collect();

        return view('livewire.pos-page', ['products' => $products, 'customers' => $customers, 'outlets' => Outlet::where('is_active', true)->get(), 'categories' => Category::where('is_active', true)->orderBy('sort_order')->get()])->title('POS — Laundry Pos');
    }
}
