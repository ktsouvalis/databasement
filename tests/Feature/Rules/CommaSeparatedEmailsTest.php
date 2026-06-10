<?php

use App\Rules\CommaSeparatedEmails;
use Illuminate\Support\Facades\Validator;

test('validates comma-separated email lists', function (string $value, bool $passes) {
    $validator = Validator::make(
        ['to' => $value],
        ['to' => new CommaSeparatedEmails]
    );

    expect($validator->passes())->toBe($passes);
})->with([
    'single address' => ['alice@example.com', true],
    'multiple addresses with spaces' => ['alice@example.com, bob@example.com', true],
    'trailing comma' => ['alice@example.com,', true],
    'invalid address in list' => ['alice@example.com, not-an-email', false],
    'only commas' => [',,', false],
]);
