<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tax engine's schema.
 *
 * Tax cannot be expressed as two columns on `products` (`tax_rate`,
 * `tax_inclusive`) computed inline at checkout: that cannot express a
 * multi-component tax (a GST invoice must show its CGST and SGST split
 * separately), cannot record what was actually charged per component (so a
 * return has to recompute rather than reverse), and gives a VAT return nothing
 * to sum over.
 *
 * The legacy `products.tax_rate` / `products.tax_inclusive` columns are kept as
 * a read-only mirror for the frontend; they are not read by the engine.
 *
 * These tables come this early in the sequence because `businesses`,
 * `categories` and `products` all carry a foreign key into them. The per-line
 * breakdown tables live in a later migration, once the documents they hang off
 * exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Country tax profiles. Global catalogue, not tenant-scoped: these are
        // facts about a jurisdiction, identical for every tenant that trades there.
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
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
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
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
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('tax_group_rate', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tax_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tax_rate_id')->constrained()->cascadeOnDelete();
            // Compound components must apply after the ones they compound on.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->primary(['tax_group_id', 'tax_rate_id']);
            $table->index(['tenant_id']);
        });

        // A jurisdiction and the group that applies inside it. Matched against the
        // branch address (origin sourcing) or the customer's (destination sourcing).
        Schema::create('tax_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('postcode_pattern')->nullable();
            $table->foreignUuid('tax_group_id')->nullable()->constrained()->nullOnDelete();
            // The fallback zone when nothing more specific matches.
            $table->boolean('is_default')->default(false);
            // Most specific match wins; ties break on priority.
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_zones');
        Schema::dropIfExists('tax_group_rate');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_profiles');
    }
};
