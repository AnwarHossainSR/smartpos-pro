<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\CustomerPortalMagicLinkMail;
use App\Models\Customer;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    use ApiResponse;

    public function magicLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $customer = Customer::withoutTenancy()
            ->where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if ($customer) {
            $customer->portal_token = Str::random(64);
            $customer->portal_token_expires_at = now()->addHours(24);
            $customer->save();

            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $url = "{$frontendUrl}/portal/auth?token={$customer->portal_token}";

            Mail::to($customer->email)->send(new CustomerPortalMagicLinkMail($customer, $url));
        }

        return $this->success(null, 'If that email is registered, a magic link has been sent.');
    }

    public function verify(Request $request, string $token): JsonResponse
    {
        $customer = Customer::withoutTenancy()
            ->where('portal_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $customer || ! $customer->portal_token_expires_at || $customer->portal_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired token.'],
            ]);
        }

        $customer->portal_token = null;
        $customer->portal_token_expires_at = null;
        $customer->save();

        $plainTextToken = $customer->createToken('portal', ['portal'])->plainTextToken;

        return response()->json(['token' => $plainTextToken]);
    }
}
