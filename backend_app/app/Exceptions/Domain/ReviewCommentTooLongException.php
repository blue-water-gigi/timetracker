<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class ReviewCommentTooLongException extends TimesheetValidationException
{
    public static function make(): self
    {
        return new self(
            'The review comment must not be greater than 500 characters.',
            'review_comment',
        );
    }

    public function errorCode(): string
    {
        return 'review_comment_too_long';
    }
}
