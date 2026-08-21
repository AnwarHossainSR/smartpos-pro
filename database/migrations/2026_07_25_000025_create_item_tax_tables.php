<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line, per-component breakdown of what tax was actually charged.
 *
 * This is the row a compliant invoice prints, a return reverses, and a VAT/GST
 * return sums — none of which can be recovered from a single `tax_amount`
 * column once rates change. Separate from the tax engine's own tables because
 * these hang off documents that are created much later in the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $breakdown = function (string $table, string $parentTable, string $parentColumn) {
            Schema::create($table, function (Blueprint $t) use ($parentTable, $parentColumn) {
                $t->uuid('id')->primary();
                $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $t->foreignUuid($parentColumn)->constrained($parentTable)->cascadeOnDelete();
                // Nullable: the rate may be deleted later, but the invoice must not change.
                $t->foreignUuid('tax_rate_id')->nullable()->constrained()->nullOnDelete();
                $t->string('name');           // snapshotted, so a renamed rate never rewrites history
                $t->decimal('rate', 9, 4)->default(0);
                $t->string('type')->default('percentage');
                $t->boolean('is_compound')->default(false);
                $t->decimal('base_amount', 14, 2)->default(0); // what this component was charged on
                $t->decimal('amount', 14, 2)->default(0);
                $t->timestamps();

                $t->index(['tenant_id']);
                $t->index([$parentColumn]);
            });
        };

        $breakdown('sale_item_taxes', 'sale_items', 'sale_item_id');
        $breakdown('purchase_item_taxes', 'purchase_items', 'purchase_item_id');
        $breakdown('sale_return_item_taxes', 'sale_return_items', 'sale_return_item_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_item_taxes');
        Schema::dropIfExists('purchase_item_taxes');
        Schema::dropIfExists('sale_item_taxes');
    }
};
