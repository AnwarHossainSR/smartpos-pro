<?php

namespace App\Services;

use App\Models\TaxGroup;
use Illuminate\Support\Facades\DB;

class TaxGroupService
{
    public function create(array $data): TaxGroup
    {
        return DB::transaction(function () use ($data) {
            $group = TaxGroup::create(collect($data)->except('rates')->all());

            $this->syncRates($group, $data['rates'] ?? []);

            return $group->load('rates');
        });
    }

    public function update(TaxGroup $group, array $data): TaxGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update(collect($data)->except('rates')->all());

            if (array_key_exists('rates', $data)) {
                $this->syncRates($group, $data['rates'] ?? []);
            }

            return $group->load('rates');
        });
    }

    /** @param  array<int, array{tax_rate_id:int, priority?:int}>  $rates */
    private function syncRates(TaxGroup $group, array $rates): void
    {
        $pivot = [];

        foreach ($rates as $index => $rate) {
            $pivot[$rate['tax_rate_id']] = [
                'priority' => $rate['priority'] ?? $index,
                'tenant_id' => $group->tenant_id,
            ];
        }

        $group->rates()->sync($pivot);
    }
}
