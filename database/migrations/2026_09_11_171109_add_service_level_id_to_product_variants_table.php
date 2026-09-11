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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('service_level_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('service_levels')->orderBy('id')->get()->each(function ($serviceLevel): void {
            DB::table('product_variants')
                ->whereNull('service_level_id')
                ->whereRaw('lower(name) = ?', [mb_strtolower($serviceLevel->name)])
                ->update([
                    'service_level_id' => $serviceLevel->id,
                    'name' => $serviceLevel->name,
                    'duration_hours' => $serviceLevel->duration_hours,
                ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_level_id');
        });
    }
};
