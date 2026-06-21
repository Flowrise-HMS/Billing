<?php

namespace Modules\Billing\Services\Concerns;

trait NormalizesMoney
{
    protected function normalizeMoneyString(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
