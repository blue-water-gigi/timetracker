<?php

namespace App\Exceptions\Domain;

class ReviewCommentRequiredException extends TimesheetValidationException
{
    public static function make(): self
    {
        return new self(
            'The review comment field is required when rejecting a timesheet.',
            'review_comment',
        );
    }

    public function errorCode(): string
    {
        return 'review_comment_required';
    }
}
