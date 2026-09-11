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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('duration_hours');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'sort_order']);
        });

        $now = now();

        DB::table('products')->orderBy('id')->chunkById(500, function ($products) use ($now): void {
            DB::table('product_variants')->insert($products->map(fn ($product): array => [
                'product_id' => $product->id,
                'name' => 'Reguler',
                'price' => $product->price,
                'duration_hours' => $product->duration_hours,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
