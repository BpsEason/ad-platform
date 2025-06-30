<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Info(
 * version="1.0.0",
 * title="Laravel Ad Platform API",
 * description="API documentation for the Laravel Ad Platform backend, including multi-tenancy support.",
 * @OA\Contact(
 * email="support@example.com"
 * )
 * )
 * @OA\Server(
 * url="http://ad-api.localhost/api",
 * description="Development API Server"
 * )
 * @OA\Tag(
 * name="Authentication",
 * description="API Endpoints for User Authentication"
 * )
 * @OA\Tag(
 * name="Ads",
 * description="API Endpoints for Ad Management (CRUD)"
 * )
 * @OA\Tag(
 * name="Reports",
 * description="API Endpoints for Reporting and Analytics"
 * )
 * @OA\Tag(
 * name="System",
 * description="System health checks and configuration"
 * )
 * @OA\Tag(
 * name="DevOps",
 * description="Endpoints primarily for infrastructure automation"
 * )
 * @OA\SecurityScheme(
 * securityScheme="bearerAuth",
 * type="http",
 * scheme="bearer",
 * bearerFormat="Personal Access Token",
 * description="Enter your bearer token in the format 'Bearer <token>'"
 * )
 * @OA\SecurityScheme(
 * securityScheme="apiKeyAuth",
 * type="apiKey",
 * in="header",
 * name="X-API-Key",
 * description="API Key for accessing sensitive endpoints like Traefik dynamic config."
 * )
 * @OA\Schema(
 * schema="Ad",
 * title="Ad",
 * @OA\Property(property="id", type="integer", readOnly=true, example=1),
 * @OA\Property(property="tenant_id", type="integer", readOnly=true, example=1),
 * @OA\Property(property="name", type="string", example="Summer Sale 2025"),
 * @OA\Property(property="content", type="string", example="Get up to 50% off on all summer collections!"),
 * @OA\Property(property="start_time", type="string", format="date-time", example="2025-07-01T00:00:00Z"),
 * @OA\Property(property="end_time", type="string", format="date-time", example="2025-07-31T23:59:59Z"),
 * @OA\Property(property="target_audience", type="object", nullable=true, example={"gender": "any", "age_min": 18, "interests": {"fashion", "shopping"}}),
 * @OA\Property(property="created_at", type="string", format="date-time", readOnly=true, example="2025-06-30T12:00:00Z"),
 * @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, example="2025-06-30T12:00:00Z")
 * )
 * @OA\Schema(
 * schema="AdStoreRequest",
 * title="Ad Store Request",
 * required={"name", "content", "start_time", "end_time"},
 * @OA\Property(property="name", type="string", example="New Campaign Name"),
 * @OA\Property(property="content", type="string", example="Creative content for the new ad."),
 * @OA\Property(property="start_time", type="string", format="date-time", example="2025-08-01T00:00:00Z"),
 * @OA\Property(property="end_time", type="string", format="date-time", example="2025-08-31T23:59:59Z"),
 * @OA\Property(property="target_audience", type="object", nullable=true, example={"gender": "female", "age_min": 25, "location": "NYC"})
 * )
 * @OA\Schema(
 * schema="AdUpdateRequest",
 * title="Ad Update Request",
 * @OA\Property(property="name", type="string", example="Updated Campaign Name"),
 * @OA\Property(property="content", type="string", example="Revised ad content."),
 * @OA\Property(property="start_time", type="string", format="date-time", example="2025-08-01T00:00:00Z"),
 * @OA\Property(property="end_time", type="string", format="date-time", example="2025-09-15T23:59:59Z"),
 * @OA\Property(property="target_audience", type="object", nullable=true, example={"age_max": 40})
 * )
 */
class TraefikConfigController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/traefik/config",
     * operationId="generateTraefikConfig",
     * tags={"DevOps"},
     * summary="Generate dynamic Traefik HTTP routers configuration based on tenants.",
     * description="This endpoint is used by Traefik's file provider to dynamically configure routing rules based on registered tenants. It returns a JSON configuration that Traefik can consume. This endpoint typically requires strong internal network security or an API key for access.",
     * security={{"apiKeyAuth": {}}},
     * @OA\Response(
     * response=200,
     * description="Successful operation",
     * @OA\JsonContent(
     * type="object",
     * example={
     * "http": {
     * "routers": {
     * "tenant-1-router": {
     * "rule": "Host(`tenant-a.localhost`)",
     * "service": "laravel-1-service",
     * "entryPoints": {"web"}
     * },
     * "tenant-2-router": {
     * "rule": "Host(`tenant-b.localhost`)",
     * "service": "laravel-2-service",
     * "entryPoints": {"web"}
     * }
     * },
     * "services": {
     * "laravel-1-service": {
     * "loadBalancer": {
     * "servers": {
     * {"url": "http://laravel/"}
     * }
     * }
     * },
     * "laravel-2-service": {
     * "loadBalancer": {
     * "servers": {
     * {"url": "http://laravel/"}
     * }
     * }
     * }
     * }
     * }
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthorized - Invalid API Key",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Unauthorized: Invalid API Key")
     * )
     * )
     * )
     * Generate dynamic Traefik HTTP routers configuration based on tenants.
     * This output can be consumed by Traefik's file provider.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateHttpConfig(Request $request)
    {
        // Simple API Key validation for this sensitive endpoint
        $apiKey = $request->header('X-API-Key');
        if (empty($apiKey) || $apiKey !== env('TRAEFIK_API_KEY')) {
            Log::warning('Unauthorized access attempt to Traefik config endpoint.', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized: Invalid API Key'], 401);
        }

        $tenants = Tenant::all();
        $routers = [];
        $services = [];

        foreach ($tenants as $tenant) {
            // Define router for each tenant based on domain
            $routerName = 'tenant-' . $tenant->id . '-router';
            $serviceName = 'laravel-' . $tenant->id . '-service';

            // Router configuration
            $routers[$routerName] = [
                'rule' => "Host(`" . $tenant->domain . "`)",
                'service' => $serviceName,
                'entryPoints' => ['web'], // Or 'websecure' for HTTPS
            ];

            // Service configuration (points to the Laravel Docker service)
            // Assuming your Laravel container is named 'laravel' and serves on port 80
            // Traefik will route to the internal Docker service.
            $services[$serviceName] = [
                'loadBalancer' => [
                    'servers' => [
                        [
                            'url' => 'http://laravel/', // Direct Docker service name
                        ],
                    ],
                ],
            ];
        }

        // Return the configuration in a structure that Traefik's file provider expects
        return response()->json([
            'http' => [
                'routers' => $routers,
                'services' => $services,
            ],
        ])->header('Content-Type', 'application/json');
    }
}
