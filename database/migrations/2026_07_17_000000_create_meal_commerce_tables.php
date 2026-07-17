<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('currency', 3)->default('INR');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_option_id')->constrained()->restrictOnDelete();
            $table->string('line_key', 64);
            $table->unsignedSmallInteger('quantity');
            $table->date('delivery_date')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'line_key']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cart_version');
            $table->string('status', 32)->default('awaiting_payment');
            $table->string('payment_status', 32)->default('pending');
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('delivery_address_line_1');
            $table->string('delivery_address_line_2')->nullable();
            $table->string('delivery_city', 120);
            $table->string('delivery_state', 120);
            $table->string('delivery_pincode', 10)->index();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'payment_status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meal_name');
            $table->string('meal_slug')->nullable();
            $table->string('meal_type')->nullable();
            $table->string('category_slug')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->date('delivery_date')->index();
            $table->string('fulfillment_status', 32)->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_order_id')->nullable()->index()->after('reference');
            $table->string('gateway_payment_id')->nullable()->unique()->after('gateway_order_id');
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('gateway_payment_id');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('attempt_number');
            $table->string('failure_code')->nullable()->after('status');
            $table->text('failure_description')->nullable()->after('failure_code');
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway_refund_id')->nullable()->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('pending');
            $table->string('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32)->default('razorpay');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('status', 32)->default('received');
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['gateway_order_id']);
            $table->dropColumn([
                'gateway_order_id', 'gateway_payment_id', 'attempt_number',
                'idempotency_key', 'failure_code', 'failure_description',
            ]);
        });

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
