<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // cashier
            // Nullable: a number is issued only when a sale is *completed*, so a held
            // or draft cart legitimately carries none. NULLs do not collide in a
            // unique index, so any number of carts can sit held at once.
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('completed'); // draft, quotation, held, completed, cancelled
            $table->string('payment_status')->default('paid'); // paid, partial, due
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('coupon_discount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            // What the customer actually handed over when it was not the base
            // currency, plus the rate used to convert it.
            $table->string('tender_currency', 3)->nullable();
            $table->decimal('tender_amount', 12, 2)->nullable();
            $table->decimal('fx_rate', 12, 6)->nullable();
            $table->string('note')->nullable();
            $table->string('public_token')->unique(); // customer-facing order status + pay link
            $table->string('kitchen_status')->default('pending'); // pending, preparing, ready, served
            $table->string('kitchen_note')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'created_at']);
            // Every sales list, dashboard KPI and report filters on `sold_at`, and
            // there are two distinct access patterns:
            //   (tenant_id, sold_at)          — the sales list filtered by date alone
            //   (tenant_id, status, sold_at)  — every report, which pins status = 'completed'
            $table->index(['tenant_id', 'sold_at'], 'sales_tenant_sold_at_index');
            $table->index(['tenant_id', 'status', 'sold_at'], 'sales_tenant_status_sold_at_index');
            $table->unique(['tenant_id', 'invoice_number']);
        });

        // Closes the dining_tables <-> sales cycle; see the dining tables migration.
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->foreign('active_sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropForeign(['active_sale_id']);
        });

        Schema::dropIfExists('sales');
    }
};
