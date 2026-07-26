<?php

declare(strict_types=1);

use App\Support\EmailNormalizer;

it('trims and lowercases email addresses', function (string $email, string $expected) {
    expect(EmailNormalizer::normalize($email))->toBe($expected);
})->with([
    'mixed case' => ['User.Name@Example.COM', 'user.name@example.com'],
    'surrounding whitespace' => ['  user@example.com  ', 'user@example.com'],
    'unicode case' => ['????????????@??????.??', '????????????@??????.??'],
]);
