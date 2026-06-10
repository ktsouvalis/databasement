<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

class CommaSeparatedEmails implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $emails = self::parse($value);

        if ($emails === []) {
            $fail(__('At least one email address is required.'));

            return;
        }

        foreach ($emails as $email) {
            if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
                $fail(__(':value is not a valid email address.', ['value' => $email]));

                return;
            }
        }
    }

    /**
     * Parse a comma-separated list of email addresses into a clean array.
     *
     * @return list<string>
     */
    public static function parse(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
