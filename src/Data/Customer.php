<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Data;

final class Customer
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $email = null,
    ) {
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name), 2)[0];
    }

    public function lastName(): string
    {
        return explode(' ', trim($this->name), 2)[1] ?? '';
    }
}
