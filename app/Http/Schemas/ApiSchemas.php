<?php

namespace App\Http\Schemas;

use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="first_name", type="string", example="John"),
 *     @OA\Property(property="last_name", type="string", example="Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg"),
 *     @OA\Property(property="bio", type="string", nullable=true, example="Experienced developer"),
 *     @OA\Property(property="location", type="string", nullable=true, example="New York, NY"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="email_verified_at", type="string", nullable=true, example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="average_rating", type="number", format="float", nullable=true, example=4.8),
 *     @OA\Property(property="total_reviews", type="integer", example=25),
 *     @OA\Property(property="jobs_completed", type="integer", example=15),
 *     @OA\Property(property="skills_offered", type="integer", example=3),
 *     @OA\Property(property="created_at", type="string", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", example="2024-01-01T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Job",
 *     type="object",
 *     title="Job",
 *     description="Job listing model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Build a WordPress Website"),
 *     @OA\Property(property="description", type="string", example="Need a professional website for my business"),
 *     @OA\Property(property="budget_min", type="number", format="decimal", nullable=true, example=500.00),
 *     @OA\Property(property="budget_max", type="number", format="decimal", nullable=true, example=1000.00),
 *     @OA\Property(property="budget_type", type="string", enum={"hourly", "fixed", "negotiable"}, example="fixed"),
 *     @OA\Property(property="deadline", type="string", format="date", nullable=true, example="2024-02-15"),
 *     @OA\Property(property="status", type="string", enum={"open", "in_progress", "completed", "cancelled", "expired"}, example="open"),
 *     @OA\Property(property="is_urgent", type="boolean", example=false),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=null),
 *     @OA\Property(property="created_at", type="string", example="2024-01-15T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", example="2024-01-15T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Skill",
 *     type="object",
 *     title="Skill",
 *     description="Skill listing model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="WordPress Development"),
 *     @OA\Property(property="description", type="string", example="Expert WordPress developer with 5+ years experience"),
 *     @OA\Property(property="price_per_hour", type="number", format="decimal", nullable=true, example=50.00),
 *     @OA\Property(property="price_fixed", type="number", format="decimal", nullable=true, example=null),
 *     @OA\Property(property="pricing_type", type="string", enum={"hourly", "fixed", "negotiable"}, example="hourly"),
 *     @OA\Property(property="is_available", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", example="2024-01-15T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", example="2024-01-15T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Category",
 *     description="Category model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Web Development"),
 *     @OA\Property(property="slug", type="string", example="web-development"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Web development services"),
 *     @OA\Property(property="icon", type="string", nullable=true, example="code"),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="Message",
 *     type="object",
 *     title="Message",
 *     description="Message model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="content", type="string", example="Hello, I'm interested in your job posting"),
 *     @OA\Property(property="is_read", type="boolean", example=false),
 *     @OA\Property(property="sender_id", type="integer", example=1),
 *     @OA\Property(property="recipient_id", type="integer", example=2),
 *     @OA\Property(property="job_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", example="2024-01-15T14:30:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     title="Payment",
 *     description="Payment model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="amount", type="number", format="decimal", example=750.00),
 *     @OA\Property(property="platform_fee", type="number", format="decimal", example=37.50),
 *     @OA\Property(property="status", type="string", enum={"pending", "held", "released", "refunded", "failed"}, example="released"),
 *     @OA\Property(property="payment_method", type="string", example="stripe"),
 *     @OA\Property(property="transaction_id", type="string", nullable=true, example="txn_123456"),
 *     @OA\Property(property="job_id", type="integer", example=1),
 *     @OA\Property(property="payer_id", type="integer", example=2),
 *     @OA\Property(property="payee_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", example="2024-01-15T16:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", example="2024-01-15T16:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Review",
 *     type="object",
 *     title="Review",
 *     description="Review model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="comment", type="string", nullable=true, example="Excellent work, delivered on time!"),
 *     @OA\Property(property="is_public", type="boolean", example=true),
 *     @OA\Property(property="job_id", type="integer", example=1),
 *     @OA\Property(property="reviewer_id", type="integer", example=2),
 *     @OA\Property(property="reviewee_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", example="2024-01-15T18:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Pagination Meta",
 *     description="Pagination metadata",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="total", type="integer", example=50),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="last_page", type="integer", example=4),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="to", type="integer", example=15)
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     title="Validation Error",
 *     description="Validation error response",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         ),
 *         example={
 *             "email": {"The email field is required."},
 *             "password": {"The password must be at least 8 characters."}
 *         }
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Generic error response",
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="error", type="string", nullable=true, example="Detailed error message")
 * )
 */
class ApiSchemas
{
    // This class is used only for OpenAPI schema definitions
}
