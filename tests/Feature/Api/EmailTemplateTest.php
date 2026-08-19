<?php

namespace Tests\Feature\Api;

use App\Mail\OrderStatusLinkMail;
use App\Mail\PreviewMail;
use App\Models\EmailTemplate;
use App\Models\Sale;
use App\Models\User;
use App\Support\Mail\EmailTemplateCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * Tenants rewrite the emails their customers receive. The interesting part is
 * the boundary: a template is text with `{placeholders}`, never Blade, and the
 * queue renders it long after the request that saved it has gone.
 */
class EmailTemplateTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private const KEY = EmailTemplateCatalog::ORDER_STATUS_LINK;

    public function test_the_catalogue_lists_defaults_until_a_tenant_overrides_one(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $data = $this->getJson('/api/v1/email-templates')->assertOk()->json('data');

        $this->assertCount(count(EmailTemplateCatalog::keys()), $data);

        $template = collect($data)->firstWhere('key', self::KEY);
        $this->assertFalse($template['is_customised']);
        $this->assertSame($template['default_subject'], $template['subject']);
        $this->assertArrayHasKey('invoice_number', $template['variables']);
    }

    public function test_a_template_can_be_edited_and_reset(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/email-templates/'.self::KEY, [
            'subject' => 'Order {invoice_number} is ready',
            'body' => 'Hello from **{seller_name}** — {url}',
        ])->assertOk()->assertJsonPath('data.is_customised', true);

        $this->getJson('/api/v1/email-templates/'.self::KEY)
            ->assertOk()
            ->assertJsonPath('data.subject', 'Order {invoice_number} is ready');

        $this->deleteJson('/api/v1/email-templates/'.self::KEY)
            ->assertOk()
            ->assertJsonPath('data.is_customised', false);

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_an_unknown_template_key_is_not_found(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/email-templates/order.nope')->assertNotFound();
        $this->putJson('/api/v1/email-templates/order.nope', ['subject' => 'x', 'body' => 'y'])->assertNotFound();
    }

    public function test_preview_renders_a_draft_with_sample_values(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $data = $this->postJson('/api/v1/email-templates/'.self::KEY.'/preview', [
            'subject' => 'Order {invoice_number}',
            'body' => '# Hi {seller_name}',
        ])->assertOk()->json('data');

        $this->assertSame('Order INV-000123', $data['subject']);
        $this->assertStringContainsString('<h1>Hi Demo Store</h1>', $data['html']);

        // Previewing must not save anything.
        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_markup_in_a_template_is_rendered_inert(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $data = $this->postJson('/api/v1/email-templates/'.self::KEY.'/preview', [
            'subject' => 'Hi <script>alert(1)</script>',
            'body' => '<script>alert(1)</script> and {{ $sale }}',
        ])->assertOk()->json('data');

        $this->assertStringNotContainsString('<script>', $data['subject']);
        $this->assertStringNotContainsString('<script>', $data['html']);
        // Blade syntax is text, not something the renderer evaluates.
        $this->assertStringContainsString('{{ $sale }}', $data['html']);
    }

    public function test_the_override_is_what_the_queued_mail_uses(): void
    {
        Mail::fake();

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/email-templates/'.self::KEY, [
            'subject' => 'Your {seller_name} order',
            'body' => 'Track it here: {url}',
        ])->assertOk();

        $sale = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $this->warehouseFor($owner)->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-1',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 10,
            'paid_amount' => 10,
        ]);

        $mail = new OrderStatusLinkMail($sale, 'Demo Store', 'https://example.test/order/abc');
        $envelope = $mail->envelope();

        $this->assertSame('Your Demo Store order', $envelope->subject);
        $this->assertStringContainsString(
            'https://example.test/order/abc',
            $mail->content()->with['markdown'],
        );
    }

    public function test_a_template_renders_on_the_queue_without_a_bound_tenant(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/email-templates/'.self::KEY, [
            'subject' => 'Queued {invoice_number}',
            'body' => 'Body {url}',
        ])->assertOk();

        $sale = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $this->warehouseFor($owner)->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-2',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 10,
            'paid_amount' => 10,
        ]);

        // What a queue worker sees: no request, no tenant context.
        app(TenantContext::class)->forget();

        $subject = (new OrderStatusLinkMail($sale, 'Demo Store', 'https://example.test/o/x'))->envelope()->subject;

        $this->assertSame('Queued INV-2', $subject);
    }

    public function test_a_test_send_queues_the_preview(): void
    {
        Mail::fake();

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/email-templates/'.self::KEY.'/test', ['email' => 'me@example.test'])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'me@example.test');

        Mail::assertQueued(PreviewMail::class, fn (PreviewMail $mail) => $mail->hasTo('me@example.test'));
    }

    public function test_a_cashier_cannot_edit_templates(): void
    {
        $owner = $this->createTenant();

        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));

        $this->putJson('/api/v1/email-templates/'.self::KEY, ['subject' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }

    public function test_one_tenants_override_does_not_leak_into_another(): void
    {
        $first = $this->createTenant('first@example.test');
        Sanctum::actingAs($first);
        $this->putJson('/api/v1/email-templates/'.self::KEY, [
            'subject' => 'First tenant subject',
            'body' => 'body',
        ])->assertOk();

        $second = $this->createTenant('second@example.test');
        Sanctum::actingAs($second);

        $this->getJson('/api/v1/email-templates/'.self::KEY)
            ->assertOk()
            ->assertJsonPath('data.is_customised', false);

        $this->assertSame(1, EmailTemplate::withoutTenancy()->count());
    }
}
