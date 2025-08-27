<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

/**
 * @OA\Info(
 *     title="Talent2Income API",
 *     version="1.0.0",
 *     description="A comprehensive micro jobs and skill exchange platform API that connects service providers with clients seeking specific tasks or expertise.",
 *     @OA\Contact(
 *         email="api@talent2income.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and session management"
 * )
 *
 * @OA\Tag(
 *     name="Users",
 *     description="User profile management and discovery"
 * )
 *
 * @OA\Tag(
 *     name="Jobs",
 *     description="Job listing management and applications"
 * )
 *
 * @OA\Tag(
 *     name="Skills",
 *     description="Skill listing management and availability"
 * )
 *
 * @OA\Tag(
 *     name="Messages",
 *     description="Real-time messaging and communication"
 * )
 *
 * @OA\Tag(
 *     name="Payments",
 *     description="Payment processing and escrow management"
 * )
 *
 * @OA\Tag(
 *     name="Reviews",
 *     description="Rating and review system"
 * )
 *
 * @OA\Tag(
 *     name="Search",
 *     description="Advanced search and filtering capabilities"
 * )
 *
 * @OA\Tag(
 *     name="Admin",
 *     description="Administrative functions and dashboard"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
