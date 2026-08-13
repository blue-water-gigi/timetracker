<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Enums\TimesheetStatus;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class StatisticsLoadSeeder extends Seeder
{
    private const string DATABASE = 'time_track_load';

    private const int EMPLOYEES = 50;

    private const int PROJECTS = 10;

    private const int WEEKS = 52;

    private const int ENTRIES_PER_TIMESHEET = 5;

    private const int INSERT_CHUNK = 1000;

    private const string FIRST_WEEK_START = '2025-08-11';

    public function run(): void
    {
        $this->assertSafeDatabase();

        $password = (string) config('performance.statistics_load.password');

        if ($password === '') {
            throw new RuntimeException('LOAD_TEST_PASSWORD must be configured.');
        }

        $timestamp   = CarbonImmutable::parse('2026-08-13 12:00:00');
        $adminId     = $this->createAdministrator($password, $timestamp);
        $workspaceId = $this->createWorkspace($adminId, $timestamp);
        $projectIds  = $this->createProjects($workspaceId, $adminId, $timestamp);
        $employeeIds = $this->createEmployees($workspaceId, $password, $timestamp);

        $this->createMemberships($projectIds, $employeeIds, $timestamp);
        $this->createTimesheets($workspaceId, $projectIds, $employeeIds, $adminId, $timestamp);
        $this->createTimeEntries($timestamp);
        $this->assertExpectedCounts();
        $this->analyzeTables();

        $this->command->info(sprintf(
            'Statistics load data created: workspace_id=%d, project_id=%d, users=%d, timesheets=%d, time_entries=%d.',
            $workspaceId,
            $projectIds[0],
            self::EMPLOYEES + 1,
            self::EMPLOYEES * self::PROJECTS * self::WEEKS,
            self::EMPLOYEES * self::PROJECTS * self::WEEKS * self::ENTRIES_PER_TIMESHEET,
        ));
    }

    private function assertSafeDatabase(): void
    {
        $database = (string) data_get(
            DB::selectOne('SELECT current_database() AS name'),
            'name',
        );

        if ($database !== self::DATABASE) {
            throw new RuntimeException(
                'StatisticsLoadSeeder can run only on '.self::DATABASE.'.',
            );
        }

        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'StatisticsLoadSeeder requires an empty migrated load database.',
            );
        }
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'StatisticsLoadSeeder can run only in the local environment.',
            );
        }

    }

    private function createAdministrator(string $password, CarbonImmutable $timestamp): int
    {
        return (int) DB::table('users')->insertGetId([
            'workspace_id'      => null,
            'system_role'       => SystemRole::ADMINISTRATOR->value,
            'first_name'        => 'Statistics',
            'last_name'         => 'Load Admin',
            'email'             => 'statistics-load-admin@example.test',
            'email_verified_at' => $timestamp,
            'password'          => Hash::make($password),
            'created_at'        => $timestamp,
            'updated_at'        => $timestamp,
        ]);
    }

    private function createWorkspace(int $adminId, CarbonImmutable $timestamp): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'owner_id'   => $adminId,
            'name'       => 'Statistics Load Organization',
            'slug'       => 'statistics-load-organization',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return (int) DB::table('workspaces')->insertGetId([
            'organization_id' => $organizationId,
            'name'            => 'Statistics Load Workspace',
            'slug'            => 'statistics-load-workspace',
            'description'     => 'Generated only for local statistics load tests.',
            'join_code_hash'  => Workspace::hashJoinCode('statistics-load-join-code'),
            'active'          => true,
            'created_at'      => $timestamp,
            'updated_at'      => $timestamp,
        ]);
    }

    /** @return list<int> */
    private function createProjects(int $workspaceId, int $adminId, CarbonImmutable $timestamp): array
    {
        $rows = [];

        foreach (range(1, self::PROJECTS) as $number) {
            $rows[] = [
                'workspace_id'       => $workspaceId,
                'created_by_user_id' => $adminId,
                'updated_by_user_id' => $adminId,
                'name'               => sprintf('Statistics Load Project %02d', $number),
                'description'        => 'Generated only for local statistics load tests.',
                'slug'               => sprintf('statistics-load-project-%02d', $number),
                'active'             => true,
                'period_start'       => self::FIRST_WEEK_START,
                'period_end'         => '2026-08-13',
                'created_at'         => $timestamp,
                'updated_at'         => $timestamp,
            ];
        }

        DB::table('projects')->insert($rows);

        return DB::table('projects')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function createEmployees(
        int $workspaceId,
        string $password,
        CarbonImmutable $timestamp,
    ): array {
        $passwordHash = Hash::make($password);
        $rows         = [];

        foreach (range(1, self::EMPLOYEES) as $number) {
            $rows[] = [
                'workspace_id'      => $workspaceId,
                'system_role'       => SystemRole::EMPLOYEE->value,
                'first_name'        => 'Load',
                'last_name'         => sprintf('User %03d', $number),
                'email'             => sprintf('statistics-load-user-%03d@example.test', $number),
                'email_verified_at' => $timestamp,
                'password'          => $passwordHash,
                'created_at'        => $timestamp,
                'updated_at'        => $timestamp,
            ];
        }

        $this->insertInChunks('users', $rows);

        return DB::table('users')
            ->where('workspace_id', $workspaceId)
            ->orderBy('email')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $projectIds
     * @param  list<int>  $employeeIds
     */
    private function createMemberships(
        array $projectIds,
        array $employeeIds,
        CarbonImmutable $timestamp,
    ): void {
        $managementRoles = ProjectRole::management();
        $rows            = [];

        foreach ($projectIds as $projectId) {
            foreach ($employeeIds as $index => $employeeId) {
                $role   = $managementRoles[$index % count($managementRoles)];
                $rows[] = [
                    'project_id'    => $projectId,
                    'user_id'       => $employeeId,
                    'project_role'  => $role->value,
                    'approval_rank' => $role->approvalRank()->value,
                    'active'        => true,
                    'created_at'    => $timestamp,
                    'updated_at'    => $timestamp,
                ];
            }
        }

        $this->insertInChunks('project_members', $rows);
    }

    /**
     * @param  list<int>  $projectIds
     * @param  list<int>  $employeeIds
     */
    private function createTimesheets(
        int $workspaceId,
        array $projectIds,
        array $employeeIds,
        int $adminId,
        CarbonImmutable $timestamp,
    ): void {
        $firstWeekStart = CarbonImmutable::parse(self::FIRST_WEEK_START);
        $rows           = [];
        $sequence       = 0;

        foreach ($projectIds as $projectId) {
            foreach ($employeeIds as $employeeId) {
                foreach (range(0, self::WEEKS - 1) as $weekOffset) {
                    $periodStart  = $firstWeekStart->addWeeks($weekOffset);
                    $periodEnd    = $periodStart->addDays(6);
                    $status       = $this->statusForSequence($sequence);
                    $wasSubmitted = $status !== TimesheetStatus::DRAFT;
                    $wasReviewed  = in_array($status, TimesheetStatus::reviewDecisions(), true);

                    $rows[] = [
                        'workspace_id'        => $workspaceId,
                        'project_id'          => $projectId,
                        'user_id'             => $employeeId,
                        'period_start'        => $periodStart->toDateString(),
                        'period_end'          => $periodEnd->toDateString(),
                        'status'              => $status->value,
                        'reviewed_by_user_id' => $wasReviewed ? $adminId : null,
                        'review_comment'      => $wasReviewed ? 'Statistics load review.' : null,
                        'submitted_at'        => $wasSubmitted ? $periodEnd->endOfDay() : null,
                        'reviewed_at'         => $wasReviewed ? $periodEnd->addDay()->startOfDay() : null,
                        'created_at'          => $timestamp,
                        'updated_at'          => $timestamp,
                    ];
                    $sequence++;

                    if (count($rows) === self::INSERT_CHUNK) {
                        $this->insertInChunks('timesheets', $rows);
                        $rows = [];
                    }
                }
            }
        }

        $this->insertInChunks('timesheets', $rows);
    }

    private function statusForSequence(int $sequence): TimesheetStatus
    {
        return match ($sequence % 20) {
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 => TimesheetStatus::APPROVED,
            16, 17                                               => TimesheetStatus::SUBMITTED,
            18                                                   => TimesheetStatus::DRAFT,
            default                                              => TimesheetStatus::REJECTED,
        };
    }

    private function createTimeEntries(CarbonImmutable $timestamp): void
    {
        $hoursByDay = [6, 7, 8, 8, 4];

        DB::table('timesheets')
            ->select(['id', 'period_start'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $timesheets) use ($hoursByDay, $timestamp): void {
                $rows = [];

                foreach ($timesheets as $timesheet) {
                    $periodStart = CarbonImmutable::parse((string) data_get($timesheet, 'period_start'));
                    $timesheetId = (int) data_get($timesheet, 'id');

                    foreach ($hoursByDay as $dayOffset => $hours) {
                        $rows[] = [
                            'timesheet_id' => $timesheetId,
                            'work_date'    => $periodStart->addDays($dayOffset)->toDateString(),
                            'description'  => 'Statistics load entry.',
                            'hours'        => $hours,
                            'is_overtime'  => (($timesheetId * self::ENTRIES_PER_TIMESHEET + $dayOffset) % 10) === 0,
                            'created_at'   => $timestamp,
                            'updated_at'   => $timestamp,
                        ];
                    }
                }

                $this->insertInChunks('time_entries', $rows);
            });
    }

    /** @param list<array<string, mixed>> $rows */
    private function insertInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function analyzeTables(): void
    {
        foreach ([
            'users',
            'projects',
            'project_members',
            'timesheets',
            'time_entries',
        ] as $table) {
            DB::statement('ANALYZE '.$table);
        }
    }

    private function assertExpectedCounts(): void
    {
        $expectedCounts = [
            'users'           => self::EMPLOYEES + 1,
            'organizations'   => 1,
            'workspaces'      => 1,
            'projects'        => self::PROJECTS,
            'project_members' => self::EMPLOYEES * self::PROJECTS,
            'timesheets'      => self::EMPLOYEES * self::PROJECTS * self::WEEKS,
            'time_entries'    => self::EMPLOYEES * self::PROJECTS * self::WEEKS * self::ENTRIES_PER_TIMESHEET,
        ];

        foreach ($expectedCounts as $table => $expectedCount) {
            $actualCount = DB::table($table)->count();

            if ($actualCount !== $expectedCount) {
                throw new RuntimeException(sprintf(
                    'Unexpected %s count: expected %d, got %d.',
                    $table,
                    $expectedCount,
                    $actualCount,
                ));
            }
        }

        $expectedStatuses = [
            TimesheetStatus::APPROVED->value  => 20_800,
            TimesheetStatus::SUBMITTED->value => 2_600,
            TimesheetStatus::DRAFT->value     => 1_300,
            TimesheetStatus::REJECTED->value  => 1_300,
        ];

        foreach ($expectedStatuses as $status => $expectedCount) {
            $actualCount = DB::table('timesheets')->where('status', $status)->count();

            if ($actualCount !== $expectedCount) {
                throw new RuntimeException(sprintf(
                    'Unexpected %s timesheet count: expected %d, got %d.',
                    $status,
                    $expectedCount,
                    $actualCount,
                ));
            }
        }
    }
}
