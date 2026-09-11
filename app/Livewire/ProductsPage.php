<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public bool $showServiceLevelMaster = false;

    public ?int $editingServiceLevelId = null;

    public string $serviceLevelName = '';

    public $serviceLevelDurationHours = 24;

    public ?int $deletingServiceLevelId = null;

    public string $deletingServiceLevelName = '';

    /** @var array<int, array{id: int|null, service_level_id: int|null, price: int|string, is_active: bool}> */
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
            $serviceLevelsByName = ServiceLevel::query()->get()->keyBy(fn (ServiceLevel $serviceLevel): string => mb_strtolower($serviceLevel->name));
            $this->editingId = $product->id;
            foreach (['name', 'category_id', 'outlet_id', 'unit', 'minimum_quantity', 'rounding_increment'] as $field) {
                $this->{$field} = $product->{$field};
            }
            $this->variants = $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'service_level_id' => $variant->service_level_id ?? $serviceLevelsByName->get(mb_strtolower($variant->name))?->id,
                'price' => $variant->price,
                'is_active' => $variant->is_active,
            ])->all();

            if ($this->variants === []) {
                $this->variants = [$this->defaultVariant($product->price)];
            }
        }
        $this->showForm = true;
    }

    public function addVariant(): void
    {
        $variant = $this->defaultVariant();

        if ($variant['service_level_id'] === null) {
            $this->addError('variants', 'Semua master varian aktif sudah digunakan.');

            return;
        }

        $this->variants[] = $variant;
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
            'variants.*.service_level_id' => ['required', 'integer', 'distinct', Rule::exists('service_levels', 'id')->where('is_active', true)],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.is_active' => ['required', 'boolean'],
        ]);

        if (! collect($data['variants'])->contains(fn (array $variant): bool => $variant['is_active'])) {
            $this->addError('variants', 'Minimal satu varian harus aktif.');

            return;
        }

        DB::transaction(function () use ($data): void {
            $variants = $data['variants'];
            unset($data['variants']);
            $serviceLevels = ServiceLevel::query()->whereIn('id', collect($variants)->pluck('service_level_id'))->get()->keyBy('id');

            $primaryVariant = collect($variants)->firstWhere('is_active', true);
            $primaryServiceLevel = $serviceLevels->get($primaryVariant['service_level_id']);
            $data['price'] = $primaryVariant['price'];
            $data['duration_hours'] = $primaryServiceLevel->duration_hours;

            $product = Product::updateOrCreate(['id' => $this->editingId], $data);
            $existingVariants = $product->variants()->get()->keyBy('id');
            $retainedIds = [];

            foreach ($variants as $sortOrder => $variantData) {
                $variant = isset($variantData['id']) ? $existingVariants->get($variantData['id']) : null;
                $serviceLevel = $serviceLevels->get($variantData['service_level_id']);
                $attributes = [
                    'service_level_id' => $serviceLevel->id,
                    'name' => $serviceLevel->name,
                    'price' => $variantData['price'],
                    'duration_hours' => $serviceLevel->duration_hours,
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

    public function openServiceLevelMaster(): void
    {
        $this->authorizeOwner();
        $this->resetServiceLevelForm();
        $this->showServiceLevelMaster = true;
    }

    public function closeServiceLevelMaster(): void
    {
        $this->showServiceLevelMaster = false;
        $this->resetServiceLevelForm();
        $this->reset(['deletingServiceLevelId', 'deletingServiceLevelName']);
    }

    public function editServiceLevel(int $id): void
    {
        $this->authorizeOwner();
        $serviceLevel = ServiceLevel::findOrFail($id);

        $this->editingServiceLevelId = $serviceLevel->id;
        $this->serviceLevelName = $serviceLevel->name;
        $this->serviceLevelDurationHours = $serviceLevel->duration_hours;
        $this->resetValidation();
    }

    public function saveServiceLevel(): void
    {
        $this->authorizeOwner();
        $this->serviceLevelName = Str::of($this->serviceLevelName)->squish()->toString();
        $data = $this->validate([
            'serviceLevelName' => ['required', 'string', 'max:50', Rule::unique('service_levels', 'name')->ignore($this->editingServiceLevelId)],
            'serviceLevelDurationHours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $duplicateExists = ServiceLevel::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($data['serviceLevelName'])])
            ->when($this->editingServiceLevelId, fn ($query) => $query->where('id', '!=', $this->editingServiceLevelId))
            ->exists();

        if ($duplicateExists) {
            $this->addError('serviceLevelName', 'Nama varian sudah digunakan.');

            return;
        }

        DB::transaction(function () use ($data): void {
            $serviceLevel = $this->editingServiceLevelId
                ? ServiceLevel::findOrFail($this->editingServiceLevelId)
                : new ServiceLevel(['is_active' => true, 'sort_order' => ((int) ServiceLevel::max('sort_order')) + 1]);
            $serviceLevel->fill([
                'name' => $data['serviceLevelName'],
                'duration_hours' => $data['serviceLevelDurationHours'],
            ])->save();
            $serviceLevel->productVariants()->update([
                'name' => $serviceLevel->name,
                'duration_hours' => $serviceLevel->duration_hours,
            ]);
        });

        $this->resetServiceLevelForm();
        $this->dispatch('notify', 'Master varian berhasil disimpan.');
    }

    public function confirmDeleteServiceLevel(int $id): void
    {
        $this->authorizeOwner();
        $serviceLevel = ServiceLevel::findOrFail($id);

        $this->deletingServiceLevelId = $serviceLevel->id;
        $this->deletingServiceLevelName = $serviceLevel->name;
        $this->resetValidation('deleteServiceLevel');
    }

    public function cancelDeleteServiceLevel(): void
    {
        $this->reset(['deletingServiceLevelId', 'deletingServiceLevelName']);
        $this->resetValidation('deleteServiceLevel');
    }

    public function deleteServiceLevel(): void
    {
        $this->authorizeOwner();
        $serviceLevel = ServiceLevel::findOrFail($this->deletingServiceLevelId);

        if ($serviceLevel->productVariants()->exists()) {
            $this->addError('deleteServiceLevel', 'Master varian masih digunakan oleh produk dan tidak dapat dihapus.');

            return;
        }

        $serviceLevel->delete();
        $this->cancelDeleteServiceLevel();
        $this->dispatch('notify', 'Master varian berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'category_id', 'outlet_id', 'newCategory']);
        $this->unit = 'kg';
        $this->minimum_quantity = 0;
        $this->rounding_increment = 0;
        $this->variants = [];
        $this->variants = [$this->defaultVariant(8000)];
        $this->resetValidation();
    }

    public function resetServiceLevelForm(): void
    {
        $this->reset(['editingServiceLevelId', 'serviceLevelName']);
        $this->serviceLevelDurationHours = 24;
        $this->resetValidation();
    }

    /** @return array{id: null, service_level_id: int|null, price: int, is_active: bool} */
    private function defaultVariant(int $price = 0): array
    {
        $usedServiceLevelIds = collect($this->variants)->pluck('service_level_id')->filter()->all();
        $serviceLevelId = ServiceLevel::query()
            ->where('is_active', true)
            ->whereNotIn('id', $usedServiceLevelIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return ['id' => null, 'service_level_id' => $serviceLevelId, 'price' => $price, 'is_active' => true];
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->isOwner(), 403);
    }

    public function render()
    {
        $products = Product::with(['category', 'outlet', 'variants'])->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))->latest()->paginate(12);

        return view('livewire.products-page', ['products' => $products, 'categories' => Category::orderBy('name')->get(), 'outlets' => Outlet::where('is_active', true)->get(), 'serviceLevels' => ServiceLevel::where('is_active', true)->withCount('productVariants')->orderBy('sort_order')->orderBy('id')->get()])->title('Produk — Laundry Pos');
    }
}
