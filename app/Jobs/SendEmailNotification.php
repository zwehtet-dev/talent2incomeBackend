<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotification implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(
        public User $user,
        public string $template,
        public array $data = [],
        public ?string $subject = null,
        public array $attachments = []
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            Log::info('Sending email notification', [
                'user_id' => $this->user->id,
                'template' => $this->template,
                'subject' => $this->subject,
            ]);

            $mailableClass = $this->getMailableClass($this->template);

            if (! class_exists($mailableClass)) {
                throw new \InvalidArgumentException("Mailable class {$mailableClass} does not exist");
            }

            $mailable = new $mailableClass($this->user, $this->data, $this->subject);

            // Add attachments if provided
            foreach ($this->attachments as $attachment) {
                $mailable->attach($attachment['path'], [
                    'as' => $attachment['name'] ?? null,
                    'mime' => $attachment['mime'] ?? null,
                ]);
            }

            Mail::to($this->user->email)->send($mailable);

            Log::info('Email notification sent successfully', [
                'user_id' => $this->user->id,
                'template' => $this->template,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $this->user->id,
                'template' => $this->template,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Email notification job failed', [
            'user_id' => $this->user->id,
            'template' => $this->template,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function getMailableClass(string $template): string
    {
        $templateMap = [
            'welcome' => \App\Mail\WelcomeEmail::class,
            'job_application' => \App\Mail\JobApplicationEmail::class,
            'job_completed' => \App\Mail\JobCompletedEmail::class,
            'payment_received' => \App\Mail\PaymentReceivedEmail::class,
            'payment_released' => \App\Mail\PaymentReleasedEmail::class,
            'review_received' => \App\Mail\ReviewReceivedEmail::class,
            'message_received' => \App\Mail\MessageReceivedEmail::class,
            'password_reset' => \App\Mail\PasswordResetEmail::class,
            'email_verification' => \App\Mail\EmailVerificationEmail::class,
            'account_suspended' => \App\Mail\AccountSuspendedEmail::class,
            'dispute_created' => \App\Mail\DisputeCreatedEmail::class,
            'analytics_report' => \App\Mail\AnalyticsReport::class,
            'saved_search_notification' => \App\Mail\SavedSearchNotification::class,
        ];

        return $templateMap[$template] ?? throw new \InvalidArgumentException("Unknown email template: {$template}");
    }
}
