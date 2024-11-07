<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="CleanSheet API Documentation",
 *     description="API documentation for the CleanSheet sports management platform",
 *     @OA\Contact(
 *         email="support@cleansheet.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(name="Authentication", description="API Endpoints for user authentication")
 * @OA\Tag(name="Organizations", description="Organization management endpoints")
 * @OA\Tag(name="Teams", description="Team management endpoints")
 * @OA\Tag(name="Team Schedules", description="Team schedule and attendance management endpoints")
 * @OA\Tag(name="Team Tiers", description="Team tier management endpoints")
 * @OA\Tag(name="Matches", description="Match management endpoints")
 * @OA\Tag(name="Players", description="Player profile and management endpoints")
 * @OA\Tag(name="Player Skills", description="Player skill management endpoints")
 * @OA\Tag(name="Player Evaluations", description="Player evaluation management endpoints")
 * @OA\Tag(name="Notifications", description="Notification management endpoints")
 * @OA\Tag(name="Subscriptions", description="Subscription management endpoints")
 * 
 */
class Controller extends BaseController
{
}
