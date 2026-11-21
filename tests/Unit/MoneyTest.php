<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Unit;

use Alsuhaibi\SaudiPayments\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function decimals(): iterable
    {
        yield 'whole riyals' => ['150', 15_000];
        yield 'one decimal' => ['149.5', 14_950];
        yield 'two decimals' => ['149.99', 14_999];
        yield 'halalas only' => ['0.01', 1];
    }

    #[DataProvider('decimals')]
    public function test_it_parses_decimals_without_floats(string $decimal, int $halalas): void
    {
        $this->assertSame($halalas, Money::fromDecimal($decimal)->halalas);
    }

    public function test_it_formats_to_two_decimals(): void
    {
        $this->assertSame('149.50', Money::halalas(14_950)->toDecimal());
        $this->assertSame('0.05', Money::halalas(5)->toDecimal());
    }

    public function test_it_rejects_negative_amounts_and_bad_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::halalas(-1);
    }

    public function test_it_rejects_more_than_two_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.005');
    }

    public function test_equality_includes_currency(): void
    {
        $this->assertTrue(Money::halalas(100)->equals(Money::halalas(100)));
        $this->assertFalse(Money::halalas(100)->equals(Money::halalas(100, 'AED')));
    }
}
