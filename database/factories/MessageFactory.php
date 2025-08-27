<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $messageTemplates = [
            "Hi! I'm interested in your job posting. Could we discuss the details?",
            'I have experience in this area and would love to work on your project.',
            'When would be a good time to start this project?',
            "I've reviewed your requirements and have some questions.",
            "Thank you for considering my proposal. I'm available to start immediately.",
            'Could you provide more details about the timeline?',
            "I've completed similar projects before. Here's my portfolio link.",
            "What's your budget range for this project?",
            'I can deliver this within the specified deadline.',
            'Let me know if you need any clarifications about my approach.',
            "I'm excited to work on this project with you!",
            "The project is progressing well. I'll have an update by tomorrow.",
            "I've finished the first phase. Please review and let me know your feedback.",
            'Thank you for the payment. It was a pleasure working with you!',
            'Could we schedule a call to discuss the project requirements?',
            'I have some suggestions that might improve the project outcome.',
            'The deliverables are ready for your review.',
            'I appreciate your patience. The project will be completed on time.',
            'Your feedback has been incorporated into the latest version.',
            'Looking forward to working together on future projects!',
        ];

        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'job_id' => fake()->boolean(70) ? Job::factory() : null,
            'content' => fake()->randomElement($messageTemplates),
            'is_read' => fake()->boolean(60),
        ];
    }

    /**
     * Create an unread message.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Create a read message.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Create a message related to a specific job.
     */
    public function forJob(int $jobId): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $jobId,
        ]);
    }

    /**
     * Create a message without job context.
     */
    public function withoutJob(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => null,
        ]);
    }

    /**
     * Create a message between specific users.
     */
    public function between(int $senderId, int $recipientId): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
        ]);
    }

    /**
     * Create a message from a specific sender.
     */
    public function from(int $senderId): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $senderId,
        ]);
    }

    /**
     * Create a message to a specific recipient.
     */
    public function to(int $recipientId): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_id' => $recipientId,
        ]);
    }

    /**
     * Create a conversation thread (multiple messages between same users).
     *
     * @param int $user1Id
     * @param int $user2Id
     * @param int|null $jobId
     * @param int $messageCount
     * @return array<int, \Illuminate\Database\Eloquent\Factories\Factory>
     */
    public function conversation(int $user1Id, int $user2Id, ?int $jobId = null, int $messageCount = 5): array
    {
        $messages = [];
        $currentSender = $user1Id;
        $currentRecipient = $user2Id;

        for ($i = 0; $i < $messageCount; $i++) {
            $messages[] = $this->state([
                'sender_id' => $currentSender,
                'recipient_id' => $currentRecipient,
                'job_id' => $jobId,
                'is_read' => $i < $messageCount - 2, // Last 2 messages unread
                'created_at' => now()->subMinutes(($messageCount - $i) * 30),
            ]);

            // Alternate sender and recipient
            [$currentSender, $currentRecipient] = [$currentRecipient, $currentSender];
        }

        return $messages;
    }

    /**
     * Create a long message.
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(3, true),
        ]);
    }

    /**
     * Create a short message.
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create a recent message.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Create an old message.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
        ]);
    }

    /**
     * Create a project inquiry message.
     */
    public function projectInquiry(): static
    {
        $inquiryMessages = [
            "Hi! I saw your job posting and I'm very interested. I have 5+ years of experience in this field.",
            "Hello! Your project looks interesting. I'd love to discuss how I can help you achieve your goals.",
            "Hi there! I specialize in exactly what you're looking for. Can we schedule a call to discuss?",
            "Good day! I've worked on similar projects before and would be happy to share my portfolio with you.",
            "Hello! I'm available to start immediately and can deliver within your timeline. Let's connect!",
        ];

        return $this->state(fn (array $attributes) => [
            'content' => fake()->randomElement($inquiryMessages),
            'is_read' => false,
        ]);
    }

    /**
     * Create a project update message.
     */
    public function projectUpdate(): static
    {
        $updateMessages = [
            "Hi! Just wanted to update you on the project progress. I'm about 60% done and everything is on track.",
            "Good news! I've completed the first milestone ahead of schedule. Please review when you have time.",
            'The project is coming along nicely. I should have the next deliverable ready by tomorrow.',
            "I've incorporated your feedback and made the requested changes. Please let me know what you think.",
            "Almost done! I'll have the final version ready for your review by end of day.",
        ];

        return $this->state(fn (array $attributes) => [
            'content' => fake()->randomElement($updateMessages),
            'is_read' => fake()->boolean(80),
        ]);
    }
}
