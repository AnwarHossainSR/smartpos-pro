<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateEmailTemplateRequest;
use App\Mail\PreviewMail;
use App\Models\EmailTemplate;
use App\Services\Mail\EmailTemplateRenderer;
use App\Support\Mail\EmailTemplateCatalog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EmailTemplateRenderer $renderer) {}

    /** The catalogue, each entry annotated with the tenant's override if it has one. */
    public function index(): JsonResponse
    {
        $overrides = EmailTemplate::all()->keyBy('key');

        $templates = collect(EmailTemplateCatalog::all())
            ->map(fn (array $default, string $key) => $this->present($key, $default, $overrides->get($key)))
            ->values();

        return response()->json(['data' => $templates]);
    }

    public function show(string $key): JsonResponse
    {
        $default = $this->defaultOrFail($key);

        return response()->json([
            'data' => $this->present($key, $default, $this->renderer->overrideFor($key)),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, string $key): JsonResponse
    {
        $default = $this->defaultOrFail($key);

        $template = EmailTemplate::updateOrCreate(
            ['key' => $key],
            $request->validated(),
        );

        return response()->json(['data' => $this->present($key, $default, $template)]);
    }

    /** Drops the override, so the packaged default is used again. */
    public function destroy(string $key): JsonResponse
    {
        $default = $this->defaultOrFail($key);

        EmailTemplate::where('key', $key)->delete();

        return response()->json(['data' => $this->present($key, $default, null)]);
    }

    /**
     * Renders the template with sample values. Accepts an unsaved subject/body so the
     * editor can preview a draft without committing it first.
     */
    public function preview(UpdateEmailTemplateRequest $request, string $key): JsonResponse
    {
        $this->defaultOrFail($key);

        $draft = $request->validated();
        $override = new EmailTemplate(['key' => $key] + $draft);

        $rendered = $this->renderer->render(
            $key,
            EmailTemplateCatalog::sampleVariables($key),
            override: $override,
        );

        return response()->json(['data' => $rendered]);
    }

    /** Sends the previewed template to one address, so a tenant can check it in a real client. */
    public function sendTest(Request $request, string $key): JsonResponse
    {
        $this->defaultOrFail($key);

        $validated = $request->validate(['email' => 'required|email']);

        $rendered = $this->renderer->render($key, EmailTemplateCatalog::sampleVariables($key));

        Mail::to($validated['email'])->queue(new PreviewMail($rendered['subject'], $rendered['markdown']));

        return response()->json(['data' => ['sent_to' => $validated['email']]]);
    }

    /** @return array{name: string, description: string, variables: array<string, string>, subject: string, body: string} */
    private function defaultOrFail(string $key): array
    {
        return EmailTemplateCatalog::find($key) ?? throw new NotFoundHttpException('Unknown email template.');
    }

    /** @param  array{name: string, description: string, variables: array<string, string>, subject: string, body: string}  $default */
    private function present(string $key, array $default, ?EmailTemplate $override): array
    {
        return [
            'key' => $key,
            'name' => $default['name'],
            'description' => $default['description'],
            'variables' => $default['variables'],
            'subject' => $override->subject ?? $default['subject'],
            'body' => $override->body ?? $default['body'],
            'default_subject' => $default['subject'],
            'default_body' => $default['body'],
            'is_customised' => $override !== null,
        ];
    }
}
