<?php

declare(strict_types=1);

use App\Enums\ApprovalRank;
use App\Enums\ProjectRole;

it('maps every project role to its immutable approval rank', function (
    ProjectRole $role,
    ApprovalRank $rank,
) {
    expect($role->approvalRank())->toBe($rank);
})->with([
    'participant' => [ProjectRole::PARTICIPANT, ApprovalRank::PARTICIPANT],
    'senior' => [ProjectRole::SENIOR, ApprovalRank::SENIOR],
    'manager' => [ProjectRole::MANAGER, ApprovalRank::MANAGER],
    'project lead' => [ProjectRole::PROJECT_LEAD, ApprovalRank::PROJECT_LEAD],
]);
