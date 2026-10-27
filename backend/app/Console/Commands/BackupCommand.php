<?php

namespace App\Console\Commands;

use App\Support\BackupRetention;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupCommand extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Back up the database and uploaded files, then delete backups past the retention period';

    public function handle(): int
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error("Faqat MySQL bazasini zaxiralash mumkin (joriy ulanish: {$connection}).");

            return self::FAILURE;
        }

        $path = config('hr.backup.path');
        File::ensureDirectoryExists($path);
        File::ensureDirectoryExists(storage_path('app/private'));

        $stamp = now()->format('Ymd-His');
        $sqlFile = "{$path}/db-{$stamp}.sql";
        $filesArchive = "{$path}/files-{$stamp}.tar.gz";

        $steps = [
            'dump' => Process::env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])->timeout(3600)->run([
                'mysqldump', '--single-transaction', '--routines', '--no-tablespaces',
                '-h', (string) $db['host'], '-P', (string) $db['port'], '-u', (string) $db['username'],
                "--result-file={$sqlFile}", (string) $db['database'],
            ]),
        ];

        if ($steps['dump']->successful()) {
            $steps['gzip'] = Process::timeout(3600)->run(['gzip', '-f', $sqlFile]);
            $steps['files'] = Process::timeout(3600)->run(['tar', '-czf', $filesArchive, '-C', storage_path('app'), 'private']);
        }

        foreach ($steps as $step => $result) {
            if (! $result->successful()) {
                File::delete([$sqlFile, "{$sqlFile}.gz", $filesArchive]);
                $this->error("Zaxiralash xatosi ({$step}): ".trim($result->errorOutput()));

                return self::FAILURE;
            }
        }

        $this->info("Yozildi: db-{$stamp}.sql.gz, files-{$stamp}.tar.gz");

        foreach ($this->prune($path) as $name) {
            $this->line("O'chirildi: {$name}");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string> Deleted set names.
     */
    private function prune(string $path): array
    {
        $sets = [];

        foreach (File::files($path) as $file) {
            if (preg_match('/^(?:db|files)-(\d{8}-\d{6})\./', $file->getFilename(), $match)) {
                $sets[$match[1]] = CarbonImmutable::createFromFormat('Ymd-His', $match[1]);
            }
        }

        $deleted = BackupRetention::toDelete($sets, now(), config('hr.backup.keep_daily'), config('hr.backup.keep_monthly'));

        foreach ($deleted as $name) {
            File::delete(["{$path}/db-{$name}.sql.gz", "{$path}/files-{$name}.tar.gz"]);
        }

        return $deleted;
    }
}
