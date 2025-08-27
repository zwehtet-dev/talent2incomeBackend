<?php

namespace App\Console\Commands;

use App\Services\QueueMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueWorkerManager extends Command
{
    protected $signature = 'queue:workers 
                           {action : Action to perform (status|start|stop|restart|scale)}
                           {--workers=1 : Number of workers to start}
                           {--queue=* : Specific queues to work on}
                           {--timeout=60 : Worker timeout in seconds}
                           {--memory=128 : Memory limit in MB}
                           {--sleep=3 : Sleep time when no jobs available}
                           {--tries=3 : Number of attempts for failed jobs}
                           {--daemon : Run workers as daemon processes}
                           {--force : Force action without confirmation}';

    protected $description = 'Manage queue worker processes with scaling and monitoring';

    protected array $defaultQueues = [
        'high', 'default', 'emails', 'payments', 'search', 'analytics', 'cleanup', 'reports', 'low',
    ];

    public function handle(QueueMonitoringService $monitor): int
    {
        $action = $this->argument('action');

        try {
            return match ($action) {
                'status' => $this->showWorkerStatus($monitor),
                'start' => $this->startWorkers($monitor),
                'stop' => $this->stopWorkers(),
                'restart' => $this->restartWorkers($monitor),
                'scale' => $this->scaleWorkers($monitor),
                default => $this->error("Unknown action: {$action}") ?: 1,
            };
        } catch (\Exception $e) {
            $this->error("Worker management failed: {$e->getMessage()}");
            Log::error('Queue worker management failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    protected function showWorkerStatus(QueueMonitoringService $monitor): int
    {
        $this->info('Queue Worker Status');
        $this->line('==================');

        $workerHealth = $monitor->checkWorkerHealth();
        $queueStats = $monitor->getQueueStatistics();

        // Display worker summary
        $statusIcon = $workerHealth['healthy'] ? '✅' : '⚠️';
        $this->line("{$statusIcon} Active workers: {$workerHealth['active_workers']}");
        $this->line("📊 Recommended workers: {$workerHealth['recommended_workers']}");
        $this->line("📋 Total pending jobs: {$workerHealth['total_pending_jobs']}");
        $this->line('');

        // Display detailed worker processes
        $this->displayWorkerProcesses();

        // Display queue load distribution
        $this->displayQueueLoad($queueStats);

        return $workerHealth['healthy'] ? 0 : 1;
    }

    protected function displayWorkerProcesses(): void
    {
        $this->info('Active Worker Processes:');
        $this->line('========================');

        $output = shell_exec('ps aux | grep "queue:work" | grep -v grep');

        if (empty(trim($output))) {
            $this->warn('No active worker processes found');

            return;
        }

        $lines = explode("\n", trim($output));
        $headers = ['PID', 'CPU%', 'Memory%', 'Started', 'Command'];
        $rows = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $rows[] = [
                    $parts[1], // PID
                    $parts[2], // CPU%
                    $parts[3], // Memory%
                    $parts[8], // Started time
                    substr($parts[10], 0, 60) . '...', // Command (truncated)
                ];
            }
        }

        if (! empty($rows)) {
            $this->table($headers, $rows);
        }

        $this->line('');
    }

    protected function displayQueueLoad(array $queueStats): void
    {
        $this->info('Queue Load Distribution:');
        $this->line('========================');

        $headers = ['Queue', 'Pending', 'Processing', 'Load Level'];
        $rows = [];

        foreach ($queueStats as $queueName => $stats) {
            $loadLevel = $this->calculateLoadLevel($stats['pending'], $stats['processing']);
            $loadIcon = match ($loadLevel) {
                'low' => '🟢',
                'medium' => '🟡',
                'high' => '🟠',
                'critical' => '🔴',
                default => '⚪',
            };

            $rows[] = [
                $queueName,
                number_format($stats['pending']),
                number_format($stats['processing']),
                $loadIcon . ' ' . $loadLevel,
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    protected function calculateLoadLevel(int $pending, int $processing): string
    {
        $total = $pending + $processing;

        return match (true) {
            $total >= 1000 => 'critical',
            $total >= 500 => 'high',
            $total >= 100 => 'medium',
            default => 'low',
        };
    }

    protected function startWorkers(QueueMonitoringService $monitor): int
    {
        $workerCount = (int) $this->option('workers');
        $queues = $this->option('queue') ?: $this->defaultQueues;
        $timeout = (int) $this->option('timeout');
        $memory = (int) $this->option('memory');
        $sleep = (int) $this->option('sleep');
        $tries = (int) $this->option('tries');
        $daemon = $this->option('daemon');

        $queueString = implode(',', $queues);

        $this->info("Starting {$workerCount} worker(s) for queues: {$queueString}");

        if (! $this->option('force')) {
            if (! $this->confirm('Start the worker processes?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $startedWorkers = 0;

        for ($i = 0; $i < $workerCount; $i++) {
            $command = $this->buildWorkerCommand($queues, $timeout, $memory, $sleep, $tries, $daemon);

            if ($daemon) {
                // Start as background daemon process
                $command .= ' > /dev/null 2>&1 &';
                shell_exec($command);
            } else {
                // Start as regular process (for development/testing)
                $this->info('Starting worker ' . ($i + 1) . '...');
                $this->line("Command: {$command}");
            }

            $startedWorkers++;

            // Small delay between starting workers
            if ($i < $workerCount - 1) {
                sleep(1);
            }
        }

        $this->info("✅ Started {$startedWorkers} worker process(es)");

        // Update monitoring
        $monitor->updateLastJobTime('workers');

        Log::info('Queue workers started', [
            'worker_count' => $startedWorkers,
            'queues' => $queues,
            'daemon' => $daemon,
        ]);

        return 0;
    }

    protected function buildWorkerCommand(
        array $queues,
        int $timeout,
        int $memory,
        int $sleep,
        int $tries,
        bool $daemon
    ): string {
        $command = 'php ' . base_path('artisan') . ' queue:work redis';
        $command .= ' --queue=' . implode(',', $queues);
        $command .= " --timeout={$timeout}";
        $command .= " --memory={$memory}";
        $command .= " --sleep={$sleep}";
        $command .= " --tries={$tries}";

        if ($daemon) {
            $command .= ' --daemon';
        }

        return $command;
    }

    protected function stopWorkers(): int
    {
        $this->info('Stopping queue workers...');

        if (! $this->option('force')) {
            if (! $this->confirm('Stop all worker processes?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        // Get worker PIDs
        $output = shell_exec('ps aux | grep "queue:work" | grep -v grep | awk \'{print $2}\'');
        $pids = array_filter(explode("\n", trim($output)));

        if (empty($pids)) {
            $this->info('No worker processes found to stop.');

            return 0;
        }

        $stoppedCount = 0;

        foreach ($pids as $pid) {
            if (is_numeric($pid)) {
                // Send SIGTERM for graceful shutdown
                $result = shell_exec("kill -TERM {$pid} 2>/dev/null; echo $?");

                if (trim($result) === '0') {
                    $stoppedCount++;
                    $this->line("Stopped worker process {$pid}");
                }
            }
        }

        // Wait a moment for graceful shutdown
        sleep(2);

        // Force kill any remaining processes
        $remainingOutput = shell_exec('ps aux | grep "queue:work" | grep -v grep | awk \'{print $2}\'');
        $remainingPids = array_filter(explode("\n", trim($remainingOutput)));

        foreach ($remainingPids as $pid) {
            if (is_numeric($pid)) {
                shell_exec("kill -KILL {$pid} 2>/dev/null");
                $this->line("Force killed worker process {$pid}");
            }
        }

        $this->info("✅ Stopped {$stoppedCount} worker process(es)");

        Log::info('Queue workers stopped', [
            'stopped_count' => $stoppedCount,
        ]);

        return 0;
    }

    protected function restartWorkers(QueueMonitoringService $monitor): int
    {
        $this->info('Restarting queue workers...');

        // Stop existing workers
        $this->stopWorkers();

        // Wait for cleanup
        sleep(3);

        // Start new workers
        return $this->startWorkers($monitor);
    }

    protected function scaleWorkers(QueueMonitoringService $monitor): int
    {
        $this->info('Analyzing worker scaling requirements...');

        $scalingActions = $monitor->scaleWorkers();

        if (empty($scalingActions)) {
            $this->info('✅ Worker scaling is optimal - no action needed');

            return 0;
        }

        foreach ($scalingActions as $action) {
            $this->displayScalingAction($action);

            if (! $this->option('force')) {
                if (! $this->confirm('Execute this scaling action?')) {
                    $this->info('Scaling action skipped.');

                    continue;
                }
            }

            if ($action['action'] === 'scale_up') {
                $this->option(['workers' => $action['workers_to_start']]);
                $this->startWorkers($monitor);
            } elseif ($action['action'] === 'scale_down') {
                $this->info('⚠️ Scaling down requires manual intervention to avoid disrupting running jobs');
                $this->line('Consider stopping specific worker processes manually');
            }
        }

        return 0;
    }

    protected function displayScalingAction(array $action): void
    {
        $this->line('');
        $this->info('Scaling Recommendation:');
        $this->line('======================');

        $actionIcon = $action['action'] === 'scale_up' ? '📈' : '📉';
        $this->line("{$actionIcon} Action: " . ucfirst(str_replace('_', ' ', $action['action'])));
        $this->line("Current workers: {$action['current_workers']}");
        $this->line("Target workers: {$action['target_workers']}");

        if (isset($action['workers_to_start'])) {
            $this->line("Workers to start: {$action['workers_to_start']}");
        }

        if (isset($action['workers_to_stop'])) {
            $this->line("Workers to stop: {$action['workers_to_stop']}");
        }

        $this->line('');
    }
}
