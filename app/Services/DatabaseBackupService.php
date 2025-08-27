<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupService
{
    private string $backupDisk;
    private string $backupPath;
    private array $retentionPolicy;
    private array $compressionSettings;

    public function __construct()
    {
        $this->backupDisk = config('backup.disk', 'local');
        $this->backupPath = config('backup.path', 'backups/database');
        $this->retentionPolicy = config('backup.retention', [
            'daily' => 7,
            'weekly' => 4,
            'monthly' => 12,
        ]);
        $this->compressionSettings = config('backup.compression', [
            'enabled' => true,
            'level' => 6,
        ]);
    }

    /**
     * Create full database backup
     */
    public function createFullBackup(array $options = []): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "full_backup_{$timestamp}.sql";

        if ($this->compressionSettings['enabled']) {
            $filename .= '.gz';
        }

        try {
            $backupPath = $this->backupPath . '/' . $filename;
            $success = $this->performBackup($backupPath, 'full', $options);

            if ($success) {
                $this->recordBackup($filename, 'full', $backupPath);
                $this->cleanupOldBackups();

                Log::info('Full database backup completed', [
                    'filename' => $filename,
                    'path' => $backupPath,
                    'size' => $this->getBackupSize($backupPath),
                ]);

                return [
                    'success' => true,
                    'filename' => $filename,
                    'path' => $backupPath,
                    'size' => $this->getBackupSize($backupPath),
                    'timestamp' => $timestamp,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Database backup failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'error' => 'Backup failed'];
    }

    /**
     * Create incremental backup
     */
    public function createIncrementalBackup(string $lastBackupTime = null): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "incremental_backup_{$timestamp}.sql";

        if ($this->compressionSettings['enabled']) {
            $filename .= '.gz';
        }

        try {
            $backupPath = $this->backupPath . '/' . $filename;
            $options = [];

            if ($lastBackupTime) {
                $options['where'] = "updated_at >= '{$lastBackupTime}'";
            }

            $success = $this->performBackup($backupPath, 'incremental', $options);

            if ($success) {
                $this->recordBackup($filename, 'incremental', $backupPath);

                Log::info('Incremental database backup completed', [
                    'filename' => $filename,
                    'path' => $backupPath,
                    'since' => $lastBackupTime,
                ]);

                return [
                    'success' => true,
                    'filename' => $filename,
                    'path' => $backupPath,
                    'timestamp' => $timestamp,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Incremental backup failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'error' => 'Incremental backup failed'];
    }

    /**
     * Restore database from backup
     */
    public function restoreFromBackup(string $backupFilename, array $options = []): array
    {
        try {
            $backupPath = $this->backupPath . '/' . $backupFilename;

            if (! Storage::disk($this->backupDisk)->exists($backupPath)) {
                return ['success' => false, 'error' => 'Backup file not found'];
            }

            $fullPath = Storage::disk($this->backupDisk)->path($backupPath);
            $success = $this->performRestore($fullPath, $options);

            if ($success) {
                Log::info('Database restored from backup', [
                    'filename' => $backupFilename,
                    'path' => $backupPath,
                ]);

                return [
                    'success' => true,
                    'filename' => $backupFilename,
                    'restored_at' => now(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Database restore failed', [
                'filename' => $backupFilename,
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'error' => 'Restore failed'];
    }

    /**
     * Create point-in-time recovery backup
     */
    public function createPointInTimeBackup(Carbon $pointInTime): array
    {
        $timestamp = $pointInTime->format('Y-m-d_H-i-s');
        $filename = "pit_backup_{$timestamp}.sql";

        if ($this->compressionSettings['enabled']) {
            $filename .= '.gz';
        }

        try {
            // Find the last full backup before the point in time
            $lastFullBackup = $this->findLastFullBackupBefore($pointInTime);

            if (! $lastFullBackup) {
                return ['success' => false, 'error' => 'No full backup found before point in time'];
            }

            // Get all incremental backups between full backup and point in time
            $incrementalBackups = $this->findIncrementalBackupsBetween(
                $lastFullBackup['created_at'],
                $pointInTime
            );

            $backupPath = $this->backupPath . '/' . $filename;
            $success = $this->createPointInTimeRestoreScript($backupPath, $lastFullBackup, $incrementalBackups);

            if ($success) {
                $this->recordBackup($filename, 'point_in_time', $backupPath);

                Log::info('Point-in-time backup created', [
                    'filename' => $filename,
                    'point_in_time' => $pointInTime->toDateTimeString(),
                    'full_backup' => $lastFullBackup['filename'],
                    'incremental_count' => count($incrementalBackups),
                ]);

                return [
                    'success' => true,
                    'filename' => $filename,
                    'point_in_time' => $pointInTime->toDateTimeString(),
                    'full_backup' => $lastFullBackup['filename'],
                    'incremental_backups' => count($incrementalBackups),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Point-in-time backup failed', [
                'point_in_time' => $pointInTime->toDateTimeString(),
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'error' => 'Point-in-time backup failed'];
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats(): array
    {
        $stats = DB::table('backup_logs')
            ->selectRaw('
                type,
                COUNT(*) as count,
                SUM(size) as total_size,
                AVG(size) as avg_size,
                MAX(created_at) as last_backup
            ')
            ->groupBy('type')
            ->get()
            ->keyBy('type')
            ->toArray();

        return [
            'statistics' => $stats,
            'retention_policy' => $this->retentionPolicy,
            'storage_usage' => $this->getStorageUsage(),
        ];
    }

    /**
     * Perform the actual backup operation
     */
    private function performBackup(string $backupPath, string $type, array $options = []): bool
    {
        $config = config('database.connections.' . config('database.default'));

        $command = $this->buildMysqldumpCommand($config, $options);

        if ($this->compressionSettings['enabled']) {
            $command .= ' | gzip -' . $this->compressionSettings['level'];
        }

        $fullPath = Storage::disk($this->backupDisk)->path($backupPath);
        $command .= ' > ' . escapeshellarg($fullPath);

        $result = Process::run($command);

        if ($result->successful()) {
            return true;
        } else {
            Log::error('Backup command failed', [
                'command' => $command,
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            return false;
        }
    }

    /**
     * Build mysqldump command
     */
    private function buildMysqldumpCommand(array $config, array $options = []): string
    {
        $command = 'mysqldump';

        // Connection parameters
        $command .= ' --host=' . escapeshellarg($config['host']);
        $command .= ' --port=' . escapeshellarg($config['port']);
        $command .= ' --user=' . escapeshellarg($config['username']);

        if (! empty($config['password'])) {
            $command .= ' --password=' . escapeshellarg($config['password']);
        }

        // Backup options
        $command .= ' --single-transaction';
        $command .= ' --routines';
        $command .= ' --triggers';
        $command .= ' --lock-tables=false';
        $command .= ' --add-drop-table';
        $command .= ' --create-options';
        $command .= ' --extended-insert';

        // Add WHERE clause for incremental backups
        if (isset($options['where'])) {
            $command .= ' --where=' . escapeshellarg($options['where']);
        }

        // Database name
        $command .= ' ' . escapeshellarg($config['database']);

        return $command;
    }

    /**
     * Perform database restore
     */
    private function performRestore(string $backupPath, array $options = []): bool
    {
        $config = config('database.connections.' . config('database.default'));

        $command = 'mysql';
        $command .= ' --host=' . escapeshellarg($config['host']);
        $command .= ' --port=' . escapeshellarg($config['port']);
        $command .= ' --user=' . escapeshellarg($config['username']);

        if (! empty($config['password'])) {
            $command .= ' --password=' . escapeshellarg($config['password']);
        }

        $command .= ' ' . escapeshellarg($config['database']);

        // Handle compressed backups
        if (str_ends_with($backupPath, '.gz')) {
            $command = 'gunzip -c ' . escapeshellarg($backupPath) . ' | ' . $command;
        } else {
            $command .= ' < ' . escapeshellarg($backupPath);
        }

        $result = Process::run($command);

        if ($result->successful()) {
            return true;
        } else {
            Log::error('Restore command failed', [
                'command' => $command,
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            return false;
        }
    }

    /**
     * Record backup metadata
     */
    private function recordBackup(string $filename, string $type, string $path): void
    {
        DB::table('backup_logs')->insert([
            'filename' => $filename,
            'type' => $type,
            'path' => $path,
            'size' => $this->getBackupSize($path),
            'created_at' => now(),
        ]);
    }

    /**
     * Get backup file size
     */
    private function getBackupSize(string $path): int
    {
        try {
            return Storage::disk($this->backupDisk)->size($path);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clean up old backups based on retention policy
     */
    private function cleanupOldBackups(): void
    {
        foreach ($this->retentionPolicy as $period => $count) {
            $this->cleanupBackupsByPeriod($period, $count);
        }
    }

    /**
     * Clean up backups by period
     */
    private function cleanupBackupsByPeriod(string $period, int $keepCount): void
    {
        $cutoffDate = match ($period) {
            'daily' => now()->subDays($keepCount),
            'weekly' => now()->subWeeks($keepCount),
            'monthly' => now()->subMonths($keepCount),
            default => now()->subDays($keepCount),
        };

        $oldBackups = DB::table('backup_logs')
            ->where('created_at', '<', $cutoffDate)
            ->where('type', 'full')
            ->get();

        foreach ($oldBackups as $backup) {
            try {
                Storage::disk($this->backupDisk)->delete($backup->path);
                DB::table('backup_logs')->where('id', $backup->id)->delete();

                Log::info('Old backup cleaned up', [
                    'filename' => $backup->filename,
                    'created_at' => $backup->created_at,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to cleanup backup', [
                    'filename' => $backup->filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Find last full backup before point in time
     */
    private function findLastFullBackupBefore(Carbon $pointInTime): ?array
    {
        $backup = DB::table('backup_logs')
            ->where('type', 'full')
            ->where('created_at', '<=', $pointInTime)
            ->orderBy('created_at', 'desc')
            ->first();

        return $backup ? (array) $backup : null;
    }

    /**
     * Find incremental backups between two points
     */
    private function findIncrementalBackupsBetween(Carbon $start, Carbon $end): array
    {
        return DB::table('backup_logs')
            ->where('type', 'incremental')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Create point-in-time restore script
     */
    private function createPointInTimeRestoreScript(string $scriptPath, array $fullBackup, array $incrementalBackups): bool
    {
        $script = "#!/bin/bash\n";
        $script .= "# Point-in-time recovery script\n";
        $script .= '# Generated at: ' . now()->toDateTimeString() . "\n\n";

        // Restore full backup first
        $script .= "echo 'Restoring full backup: {$fullBackup['filename']}'\n";
        $script .= $this->generateRestoreCommand($fullBackup['path']) . "\n\n";

        // Apply incremental backups
        foreach ($incrementalBackups as $backup) {
            $script .= "echo 'Applying incremental backup: {$backup->filename}'\n";
            $script .= $this->generateRestoreCommand($backup->path) . "\n\n";
        }

        $script .= "echo 'Point-in-time recovery completed'\n";

        return Storage::disk($this->backupDisk)->put($scriptPath, $script);
    }

    /**
     * Generate restore command for a backup file
     */
    private function generateRestoreCommand(string $backupPath): string
    {
        $config = config('database.connections.' . config('database.default'));

        $command = 'mysql';
        $command .= ' --host=' . $config['host'];
        $command .= ' --port=' . $config['port'];
        $command .= ' --user=' . $config['username'];

        if (! empty($config['password'])) {
            $command .= ' --password=' . $config['password'];
        }

        $command .= ' ' . $config['database'];

        if (str_ends_with($backupPath, '.gz')) {
            $command = 'gunzip -c ' . $backupPath . ' | ' . $command;
        } else {
            $command .= ' < ' . $backupPath;
        }

        return $command;
    }

    /**
     * Get storage usage for backups
     */
    private function getStorageUsage(): array
    {
        $totalSize = DB::table('backup_logs')->sum('size');
        $diskSpace = disk_total_space(Storage::disk($this->backupDisk)->path(''));
        $freeSpace = disk_free_space(Storage::disk($this->backupDisk)->path(''));

        return [
            'total_backup_size' => $totalSize,
            'disk_total_space' => $diskSpace,
            'disk_free_space' => $freeSpace,
            'usage_percentage' => $diskSpace > 0 ? ($totalSize / $diskSpace) * 100 : 0,
        ];
    }
}
