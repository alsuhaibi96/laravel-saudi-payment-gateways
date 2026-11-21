# Laravel Saudi Payment Gateways

Switch Saudi payment methods (Moyasar, PayTabs, Tabby, Tamara, MISpay) on and off from your
database or admin panel, **without shipping a new mobile app release**.

A Laravel package built around three ideas:

1. **Strategy**: every provider implements one `PaymentGateway` interface.
2. **Factory**: the gateway is picked at runtime from a key stored in the database.
3. **Hosted web checkout + signed webhooks**: the app opens a checkout web page; the final
   payment status only ever comes from a verified, idempotent webhook.

PHP 8.2+, Laravel 11 and 12.

## Why

In a typical Saudi mobile app, customers expect mada and cards, Apple Pay, and buy-now-pay-later
(Tabby, Tamara, MISpay). If each method is wired into the app with its own SDK, then adding a
provider, pausing one during an outage, or enabling BNPL for a sale needs an App Store and Google
Play release, plus waiting for users to update.

Moving checkout to a hosted web page that the app opens (in-app browser / web view) and letting the
backend decide which methods to show turns all of that into a row in a table.

## Architecture

```
 Mobile app                 Your Laravel backend                                  Provider
 ──────────                 ─────────────────────                                 ────────
 "Pay" ──▶ opens checkout   GET /payments/methods?amount=14950
           web page  ─────▶   PaymentManager::availableMethods()  ◀── payment_methods table
                              (enabled, min/max amount, sort order)    (toggled from admin)

 user picks "Tabby" ──────▶ PaymentManager::checkout('tabby', $request)
                              ├─ checks enabled + amount limits
                              ├─ GatewayFactory::make('tabby')  ──▶ TabbyGateway (Strategy)
                              └─ createCheckout() ─────────────────────────────▶ hosted checkout
           ◀──────────── redirect to provider ──────────────────────────────────┘

                            POST /webhooks/payments/tabby  ◀──────────────────── signed webhook
                              ├─ verifyWebhook(): hash_hmac + hash_equals
                              ├─ parseWebhook(): normalized PaymentResult
                              ├─ insertOrIgnore on (gateway, event_id)  → idempotent
                              └─ event(PaymentStatusUpdated)  → your listener marks the order paid
```

```
src/
├── Contracts/PaymentGateway.php        # Strategy interface
├── Drivers/                            # Moyasar, PayTabs, Tabby, Tamara, MISpay (+ AbstractHttpGateway)
├── GatewayFactory.php                  # key -> configured gateway, extend() for custom ones
├── PaymentManager.php                  # availability rules + checkout entry point
├── WebhookProcessor.php                # idempotent recording + event dispatch
├── Models/PaymentMethod.php            # DB-driven methods (name_ar/name_en, limits in halalas)
├── Support/Money.php                   # integer halalas, decimal parsing without floats
├── Data/                               # CheckoutRequest, CheckoutSession, PaymentResult, Customer
└── Http/Controllers/                   # GET /payments/methods, POST /webhooks/payments/{gateway}
```

## Quick start

```bash
composer require alsuhaibi96/laravel-saudi-payment-gateways
php artisan vendor:publish --tag=saudi-payments-config
php artisan migrate
```

Until the package is on Packagist, add the GitHub repo as a VCS repository first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/alsuhaibi96/laravel-saudi-payment-gateways" }]
```

Copy the keys you need from [.env.example](.env.example) into your `.env` (sandbox credentials first).

Seed the methods customers can see. Amounts are **halalas** (100 halalas = 1 SAR):

```php
use Alsuhaibi\SaudiPayments\Models\PaymentMethod;

PaymentMethod::upsert([
    ['key' => 'moyasar', 'name_ar' => 'مدى / بطاقة ائتمانية / Apple Pay', 'name_en' => 'mada / Card / Apple Pay', 'enabled' => true,  'sort_order' => 1, 'min_amount_halalas' => 100,    'max_amount_halalas' => null],
    ['key' => 'tabby',   'name_ar' => 'تابي - قسّمها على 4',              'name_en' => 'Tabby - split in 4',      'enabled' => true,  'sort_order' => 2, 'min_amount_halalas' => 10000,  'max_amount_halalas' => 500000],
    ['key' => 'tamara',  'name_ar' => 'تمارا',                            'name_en' => 'Tamara',                  'enabled' => false, 'sort_order' => 3, 'min_amount_halalas' => 10000,  'max_amount_halalas' => 500000],
], uniqueBy: ['key']);
```

Now toggling `enabled` (from Filament, Nova, a SQL console, anything) changes what the checkout page
shows on the next request.

## Usage

### List methods for the checkout page

The package registers `GET /payments/methods?amount={halalas}&locale={ar|en}`:

```json
{
  "data": [
    { "key": "moyasar", "name": "mada / Card / Apple Pay", "min_amount_halalas": 100, "max_amount_halalas": null, "logo_url": null },
    { "key": "tabby", "name": "Tabby - split in 4", "min_amount_halalas": 10000, "max_amount_halalas": 500000, "logo_url": null }
  ]
}
```

Only methods that are enabled, accept the amount, and have a configured gateway are returned.

### Start a checkout

```php
use Alsuhaibi\SaudiPayments\Data\CheckoutRequest;
use Alsuhaibi\SaudiPayments\Data\Customer;
use Alsuhaibi\SaudiPayments\Exceptions\PaymentMethodUnavailableException;
use Alsuhaibi\SaudiPayments\PaymentManager;
use Alsuhaibi\SaudiPayments\Support\Money;

