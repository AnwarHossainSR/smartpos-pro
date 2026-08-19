<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's override of one packaged email. A row exists only where a tenant
 * has actually rewritten something; everything else falls back to the default
 * in {@see EmailTemplateCatalog}.
 */
class EmailTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'key', 'subject', 'body'];
}
