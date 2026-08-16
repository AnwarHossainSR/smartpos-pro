<?php

namespace App\Jobs;

use App\Domain\Enums\TenantStatus;
use App\Mail\DailyReportMail;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a daily sales summary email to the business owner.
 * Dispatched by the Laravel scheduler in Console/Kernel.php.
 */
class SendDailyReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $tenantId) {}

    public function handle(TenantContext $context): void
    {
        $context->set($this->tenantId);

        $tenant = Tenant::find($this->tenantId);
        if (! $tenant || $tenant->status !== TenantStatus::Active) {
            return;
        }

        $owner = User::where('tenant_id', $this->tenantId)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->first();
        if (! $owner) {
            return;
        }

        $yesterday = now()->subDay()->startOfDay();

        $summary = DB::table('sales')
            ->where('tenant_id', $this->tenantId)
            // Half-open range rather than whereDate(): date(sold_at) can't use the index.
            ->where('sold_at', '>=', $yesterday)
            ->where('sold_at', '<', $yesterday->copy()->addDay())
            ->selectRaw('count(*) as sale_count, sum(total) as revenue, sum(profit) as profit')
            ->first();

        Mail::to($owner->email)->queue(new DailyReportMail($owner, $summary, $yesterday));
    }
}
