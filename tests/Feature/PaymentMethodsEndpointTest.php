<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Feature;

use Alsuhaibi\SaudiPayments\Tests\TestCase;

final class PaymentMethodsEndpointTest extends TestCase
{
    public function test_it_lists_enabled_methods_for_the_amount_in_order(): void
    {
        $this->paymentMethod('moyasar', ['name_ar' => 'مدى / بطاقة', 'name_en' => 'mada / Card', 'sort_order' => 1, 'settings' => ['logo_url' => '/img/mada.svg']]);
        $this->paymentMethod('tabby', ['name_ar' => 'تابي', 'name_en' => 'Tabby', 'sort_order' => 2, 'min_amount_halalas' => 10_000]);
        $this->paymentMethod('tamara', ['name_ar' => 'تمارا', 'name_en' => 'Tamara', 'sort_order' => 3, 'enabled' => false]);

        $this->getJson('/payments/methods?amount=14950&locale=en')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['key' => 'moyasar', 'name' => 'mada / Card', 'min_amount_halalas' => null, 'max_amount_halalas' => null, 'logo_url' => '/img/mada.svg'],
                ['key' => 'tabby', 'name' => 'Tabby', 'min_amount_halalas' => 10_000, 'max_amount_halalas' => null, 'logo_url' => null],
            ]]);
    }

    public function test_bnpl_is_hidden_below_its_minimum(): void
    {
        $this->paymentMethod('moyasar');
        $this->paymentMethod('tabby', ['min_amount_halalas' => 10_000]);

        $this->getJson('/payments/methods?amount=5000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'moyasar');
    }

    public function test_it_returns_arabic_names(): void
    {
        $this->paymentMethod('tamara', ['name_ar' => 'تمارا']);

        $this->getJson('/payments/methods?amount=14950&locale=ar')->assertJsonPath('data.0.name', 'تمارا');
    }

    public function test_toggling_a_method_in_the_database_takes_effect_immediately(): void
    {
        $method = $this->paymentMethod('tabby');
        $this->getJson('/payments/methods?amount=14950')->assertJsonCount(1, 'data');

        $method->update(['enabled' => false]);

        $this->getJson('/payments/methods?amount=14950')->assertJsonCount(0, 'data');
    }

    public function test_amount_is_required_and_must_be_halalas(): void
    {
        $this->getJson('/payments/methods')->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->getJson('/payments/methods?amount=149.50')->assertUnprocessable()->assertJsonValidationErrors('amount');
    }
}
