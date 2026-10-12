<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Philippine mobile or landline number. Spaces, dashes and brackets are allowed for readability:
 * 0917 123 4567, +63 917 123 4567, (043) 457-1234.
 */
class PhilippineContactNumber implements ValidationRule
{
    public const MESSAGE = 'Enter a valid Philippine number, such as 0917 123 4567 or +63 917 123 4567.';

    public static function matches(string $value): bool
    {
        $digits = preg_replace('/[\s\-()]/', '', trim($value));

        // Mobile: 09XX XXX XXXX. Landline: area code (2-8) plus the local number, 9 or 10 digits after the 0.
        return (bool) preg_match('/^(\+?63|0)(9\d{9}|[2-8]\d{8,9})$/', (string) $digits);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::matches($value)) {
            $fail(self::MESSAGE);
        }
    }
}
