<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Create realistic rating distribution (weighted towards positive)
        $rating = fake()->randomElement([
            1, 1,           // 2 out of 20 (10%)
            2, 2,           // 2 out of 20 (10%)
            3, 3, 3,        // 3 out of 20 (15%)
            4, 4, 4, 4, 4,  // 5 out of 20 (25%)
            5, 5, 5, 5, 5, 5, 5, 5,  // 8 out of 20 (40%)
        ]);

        $positiveComments = [
            'Excellent work! Delivered exactly what I needed on time.',
            'Great communication and high-quality results. Highly recommended!',
            'Professional, efficient, and exceeded my expectations.',
            'Amazing attention to detail. Will definitely work with again.',
            'Fast delivery and perfect execution. Thank you!',
            'Outstanding work quality and very responsive to feedback.',
            'Exactly what I was looking for. Great job!',
            'Professional service and excellent results. 5 stars!',
            'Delivered ahead of schedule with exceptional quality.',
            'Very satisfied with the work. Highly professional.',
        ];

        $neutralComments = [
            'Good work overall, met the basic requirements.',
            'Decent quality, though could have been better communicated.',
            'Satisfactory results, delivered on time.',
            'Average work, nothing exceptional but got the job done.',
            'Met expectations, would consider working together again.',
        ];

        $negativeComments = [
            'Work was below expectations and delivered late.',
            'Poor communication and had to request multiple revisions.',
            'Did not follow the brief properly, disappointing results.',
            'Unprofessional behavior and missed several deadlines.',
            'Quality was not up to standard, would not recommend.',
        ];

        $comment = match ($rating) {
            5, 4 => fake()->randomElement($positiveComments),
            3 => fake()->randomElement($neutralComments),
            2, 1 => fake()->randomElement($negativeComments),
            default => fake()->sentence(),
        };

        return [
            'job_id' => Job::factory(),
            'reviewer_id' => User::factory(),
            'reviewee_id' => User::factory(),
            'rating' => $rating,
            'comment' => fake()->boolean(85) ? $comment : null, // 15% chance of no comment
            'is_public' => fake()->boolean(95),
            'is_flagged' => fake()->boolean(5),
            'flagged_reason' => null,
            'moderated_at' => null,
            'moderated_by' => null,
        ];
    }

    /**
     * Create a 5-star review.
     */
    public function fiveStars(): static
    {
        $excellentComments = [
            'Absolutely fantastic work! Exceeded all my expectations.',
            'Perfect execution and amazing attention to detail.',
            'Outstanding professional who delivers exceptional results.',
            "Couldn't be happier with the quality and service.",
            'Top-notch work, will definitely hire again!',
        ];

        return $this->state(fn (array $attributes) => [
            'rating' => 5,
            'comment' => fake()->randomElement($excellentComments),
            'is_public' => true,
            'is_flagged' => false,
        ]);
    }

    /**
     * Create a 4-star review.
     */
    public function fourStars(): static
    {
        $goodComments = [
            'Very good work, just minor issues that were quickly resolved.',
            'Great quality and professional service. Recommended!',
            'Solid work with good communication throughout.',
            'Happy with the results, delivered as promised.',
            'Good experience overall, would work together again.',
        ];

        return $this->state(fn (array $attributes) => [
            'rating' => 4,
            'comment' => fake()->randomElement($goodComments),
            'is_public' => true,
            'is_flagged' => false,
        ]);
    }

    /**
     * Create a 3-star review.
     */
    public function threeStars(): static
    {
        $averageComments = [
            'Acceptable work, met basic requirements.',
            'Average quality, nothing special but got the job done.',
            'Okay results, could have been better with more attention to detail.',
            'Satisfactory work, delivered on time.',
            'Met expectations, though communication could improve.',
        ];

        return $this->state(fn (array $attributes) => [
            'rating' => 3,
            'comment' => fake()->randomElement($averageComments),
            'is_public' => true,
            'is_flagged' => false,
        ]);
    }

    /**
     * Create a 2-star review.
     */
    public function twoStars(): static
    {
        $poorComments = [
            'Below expectations, had to request multiple revisions.',
            'Poor communication and missed some requirements.',
            'Work quality was not up to standard.',
            'Disappointing results, would not recommend.',
            'Had issues with delivery and quality.',
        ];

        return $this->state(fn (array $attributes) => [
            'rating' => 2,
            'comment' => fake()->randomElement($poorComments),
            'is_public' => true,
            'is_flagged' => false,
        ]);
    }

    /**
     * Create a 1-star review.
     */
    public function oneStar(): static
    {
        $terribleComments = [
            'Terrible experience, work was completely unsatisfactory.',
            'Unprofessional behavior and very poor quality work.',
            'Did not deliver what was promised, waste of time and money.',
            'Extremely disappointed, would never work with again.',
            'Poor quality, missed deadlines, and bad communication.',
        ];

        return $this->state(fn (array $attributes) => [
            'rating' => 1,
            'comment' => fake()->randomElement($terribleComments),
            'is_public' => true,
            'is_flagged' => fake()->boolean(20), // Higher chance of being flagged
        ]);
    }

    /**
     * Create a positive review (4-5 stars).
     */
    public function positive(): static
    {
        return fake()->boolean(60) ? $this->fiveStars() : $this->fourStars();
    }

    /**
     * Create a negative review (1-2 stars).
     */
    public function negative(): static
    {
        return fake()->boolean(40) ? $this->oneStar() : $this->twoStars();
    }

    /**
     * Create a neutral review (3 stars).
     */
    public function neutral(): static
    {
        return $this->threeStars();
    }

    /**
     * Create a review without comment.
     */
    public function withoutComment(): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => null,
        ]);
    }

    /**
     * Create a flagged review.
     */
    public function flagged(string $reason = Review::FLAG_INAPPROPRIATE): static
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
            'flagged_reason' => $reason,
            'is_public' => false,
        ]);
    }

    /**
     * Create a private review.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Create a moderated review.
     */
    public function moderated(int $moderatorId, bool $approved = true): static
    {
        return $this->state(fn (array $attributes) => [
            'moderated_by' => $moderatorId,
            'moderated_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'is_flagged' => ! $approved,
            'is_public' => $approved,
        ]);
    }

    /**
     * Create a review for a specific job.
     */
    public function forJob(int $jobId): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $jobId,
        ]);
    }

    /**
     * Create a review between specific users.
     */
    public function between(int $reviewerId, int $revieweeId): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_id' => $reviewerId,
            'reviewee_id' => $revieweeId,
        ]);
    }

    /**
     * Create a review from a specific reviewer.
     */
    public function from(int $reviewerId): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_id' => $reviewerId,
        ]);
    }

    /**
     * Create a review for a specific reviewee.
     */
    public function forReviewee(int $revieweeId): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewee_id' => $revieweeId,
        ]);
    }

    /**
     * Create a recent review.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create an old review.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Create a detailed review with long comment.
     */
    public function detailed(): static
    {
        $detailedComments = [
            'This freelancer provided exceptional service from start to finish. The communication was clear and frequent, keeping me updated on progress throughout the project. The quality of work exceeded my expectations, and they were very responsive to feedback and requests for revisions. I would definitely recommend them to others and will be hiring them again for future projects.',
            'Working with this professional was a great experience. They understood the requirements perfectly and delivered exactly what I needed. The work was completed on time and the quality was outstanding. They were also very patient with my questions and provided helpful suggestions to improve the final result. Highly recommended!',
            "I'm very satisfied with the work delivered. The freelancer was professional, communicative, and delivered high-quality results within the agreed timeframe. They paid attention to all the details and made sure everything was perfect before final delivery. Will definitely work with them again in the future.",
        ];

        return $this->state(fn (array $attributes) => [
            'comment' => fake()->randomElement($detailedComments),
            'rating' => fake()->randomElement([4, 5]),
        ]);
    }

    /**
     * Create reviews with realistic distribution for a user.
     *
     * @param int $revieweeId
     * @param int $count
     * @return Review[] // Specify the type of array values
     */
    public static function createRealisticDistribution(int $revieweeId, int $count = 10): array
    {
        $reviews = [];

        // 60% positive (4-5 stars)
        $positiveCount = (int) ($count * 0.6);
        for ($i = 0; $i < $positiveCount; $i++) {
            $reviews[] = Review::factory()->positive()->forReviewee($revieweeId);
        }

        // 25% neutral (3 stars)
        $neutralCount = (int) ($count * 0.25);
        for ($i = 0; $i < $neutralCount; $i++) {
            $reviews[] = Review::factory()->neutral()->forReviewee($revieweeId);
        }

        // 15% negative (1-2 stars)
        $negativeCount = $count - $positiveCount - $neutralCount;
        for ($i = 0; $i < $negativeCount; $i++) {
            $reviews[] = Review::factory()->negative()->forReviewee($revieweeId);
        }

        return $reviews;
    }
}
