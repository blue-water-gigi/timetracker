<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class EmailNormalizer
{
    public static function normalize(string $email): string
    {
        return Str::of($email)->trim()->lower()->toString();
    }
}
