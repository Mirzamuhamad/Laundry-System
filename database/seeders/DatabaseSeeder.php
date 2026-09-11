<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\EmployeeSchedule;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ServiceLevel;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $main = Outlet::firstOrCreate(['code' => 'PST'], ['name' => 'Laundry Pos Pusat', 'address' => 'Jl. Melati No. 10, Jakarta', 'phone' => '0812-3456-7890']);
        Outlet::firstOrCreate(['code' => 'SLT'], ['name' => 'Laundry Pos Selatan', 'address' => 'Jl. Anggrek No. 5, Jakarta', 'phone' => '0812-3456-7891']);

        User::firstOrCreate(['email' => 'owner@laundry.test'], ['name' => 'Dian Pratama', 'phone' => '081200000001', 'role' => 'owner', 'password' => 'password', 'is_active' => true]);
        $cashier = User::firstOrCreate(['email' => 'kasir@laundry.test'], ['outlet_id' => $main->id, 'name' => 'Ayu Lestari', 'phone' => '081200000002', 'role' => 'cashier', 'password' => 'password', 'is_active' => true]);
        EmployeeSchedule::firstOrCreate(['user_id' => $cashier->id], ['start_time' => '08:00', 'end_time' => '17:00', 'tolerance_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5, 6]]);

        $kiloan = Category::firstOrCreate(['name' => 'Kiloan'], ['color' => '#14b8a6', 'sort_order' => 1]);
        $satuan = Category::firstOrCreate(['name' => 'Satuan'], ['color' => '#2563eb', 'sort_order' => 2]);
        $lain = Category::firstOrCreate(['name' => 'Lainnya'], ['color' => '#d97706', 'sort_order' => 3]);
        $products = [
            [$kiloan->id, 'Cuci Kering Lipat', 'kg', 8000, 3, .5, 48],
            [$kiloan->id, 'Cuci Setrika', 'kg', 11000, 3, .5, 48],
            [$kiloan->id, 'Express Same Day', 'kg', 18000, 3, .5, 12],
            [$satuan->id, 'Kemeja', 'pcs', 10000, 0, 0, 48],
            [$satuan->id, 'Jas', 'pcs', 45000, 0, 0, 72],
            [$satuan->id, 'Bed Cover', 'pcs', 30000, 0, 0, 72],
            [$lain->id, 'Cuci Sepatu', 'pasang', 35000, 0, 0, 72],
            [$lain->id, 'Cuci Karpet', 'meter', 20000, 0, .5, 96],
        ];
        $regularServiceLevel = ServiceLevel::where('name', 'Reguler')->firstOrFail();
        foreach ($products as [$category, $name, $unit, $price, $minimum, $rounding, $hours]) {
            $product = Product::firstOrCreate(['name' => $name], ['category_id' => $category, 'unit' => $unit, 'price' => $price, 'minimum_quantity' => $minimum, 'rounding_increment' => $rounding, 'duration_hours' => $hours]);
            $product->variants()->firstOrCreate(['name' => 'Reguler'], ['service_level_id' => $regularServiceLevel->id, 'price' => $product->price, 'duration_hours' => $product->duration_hours, 'is_active' => true, 'sort_order' => 0]);
        }
        Customer::firstOrCreate(['phone' => '081234567890'], ['outlet_id' => $main->id, 'name' => 'Budi Santoso', 'address' => 'Jakarta']);
    }
}
