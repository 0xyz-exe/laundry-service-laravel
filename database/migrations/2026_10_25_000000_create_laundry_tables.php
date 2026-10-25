<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->boolean('is_staff')->default(false); });
        Schema::create('laundry_services', function (Blueprint $table) {
            $table->id(); $table->string('name', 100); $table->text('description')->nullable(); $table->string('unit', 10); $table->unsignedBigInteger('price_minor'); $table->boolean('active')->default(true); $table->timestamps();
            $table->index(['active', 'unit']);
        });
        Schema::create('laundry_orders', function (Blueprint $table) {
            $table->id(); $table->string('reference', 20)->unique(); $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete(); $table->dateTime('pickup_at'); $table->dateTime('delivery_at');
            $table->string('pickup_address', 500); $table->text('customer_note')->nullable(); $table->string('status', 30)->default('pending');
            $table->string('payment_method', 20)->default('cash_on_delivery'); $table->string('payment_status', 20)->default('unpaid');
            $table->unsignedBigInteger('total_minor')->default(0); $table->timestamps(); $table->index(['customer_id', 'created_at']); $table->index(['assigned_staff_id', 'status']);
        });
        Schema::create('laundry_order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('laundry_order_id')->constrained('laundry_orders')->cascadeOnDelete(); $table->foreignId('laundry_service_id')->nullable()->constrained('laundry_services')->nullOnDelete();
            $table->string('service_name', 100); $table->string('unit', 10); $table->unsignedBigInteger('quantity_units'); $table->unsignedBigInteger('unit_price_minor'); $table->unsignedBigInteger('line_total_minor'); $table->timestamps();
        });
        Schema::create('laundry_order_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('laundry_order_id')->constrained('laundry_orders')->restrictOnDelete(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 20); $table->string('from_value', 30)->nullable(); $table->string('to_value', 30); $table->text('note')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['laundry_order_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('laundry_order_events'); Schema::dropIfExists('laundry_order_items'); Schema::dropIfExists('laundry_orders'); Schema::dropIfExists('laundry_services');
        Schema::table('users', function (Blueprint $table) { $table->dropColumn('is_staff'); });
    }
};
