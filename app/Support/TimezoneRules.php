<?php

namespace App\Support;

use Closure;
use DateTimeZone;

class TimezoneRules
{
    private const ALIASES = [
        'Europe/Nicosia' => 'Asia/Nicosia',
    ];

    /**
     * @return array<int, string|Closure>
     */
    public static function required(): array
    {
        return [
            'required',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! in_array(self::normalize($value), ['UTC', ...DateTimeZone::listIdentifiers()], true)) {
                    $fail(__('The :attribute must be a valid timezone.'));
                }
            },
        ];
    }

    public static function normalize(string $timezone): string
    {
        return self::ALIASES[$timezone] ?? $timezone;
    }
}
