<?php

namespace App\Console\Commands;

use App\Models\SecurityIncident;
use App\Services\AuditService;
use App\Services\SecurityIncidentService;
use Illuminate\Console\Command;

class SecurityIncidentMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:monitor 
                           {--check-incidents : Check for incidents requiring attention}
                           {--auto-resolve : Auto-resolve false positives}
                           {--send-alerts : Send alerts for critical incidents}
                           {--all : Run all monitoring tasks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor security incidents and send alerts';

    /**
     * Execute the console command.
     */
    public function handle(SecurityIncidentService $securityService, AuditService $auditService)
    {
        $this->info('Starting security incident monitoring...');

        $checkIncidents = $this->option('check-incidents') || $this->option('all');
        $autoResolve = $this->option('auto-resolve') || $this->option('all');
        $sendAlerts = $this->option('send-alerts') || $this->option('all');

        $results = [];

        if ($checkIncidents) {
            $results['incidents_requiring_attention'] = $this->checkIncidentsRequiringAttention($securityService);
        }

        if ($autoResolve) {
            $results['auto_resolved'] = $this->autoResolveFalsePositives($securityService);
        }

        if ($sendAlerts) {
            $results['alerts_sent'] = $this->sendCriticalAlerts($securityService);
        }

        if (empty($results)) {
            $this->warn('No monitoring options specified. Use --check-incidents, --auto-resolve, --send-alerts, or --all');

            return 1;
        }

        // Display statistics
        $this->displaySecurityStatistics($securityService);

        // Log the monitoring run
        $auditService->log(
            'security.monitoring_executed',
            null,
            null,
            null,
            'Security incident monitoring executed',
            'info',
            false,
            [
                'results' => $results,
                'executed_via' => 'console_command',
            ]
        );

        $this->info('Security monitoring completed');

        return 0;
    }

    /**
     * Check for incidents requiring immediate attention.
     */
    private function checkIncidentsRequiringAttention(SecurityIncidentService $securityService): int
    {
        $incidents = $securityService->getIncidentsRequiringAttention();

        if ($incidents->isEmpty()) {
            $this->info('No incidents requiring immediate attention');

            return 0;
        }

        $this->warn("Found {$incidents->count()} incidents requiring attention:");

        $tableData = $incidents->map(function ($incident) {
            return [
                $incident->id,
                $incident->incident_type,
                $incident->severity,
                $incident->title,
                $incident->created_at->diffForHumans(),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Type', 'Severity', 'Title', 'Created'],
            $tableData
        );

        return $incidents->count();
    }

    /**
     * Auto-resolve false positives.
     */
    private function autoResolveFalsePositives(SecurityIncidentService $securityService): int
    {
        $this->info('Auto-resolving false positives...');

        $resolvedCount = $securityService->autoResolveFalsePositives();

        if ($resolvedCount > 0) {
            $this->info("Auto-resolved {$resolvedCount} false positive incidents");
        } else {
            $this->info('No false positives found to auto-resolve');
        }

        return $resolvedCount;
    }

    /**
     * Send alerts for critical incidents.
     */
    private function sendCriticalAlerts(SecurityIncidentService $securityService): int
    {
        $criticalIncidents = SecurityIncident::where('severity', SecurityIncident::SEVERITY_CRITICAL)
            ->where('status', SecurityIncident::STATUS_OPEN)
            ->where('notification_sent', false)
            ->get();

        if ($criticalIncidents->isEmpty()) {
            $this->info('No critical incidents requiring alerts');

            return 0;
        }

        $alertsSent = 0;
        foreach ($criticalIncidents as $incident) {
            // In a real implementation, you would send actual email alerts
            $this->warn("CRITICAL ALERT: {$incident->title} (ID: {$incident->id})");

            // Mark as notified
            $incident->update(['notification_sent' => true]);
            $alertsSent++;
        }

        $this->warn("Sent {$alertsSent} critical incident alerts");

        return $alertsSent;
    }

    /**
     * Display security statistics.
     */
    private function displaySecurityStatistics(SecurityIncidentService $securityService): void
    {
        $stats = $securityService->getIncidentStatistics(7); // Last 7 days

        $this->info("\n=== Security Statistics (Last 7 Days) ===");
        $this->info("Total Incidents: {$stats['total_incidents']}");

        if (! empty($stats['by_severity'])) {
            $this->info('By Severity:');
            foreach ($stats['by_severity'] as $severity => $count) {
                $this->info("  {$severity}: {$count}");
            }
        }

        if (! empty($stats['by_type'])) {
            $this->info('By Type:');
            foreach ($stats['by_type'] as $type => $count) {
                $this->info("  {$type}: {$count}");
            }
        }

        $this->info("Open Critical: {$stats['open_critical']}");

        if ($stats['average_resolution_time']) {
            $this->info("Average Resolution Time: {$stats['average_resolution_time']} hours");
        }

        if (! empty($stats['top_source_ips'])) {
            $this->info('Top Source IPs:');
            foreach (array_slice($stats['top_source_ips'], 0, 5) as $ip => $count) {
                $this->info("  {$ip}: {$count} incidents");
            }
        }
    }
}
