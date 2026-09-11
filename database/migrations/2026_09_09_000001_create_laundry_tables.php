<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#14b8a6');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('unit')->default('kg');
            $table->unsignedBigInteger('price');
            $table->decimal('minimum_quantity', 10, 2)->default(0);
            $table->decimal('rounding_increment', 10, 2)->default(0);
            $table->unsignedInteger('duration_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['outlet_id', 'is_active']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('status')->default('received');
            $table->timestamp('due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'created_at']);
            $table->index(['outlet_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('unit');
            $table->decimal('quantity', 10, 2);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('subtotal');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('method');
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('expense_date');
            $table->string('category');
            $table->unsignedBigInteger('amount');
            $table->string('payment_method')->default('cash');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'expense_date']);
        });

        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('start_time')->default('08:00');
            $table->time('end_time')->default('17:00');
            $table->unsignedInteger('tolerance_minutes')->default(10);
            $table->json('work_days')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained();
            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->string('check_in_photo')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('check_out_photo')->nullable();
            $table->unsignedInteger('work_duration_minutes')->nullable();
            $table->string('status')->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
            $table->index(['outlet_id', 'attendance_date']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['outlet_id']));
        Schema::dropIfExists('outlets');
    }
};
