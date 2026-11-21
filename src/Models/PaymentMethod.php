<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment option shown on the checkout page. Toggle "enabled" from your admin panel.
 *
 * @property int $id
 * @property string $key gateway key, e.g. "tabby"
 * @property string $name_ar
 * @property string $name_en
 * @property bool $enabled
 * @property int $sort_order
 * @property int|null $min_amount_halalas
 * @property int|null $max_amount_halalas
 * @property array<string, mixed>|null $settings display-only settings (logo, badge text...). Never secrets.
 */
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'enabled',
        'sort_order',
        'min_amount_halalas',
        'max_amount_halalas',
        'settings',
    ];

    protected $attributes = [
        'enabled' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'min_amount_halalas' => 'integer',
            'max_amount_halalas' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeAcceptingAmount(Builder $query, int $halalas): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('min_amount_halalas')->orWhere('min_amount_halalas', '<=', $halalas))
            ->where(fn (Builder $q) => $q->whereNull('max_amount_halalas')->orWhere('max_amount_halalas', '>=', $halalas));
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }
}
