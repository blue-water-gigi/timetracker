<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //        $projectMember = ProjectMemberFactory::new()->create();
        //
        //        $project = $projectMember->project;
        //
        //        $memberships = $project->memberships();
        //
        //        dd($memberships);
    }
}
