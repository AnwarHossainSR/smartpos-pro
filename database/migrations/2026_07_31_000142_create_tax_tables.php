<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tax engine's schema.
 *
 * Tax was previously two columns on `products` (`tax_rate`, `tax_inclusive`)
 * computed inline at checkout. That cannot express a multi-component tax (a GST
 * invoice must show its CGST and SGST split separately), cannot record what was
 * actually charged per component (so a return has to recompute rather than
 * reverse), and gives a VAT return nothing to sum over.
 *
 * The legacy columns are kept and back-filled into single-rate groups by the
 * accompanying data migration, so existing products and historical invoices
 * behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Country tax profiles. Global catalogue, not tenant-scoped: these are
        // facts about a jurisdiction, identical for every tenant that trades there.
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->string('code')->unique();          // BD_VAT, IN_GST, EU_VAT, GENERIC
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('sourcing')->default('origin');          // origin | destination
            $table->string('rounding_scope')->default('line');      // line | component | invoice
            $table->string('rounding_mode')->default('half_up');
            $table->string('price_display')->default('exclusive');  // inclusive | exclusive
            $table->string('tax_number_label')->default('Tax number'); // "BIN", "GSTIN", "VAT no."
            $table->string('tax_number_pattern')->nullable();       // regex, validated on the business/customer
            $table->json('required_invoice_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A single tax component: "VAT 15%", "CGST 9%", "Eco fee 2.00".
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('rate', 9, 4)->default(0);   // percent, or a flat per-unit amount when type = fixed
            $table->string('type')->default('percentage'); // percentage | fixed
            // A compound component taxes the base *plus* the components before it
            // (Canadian PST-on-GST, some cess regimes), so ordering is significant.
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'name']);
        });

        // What actually gets attached to a product: an ordered set of components.
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('tax_group_rate', function (Blueprint $table) {
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('tax_group_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('tax_rate_id', 12)->constrained()->cascadeOnDelete();
            // Compound components must apply after the ones they compound on.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->primary(['tax_group_id', 'tax_rate_id']);
            $table->index(['tenant_id']);
        });

        // A jurisdiction and the group that applies inside it. Matched against the
        // branch address (origin sourcing) or the customer's (destination sourcing).
        Schema::create('tax_zones', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('postcode_pattern')->nullable();
            $table->foreignUlid('tax_group_id', 12)->nullable()->constrained()->nullOnDelete();
            // The fallback zone when nothing more specific matches.
            $table->boolean('is_default')->default(false);
            // Most specific match wins; ties break on priority.
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Per-line, per-component breakdown of what was actually charged.
        // This is the row a compliant invoice prints, a return reverses, and a
        // VAT/GST return sums — none of which can be recovered from a single
        // `tax_amount` column once rates change.
        $breakdown = function (string $table, string $parentTable, string $parentColumn) {
            Schema::create($table, function (Blueprint $t) use ($parentTable, $parentColumn) {
                $t->char('id', 12)->primary();
                $t->unique('id');
                $t->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
                $t->foreignUlid($parentColumn, 12)->constrained($parentTable)->cascadeOnDelete();
                // Nullable: the rate may be deleted later, but the invoice must not change.
                $t->foreignUlid('tax_rate_id', 12)->nullable()->constrained()->nullOnDelete();
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

        Schema::table('products', function (Blueprint $table) {
            $table->foreignUlid('tax_group_id', 12)->nullable()->after('unit_id')->constrained()->nullOnDelete();
        });

        // Falls back per category so a shop can set "food is zero-rated" once
        // rather than on every product in it.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignUlid('tax_group_id', 12)->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('tax_exempt')->default(false)->after('tax_number');
            $table->string('tax_exemption_reference')->nullable()->after('tax_exempt');
            $table->string('country_code', 2)->nullable()->after('tax_exemption_reference');
            $table->string('state')->nullable()->after('country_code');
            $table->string('postcode')->nullable()->after('state');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignUlid('tax_profile_id', 12)->nullable()->after('tax_number')->constrained()->nullOnDelete();
            // Denormalised from the profile so a tenant can override the default
            // for its own market without forking the profile.
            $table->string('price_display')->default('exclusive')->after('tax_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_profile_id');
            $table->dropColumn('price_display');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'state', 'postcode']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tax_exempt', 'tax_exemption_reference', 'country_code', 'state', 'postcode']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_group_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_group_id');
        });

        Schema::dropIfExists('sale_return_item_taxes');
        Schema::dropIfExists('purchase_item_taxes');
        Schema::dropIfExists('sale_item_taxes');
        Schema::dropIfExists('tax_zones');
        Schema::dropIfExists('tax_group_rate');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_profiles');
    }
};
