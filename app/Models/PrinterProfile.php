<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterProfile extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'business_id',
        'name',
        'connection_type',
        'ip_address',
        'port',
        'paper_width',
        'auto_cut',
        'open_drawer',
    ];

    protected function casts(): array
    {
        return [
            'auto_cut' => 'boolean',
            'open_drawer' => 'boolean',
            'port' => 'integer',
            'paper_width' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
