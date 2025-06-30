<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
/**
 * @OA\Schema(
 * schema="HealthCheckResponse",
 * title="Health Check Response",
 * @OA\Property(property="status", type="string", example="ok", description="Status of the service"),
 * @OA\Property(property="message", type="string", example="Service is healthy", description="Detailed health message")
 * )
 */
class HealthController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/health",
     * operationId="healthCheck",
     * tags={"System"},
     * summary="Perform a simple health check",
     * description="Returns a simple status indicating if the Laravel service is running.",
     * @OA\Response(
     * response=200,
     * description="Service is healthy",
     * @OA\JsonContent(ref="#/components/schemas/HealthCheckResponse")
     * )
     * )
     */
    public function healthCheck()
    {
        return response()->json(['status' => 'ok', 'message' => 'Service is healthy']);
    }
}
