<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ProductsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $category_id = null;

    public ?int $outlet_id = null;

    public string $unit = 'kg';

    public $minimum_quantity = 0;

    public $rounding_increment = 0;

    public string $newCategory = '';

    public ?int $deletingProductId = null;

    public string $deletingProductName = '';

    /** @var array<int, array{id: int|null, name: string, price: int|string, duration_hours: int|string, is_active: bool}> */
    public array $variants = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetForm();
        if ($id) {
            $product = Product::with('variants')->findOrFail($id);
            $this->editingId = $product->id;
            foreach (['name', 'category_id', 'outlet_id', 'unit', 'minimum_quantity', 'rounding_increment'] as $field) {
                $this->{$field} = $product->{$field};
            }
            $this->variants = $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => $variant->price,
                'duration_hours' => $variant->duration_hours,
                'is_active' => $variant->is_active,
            ])->all();

            if ($this->variants === []) {
                $this->variants = [$this->defaultVariant($product->price, $product->duration_hours)];
            }
        }
        $this->showForm = true;
    }

    public function addVariant(): void
    {
        $this->variants[] = $this->defaultVariant();
    }

    public function removeVariant(int $index): void
    {
        if (count($this->variants) === 1) {
            $this->addError('variants', 'Produk harus memiliki minimal satu varian.');

            return;
        }

        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
        $this->resetValidation('variants');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'], 'category_id' => ['nullable', 'exists:categories,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'], 'unit' => ['required', 'string', 'max:20'],
            'minimum_quantity' => ['required', 'numeric', 'min:0'], 'rounding_increment' => ['required', 'numeric', 'min:0'],
            'variants' => ['required', 'array', 'min:1'], 'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'variants.*.is_active' => ['required', 'boolean'],
        ]);

        if (! collect($data['variants'])->contains(fn (array $variant): bool => $variant['is_active'])) {
            $this->addError('variants', 'Minimal satu varian harus aktif.');

            return;
        }

        DB::transaction(function () use ($data): void {
            $variants = $data['variants'];
            unset($data['variants']);

            $primaryVariant = collect($variants)->firstWhere('is_active', true);
            $data['price'] = $primaryVariant['price'];
            $data['duration_hours'] = $primaryVariant['duration_hours'];

            $product = Product::updateOrCreate(['id' => $this->editingId], $data);
            $existingVariants = $product->variants()->get()->keyBy('id');
            $retainedIds = [];

            foreach ($variants as $sortOrder => $variantData) {
                $variant = isset($variantData['id']) ? $existingVariants->get($variantData['id']) : null;
                $attributes = [
                    'name' => $variantData['name'],
                    'price' => $variantData['price'],
                    'duration_hours' => $variantData['duration_hours'],
                    'is_active' => $variantData['is_active'],
                    'sort_order' => $sortOrder,
                ];

                if ($variant) {
                    $variant->update($attributes);
                } else {
                    $variant = $product->variants()->create($attributes);
                }

                $retainedIds[] = $variant->id;
            }

            $product->variants()->whereNotIn('id', $retainedIds)->delete();
        });

        $this->showForm = false;
        $this->dispatch('notify', 'Produk berhasil disimpan.');
    }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => ['required', 'string', 'max:60', 'unique:categories,name']]);
        $category = Category::create(['name' => $this->newCategory]);
        $this->category_id = $category->id;
        $this->newCategory = '';
        $this->dispatch('notify', 'Kategori baru ditambahkan.');
    }

    public function toggle(int $id): void
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);
    }

    public function confirmDeleteProduct(int $id): void
    {
        $this->authorizeOwner();
        $product = Product::findOrFail($id);

        $this->deletingProductId = $product->id;
        $this->deletingProductName = $product->name;
    }

    public function cancelDeleteProduct(): void
    {
        $this->reset(['deletingProductId', 'deletingProductName']);
    }

    public function deleteProduct(): void
    {
        $this->authorizeOwner();
        $product = Product::findOrFail($this->deletingProductId);

        $product->delete();
        $this->cancelDeleteProduct();
        $this->dispatch('notify', 'Produk berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'category_id', 'outlet_id', 'newCategory']);
        $this->unit = 'kg';
        $this->minimum_quantity = 0;
        $this->rounding_increment = 0;
        $this->variants = [$this->defaultVariant(8000, 72)];
        $this->resetValidation();
    }

    /** @return array{id: null, name: string, price: int, duration_hours: int, is_active: bool} */
    private function defaultVariant(int $price = 0, int $durationHours = 24): array
    {
        return ['id' => null, 'name' => 'Reguler', 'price' => $price, 'duration_hours' => $durationHours, 'is_active' => true];
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->isOwner(), 403);
    }

    public function render()
    {
        $products = Product::with(['category', 'outlet', 'variants'])->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))->latest()->paginate(12);

        return view('livewire.products-page', ['products' => $products, 'categories' => Category::orderBy('name')->get(), 'outlets' => Outlet::where('is_active', true)->get()])->title('Produk — Laundry Pos');
    }
}