final class CheckoutController
{
    public function store(StartCheckoutRequest $request, Order $order, PaymentManager $payments)
    {
        try {
            $session = $payments->checkout($request->validated('method'), new CheckoutRequest(
                orderReference: $order->reference,          // unique per order
                amount: Money::halalas($order->total_halalas), // never floats
                customer: new Customer($order->customer_name, $order->customer_phone, $order->customer_email),
                description: "Order {$order->reference}",
                successUrl: route('checkout.done', $order),
                cancelUrl: route('checkout.cancelled', $order),
                webhookUrl: route('saudi-payments.webhook', $request->validated('method')),
                locale: app()->getLocale(),
            ));
        } catch (PaymentMethodUnavailableException $e) {
            return back()->withErrors(['method' => $e->getMessage()]);
        }

        $order->update(['payment_gateway' => $session->gateway, 'payment_reference' => $session->providerReference]);

        return redirect()->away($session->redirectUrl);
    }
}
```

`checkout()` re-checks availability and amount limits on the server: a disabled method or an amount
outside its limits is rejected before any request reaches the provider, even if a stale page
still shows the button.

### Handle the result

Webhooks hit `POST /webhooks/payments/{gateway}`. Each verified event is stored once and fires
`PaymentStatusUpdated` exactly once:

```php
use Alsuhaibi\SaudiPayments\Enums\PaymentStatus;
use Alsuhaibi\SaudiPayments\Events\PaymentStatusUpdated;

final class MarkOrderPaid
{
    public function handle(PaymentStatusUpdated $event): void
    {
        $result = $event->result;
        $order = Order::where('reference', $result->orderReference)->lockForUpdate()->firstOrFail();

        if ($order->isFinal()) {
            return; // never move out of a final state (e.g. paid -> pending)
        }

        if ($result->status === PaymentStatus::Paid && $result->amount?->halalas !== $order->total_halalas) {
            report(new \RuntimeException("Amount mismatch on {$order->reference}"));

            return;
        }

        $order->update(['payment_status' => $result->status->value]);
    }
}
```

The webhook route uses the `api` middleware group (no CSRF). Change prefix/middleware in
`config/saudi-payments.php` under `routes`, or disable the package routes and register your own.

### Add a provider

Implement `PaymentGateway` (or extend `AbstractHttpGateway`) and register it, either in config:

```php
'gateways' => [
    'my-wallet' => ['driver' => App\Payments\MyWalletGateway::class, 'base_url' => '...', 'webhook_secret' => env('MY_WALLET_WEBHOOK_SECRET')],
],
```

or at runtime:

```php
app(GatewayFactory::class)->extend('my-wallet', fn (array $config, $app) => new MyWalletGateway(...));
```

## Design decisions

**Money is integer halalas.** `Money::halalas(14950)`, `Money::fromDecimal('149.50')` (parsed as a
string, never via floats), `->toDecimal()` for APIs that want `"149.50"`. Limits in the
`payment_methods` table are halalas too.

**Webhooks are the source of truth.** The success redirect is UX only: customers close tabs, lose
signal, and redirect URLs can be forged. Orders are marked paid only from a verified webhook (or a
server-to-server status check you add).

**Signature verification.** The default `verifyWebhook()` computes `hash_hmac('sha256', $rawBody, $secret)`
and compares with `hash_equals()` (constant time) against the configured header. An empty secret
never verifies. Providers sign differently (some use a header HMAC, some a token in the payload, some
a JWT), so override `verifyWebhook()` per driver as needed.

**Idempotency.** Providers retry webhooks. `payment_webhook_events` has a unique
`(gateway, event_id)` index and the processor uses `insertOrIgnore`, so concurrent or repeated
deliveries are recorded once and the event fires once. The insert and the event dispatch share a
transaction: if a synchronous listener throws, the row is rolled back and the provider's retry is
processed normally.

**BNPL "authorized" is not "paid".** Tabby and Tamara authorize first and capture later; the drivers
map that to `PaymentStatus::Authorized`, not `Paid`.

**Retries are safe.** The HTTP client retries only on connection errors, never on a response, so a
slow provider cannot cause a duplicate checkout.

## Important: adapt drivers to the provider's current API

The drivers show the shape of each integration (auth style, amount format, where the redirect URL
lives in the response, status mapping), but **providers change their APIs, field names and webhook
signing**. Before going live, check each driver against the provider's current documentation and
sandbox: endpoints and secrets are configurable for exactly that reason, and each driver is a single
small class you can adjust or replace.

## Testing

```bash
composer install
vendor/bin/phpunit
```

52 tests using Orchestra Testbench and `Http::fake()`, covering factory resolution, disabled
methods, amount limits, valid/invalid/tampered webhook signatures, idempotency under retries,
rollback on listener failure, and the request each driver builds. Verified on Laravel 11 and 12.

## Read the full write-up

The architecture, the trade-offs and what it changed in production:
**[Enabling Moyasar, PayTabs, Tabby, Tamara and MISpay dynamically without updating the mobile app](https://alsuhaibi96.com/en/blog/dynamic-payment-gateways-saudi-strategy-factory)**

## License

MIT. See [LICENSE](LICENSE). Not affiliated with any of the payment providers named here; their names
are trademarks of their respective owners.

## Author

**Abdulrahman Alsuhaibi** — https://alsuhaibi96.com
