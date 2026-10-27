<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = sys_get_temp_dir().'/backup-test-'.uniqid();
        File::ensureDirectoryExists($this->backupPath);
        config(['hr.backup.path' => $this->backupPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupPath);

        parent::tearDown();
    }

    public function test_command_runs_dump_and_archive_and_prunes_old_sets(): void
    {
        $this->travelTo('2026-10-21 02:00');
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'utg_transport']);
        Process::fake();
        foreach (['db-20250115-020000.sql.gz', 'files-20250115-020000.tar.gz', 'db-20261020-020000.sql.gz', 'files-20261020-020000.tar.gz'] as $name) {
            File::put("{$this->backupPath}/{$name}", 'x');
        }

        $this->artisan('app:backup')->assertExitCode(0);

        Process::assertRan(fn (PendingProcess $process) => in_array('mysqldump', (array) $process->command, true)
            && in_array('utg_transport', (array) $process->command, true)
            && ($process->environment['MYSQL_PWD'] ?? null) !== null);
        Process::assertRan(fn (PendingProcess $process) => ((array) $process->command)[0] === 'tar');
        $this->assertFileDoesNotExist("{$this->backupPath}/db-20250115-020000.sql.gz");
        $this->assertFileDoesNotExist("{$this->backupPath}/files-20250115-020000.tar.gz");
        $this->assertFileExists("{$this->backupPath}/db-20261020-020000.sql.gz");
    }

    public function test_failed_dump_exits_with_error(): void
    {
        config(['database.default' => 'mysql']);
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

        $this->artisan('app:backup')->assertExitCode(1);
    }

    public function test_command_refuses_non_mysql_connection(): void
    {
        config(['database.default' => 'sqlite']);
        Process::fake();

        $this->artisan('app:backup')->assertExitCode(1);

        Process::assertNothingRan();
    }
}
