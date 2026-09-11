<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('duration_hours');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('service_levels')->insert([
            ['name' => 'Reguler', 'duration_hours' => 72, 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'One Day', 'duration_hours' => 24, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Express', 'duration_hours' => 6, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Quick', 'duration_hours' => 3, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('product_variants')->orderBy('id')->get()->each(function ($variant) use ($now): void {
            $exists = DB::table('service_levels')->whereRaw('lower(name) = ?', [mb_strtolower($variant->name)])->exists();

            if (! $exists) {
                DB::table('service_levels')->insert([
                    'name' => $variant->name,
                    'duration_hours' => $variant->duration_hours,
                    'is_active' => true,
                    'sort_order' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_levels');
    }
};
