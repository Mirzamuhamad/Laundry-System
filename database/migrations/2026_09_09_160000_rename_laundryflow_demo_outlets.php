<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('outlets')->where('name', 'LaundryFlow Pusat')->update(['name' => 'Laundry Pos Pusat']);
        DB::table('outlets')->where('name', 'LaundryFlow Selatan')->update(['name' => 'Laundry Pos Selatan']);
    }

    public function down(): void
    {
        DB::table('outlets')->where('name', 'Laundry Pos Pusat')->update(['name' => 'LaundryFlow Pusat']);
        DB::table('outlets')->where('name', 'Laundry Pos Selatan')->update(['name' => 'LaundryFlow Selatan']);
    }
};
