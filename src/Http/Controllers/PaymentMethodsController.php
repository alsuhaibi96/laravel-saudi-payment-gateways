<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Http\Controllers;

use Alsuhaibi\SaudiPayments\Models\PaymentMethod;
use Alsuhaibi\SaudiPayments\PaymentManager;
use Alsuhaibi\SaudiPayments\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /payments/methods?amount=14950&locale=ar
 *
 * Used by the hosted checkout web page (opened from the mobile app in a web view) to render
 * whatever methods are enabled right now. Enabling Tabby for a weekend sale is a DB update,
 * not an App Store release.
 */
final class PaymentMethodsController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'locale' => ['sometimes', 'in:ar,en'],
        ]);

        $locale = $validated['locale'] ?? app()->getLocale();
        $amount = Money::halalas((int) $validated['amount'], (string) config('saudi-payments.currency', 'SAR'));

        $methods = $payments->availableMethods($amount)->map(fn (PaymentMethod $method): array => [
            'key' => $method->key,
            'name' => $method->localizedName($locale),
            'min_amount_halalas' => $method->min_amount_halalas,
            'max_amount_halalas' => $method->max_amount_halalas,
            'logo_url' => $method->settings['logo_url'] ?? null,
        ]);

        return response()->json(['data' => $methods]);
    }
}
