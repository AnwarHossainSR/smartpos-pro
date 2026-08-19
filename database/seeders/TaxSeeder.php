<?php

namespace Database\Seeders;

use App\Models\TaxGroup;
use App\Models\TaxRate;
use App\Models\TaxZone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function __construct(private ?string $tenantId = null) {}

    public function run(): void
    {
        $tenantId = $this->tenantId ?? app(TenantContext::class)->id();

        if (! $tenantId) {
            $this->command?->warn('TaxSeeder: No tenant bound. Pass tenant_id or bind TenantContext.');

            return;
        }

        // ── Tax Rates (Bangladesh VAT components) ──
        $vat15 = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'VAT 15%'],
            ['code' => 'VAT15', 'rate' => 15, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        $sd5 = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 5%'],
            ['code' => 'SD5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        $sd10 = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 10%'],
            ['code' => 'SD10', 'rate' => 10, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        $sd20 = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 20%'],
            ['code' => 'SD20', 'rate' => 20, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        $ait5 = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'AIT 5%'],
            ['code' => 'AIT5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        $vatZero = TaxRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Zero Rated'],
            ['code' => 'ZERO', 'rate' => 0, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]
        );

        // ── Tax Groups ──
        $standardVat = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Standard VAT 15%'],
            ['code' => 'STD15', 'is_default' => false, 'is_active' => true]
        );
        $standardVat->rates()->syncWithoutDetaching([$vat15->id => ['priority' => 1]]);

        $sd5Group = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 5%'],
            ['code' => 'SD5G', 'is_default' => false, 'is_active' => true]
        );
        $sd5Group->rates()->syncWithoutDetaching([$sd5->id => ['priority' => 1]]);

        $sd10Group = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 10%'],
            ['code' => 'SD10G', 'is_default' => false, 'is_active' => true]
        );
        $sd10Group->rates()->syncWithoutDetaching([$sd10->id => ['priority' => 1]]);

        $sd20Group = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'SD 20%'],
            ['code' => 'SD20G', 'is_default' => false, 'is_active' => true]
        );
        $sd20Group->rates()->syncWithoutDetaching([$sd20->id => ['priority' => 1]]);

        $aitGroup = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'AIT 5%'],
            ['code' => 'AIT5G', 'is_default' => false, 'is_active' => true]
        );
        $aitGroup->rates()->syncWithoutDetaching([$ait5->id => ['priority' => 1]]);

        $zeroGroup = TaxGroup::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Zero Rated'],
            ['code' => 'ZERO', 'is_default' => true, 'is_active' => true]
        );
        $zeroGroup->rates()->syncWithoutDetaching([$vatZero->id => ['priority' => 1]]);

        // ── Tax Zones (Bangladesh divisions) ──
        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Dhaka Division'],
            ['country_code' => 'BD', 'state' => 'Dhaka', 'tax_group_id' => $standardVat->id, 'is_default' => true, 'priority' => 10, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Chittagong Division'],
            ['country_code' => 'BD', 'state' => 'Chittagong', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 9, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Rajshahi Division'],
            ['country_code' => 'BD', 'state' => 'Rajshahi', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 8, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Khulna Division'],
            ['country_code' => 'BD', 'state' => 'Khulna', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 7, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Barisal Division'],
            ['country_code' => 'BD', 'state' => 'Barisal', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 6, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Rangpur Division'],
            ['country_code' => 'BD', 'state' => 'Rangpur', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 5, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Mymensingh Division'],
            ['country_code' => 'BD', 'state' => 'Mymensingh', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 4, 'is_active' => true]
        );

        TaxZone::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Sylhet Division'],
            ['country_code' => 'BD', 'state' => 'Sylhet', 'tax_group_id' => $standardVat->id, 'is_default' => false, 'priority' => 3, 'is_active' => true]
        );
    }
}
