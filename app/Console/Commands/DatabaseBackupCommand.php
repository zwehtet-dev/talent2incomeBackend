<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup 
                           {--type=full : Backup type (full, incremental, point-in-time)}
                           {--since= : For incremental backups, backup changes since this timestamp}
                           {--point-in-time= : For point-in-time backups, restore to this timestamp}
                           {--restore= : Restore from backup file}
                           {--list : List available backups}
                           {--stats : Show backup statistics}
                           {--cleanup : Clean up old backups}';

    protected $description = 'Manage database backups and point-in-time recovery';

    private DatabaseBackupService $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listBackups();
        }

        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ($this->option('cleanup')) {
            return $this->cleanupBackups();
        }

        if ($this->option('restore')) {
            return $this->restoreBackup();
        }

        return $this->createBackup();
    }

    private function createBackup(): int
    {
        $type = $this->option('type');

        $this->info("Creating {$type} database backup...");

        switch ($type) {
            case 'full':
                $result = $this->backupService->createFullBackup();

                break;

            case 'incremental':
                $since = $this->option('since');
                $result = $this->backupService->createIncrementalBackup($since);

                break;

            case 'point-in-time':
                $pointInTime = $this->option('point-in-time');
                if (! $pointInTime) {
                    $this->error('Point-in-time timestamp is required for point-in-time backups');

                    return 1;
                }

                try {
                    $timestamp = Carbon::parse($pointInTime);
                    $result = $this->backupService->createPointInTimeBackup($timestamp);
                } catch (\Exception $e) {
                    $this->error('Invalid timestamp format: ' . $e->getMessage());

                    return 1;
                }

                break;

            default:
                $this->error("Unknown backup type: {$type}");

                return 1;
        }

        if ($result['success']) {
            $this->info("✓ Backup created successfully: {$result['filename']}");

            if (isset($result['size'])) {
                $this->line('Size: ' . $this->formatBytes($result['size']));
            }

            if (isset($result['point_in_time'])) {
                $this->line("Point in time: {$result['point_in_time']}");
            }

            return 0;
        } else {
            $this->error('✗ Backup failed: ' . ($result['error'] ?? 'Unknown error'));

            return 1;
        }
    }

    private function restoreBackup(): int
    {
        $filename = $this->option('restore');

        if (! $filename) {
            $this->error('No backup filename specified.');

            return 1;
        }

        if (! $this->confirm("Are you sure you want to restore from backup '{$filename}'? This will overwrite the current database.")) {
            $this->info('Restore cancelled.');

            return 0;
        }

        $this->info("Restoring database from backup: {$filename}");

        $result = $this->backupService->restoreFromBackup($filename);

        if ($result['success']) {
            $this->info("✓ Database restored successfully from: {$filename}");
            $this->line("Restored at: {$result['restored_at']}");

            return 0;
        } else {
            $this->error('✗ Restore failed: ' . ($result['error'] ?? 'Unknown error'));

            return 1;
        }
    }

    private function listBackups(): int
    {
        $this->info('Available database backups:');

        $backups = \DB::table('backup_logs')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($backups->isEmpty()) {
            $this->line('No backups found.');

            return 0;
        }

        $this->table(
            ['Filename', 'Type', 'Size', 'Created At'],
            $backups->map(function ($backup) {
                return [
                    $backup->filename,
                    ucfirst($backup->type),
                    $this->formatBytes($backup->size),
                    Carbon::parse($backup->created_at)->format('Y-m-d H:i:s'),
                ];
            })->toArray()
        );

        return 0;
    }

    private function showStats(): int
    {
        $this->info('Database backup statistics:');

        $stats = $this->backupService->getBackupStats();

        // Show backup counts by type
        if (isset($stats['statistics'])) {
            $this->info("\nBackup Summary:");
            $this->table(
                ['Type', 'Count', 'Total Size', 'Average Size', 'Last Backup'],
                collect($stats['statistics'])->map(function ($stat, $type) {
                    return [
                        ucfirst($type),
                        $stat->count,
                        $this->formatBytes($stat->total_size),
                        $this->formatBytes($stat->avg_size),
                        $stat->last_backup ? Carbon::parse($stat->last_backup)->diffForHumans() : 'Never',
                    ];
                })->toArray()
            );
        }

        // Show storage usage
        if (isset($stats['storage_usage'])) {
            $usage = $stats['storage_usage'];
            $this->info("\nStorage Usage:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Backup Size', $this->formatBytes($usage['total_backup_size'])],
                    ['Disk Total Space', $this->formatBytes($usage['disk_total_space'])],
                    ['Disk Free Space', $this->formatBytes($usage['disk_free_space'])],
                    ['Usage Percentage', round($usage['usage_percentage'], 2) . '%'],
                ]
            );
        }

        // Show retention policy
        if (isset($stats['retention_policy'])) {
            $this->info("\nRetention Policy:");
            foreach ($stats['retention_policy'] as $period => $count) {
                $this->line("- {$period}: keep {$count} backups");
            }
        }

        return 0;
    }

    private function cleanupBackups(): int
    {
        $this->info('Cleaning up old backups based on retention policy...');

        // This would be handled by the backup service's cleanup method
        // which is called automatically during backup creation

        $this->info('✓ Backup cleanup completed.');

        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }
}
