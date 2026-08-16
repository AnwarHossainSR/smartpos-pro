<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A jurisdiction and the tax group that applies inside it, matched against
 * whichever party's address the profile's sourcing rule points at.
 */
class TaxZone extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'country_code', 'state', 'postcode_pattern',
        'tax_group_id', 'is_default', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    /**
     * How specifically this zone matches an address, or null for no match.
     * Higher wins, so a postcode rule beats a state rule beats a country rule.
     */
    public function matchScore(?string $country, ?string $state, ?string $postcode): ?int
    {
        $score = 0;

        if ($this->country_code !== null) {
            if ($country === null || strcasecmp($this->country_code, $country) !== 0) {
                return null;
            }
            $score += 1;
        }

        if ($this->state !== null && $this->state !== '') {
            if ($state === null || strcasecmp($this->state, $state) !== 0) {
                return null;
            }
            $score += 2;
        }

        if ($this->postcode_pattern !== null && $this->postcode_pattern !== '') {
            if ($postcode === null || ! preg_match('/'.str_replace('/', '\/', $this->postcode_pattern).'/i', $postcode)) {
                return null;
            }
            $score += 4;
        }

        return $score;
    }
}
