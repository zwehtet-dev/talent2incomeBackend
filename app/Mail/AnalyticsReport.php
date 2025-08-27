<?php

namespace App\Mail;

use App\Models\GeneratedReport;
use App\Models\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AnalyticsReport extends Mailable
{
    use Queueable;
    use SerializesModels;

    public GeneratedReport $report;
    public ScheduledReport $scheduledReport;

    public function __construct(GeneratedReport $report, ScheduledReport $scheduledReport)
    {
        $this->report = $report;
        $this->scheduledReport = $scheduledReport;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->report->name . ' - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.analytics-report',
            with: [
                'report' => $this->report,
                'scheduledReport' => $this->scheduledReport,
                'data' => $this->report->data,
                'summary' => $this->generateSummary(),
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->report->file_path && Storage::exists($this->report->file_path)) {
            $attachments[] = Attachment::fromStorage($this->report->file_path)
                ->as($this->report->name . '.json')
                ->withMime('application/json');
        }

        return $attachments;
    }

    protected function generateSummary(): array
    {
        $data = $this->report->data;

        return [
            'period' => $data['period'] ?? [],
            'key_highlights' => $this->extractKeyHighlights($data),
            'alerts' => $data['system_health']['alerts'] ?? [],
        ];
    }

    protected function extractKeyHighlights(array $data): array
    {
        $highlights = [];

        if (isset($data['revenue'])) {
            $highlights[] = [
                'metric' => 'Total Revenue',
                'value' => '$' . number_format($data['revenue']['total_revenue'], 2),
                'growth' => $data['revenue']['growth_rate'] ?? 0,
            ];
        }

        if (isset($data['engagement'])) {
            $highlights[] = [
                'metric' => 'New Registrations',
                'value' => number_format($data['engagement']['total_new_registrations']),
                'growth' => null,
            ];
        }

        if (isset($data['key_metrics'])) {
            $highlights[] = [
                'metric' => 'Active Users',
                'value' => number_format($data['key_metrics']['active_users']),
                'growth' => null,
            ];
        }

        return $highlights;
    }
}
