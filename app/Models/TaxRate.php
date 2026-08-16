<?php

namespace App\Models;

use App\Domain\Enums\TaxType;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One tax component: "VAT 15%", "CGST 9%", "Bottle deposit 2.00". */
class TaxRate extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'code', 'rate', 'type', 'is_compound', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'type' => TaxType::class,
            'is_compound' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_rate')->withPivot('priority');
    }
}
