<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
/**
 * @OA\Schema(
 * schema="ConversionRateResponse",
 * title="Conversion Rate Report",
 * @OA\Property(property="impressions", type="integer", example=1000, description="Total impressions recorded"),
 * @OA\Property(property="clicks", type="integer", example=150, description="Total clicks recorded"),
 * @OA\Property(property="conversion_rate", type="string", example="15.00%", description="Calculated conversion rate")
 * )
 * @OA\Schema(
 * schema="EventsReportResponse",
 * title="Daily Events Report",
 * type="object",
 * example={
 * "2025-07-01": {"impression": 100, "click": 10},
 * "2025-07-02": {"impression": 120, "click": 15}
 * },
 * description="Aggregated daily impression and click events."
 * )
 */
class ReportController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/reports/conversions",
     * operationId="getConversionRate",
     * tags={"Reports"},
     * summary="Get overall conversion rate",
     * description="Retrieves the total impressions, clicks, and calculated conversion rate for the current tenant.",
     * security={{"bearerAuth": {}}},
     * @OA\Parameter(
     * name="X-Tenant-Id",
     * in="header",
     * required=true,
     * description="Tenant ID for multi-tenancy. Must match the authenticated user's tenant_id.",
     * @OA\Schema(
     * type="integer",
     * format="int64",
     * example=1
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation",
     * @OA\JsonContent(ref="#/components/schemas/ConversionRateResponse")
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthenticated"
     * ),
     * @OA\Response(
     * response=403,
     * description="Unauthorized / Invalid Tenant ID"
     * )
     * )
     */
    public function getConversionRate()
    {
        $impressions = Event::where('event_type', 'impression')->count();
        $clicks = Event::where('event_type', 'click')->count();
        $conversionRate = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
        return response()->json([
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversion_rate' => round($conversionRate, 2) . '%',
        ]);
    }
    /**
     * @OA\Get(
     * path="/api/reports/events",
     * operationId="getEventsReport",
     * tags={"Reports"},
     * summary="Get daily impression and click events report",
     * description="Retrieves aggregated daily impression and click event counts for the current tenant.",
     * security={{"bearerAuth": {}}},
     * @OA\Parameter(
     * name="X-Tenant-Id",
     * in="header",
     * required=true,
     * description="Tenant ID for multi-tenancy. Must match the authenticated user's tenant_id.",
     * @OA\Schema(
     * type="integer",
     * format="int64",
     * example=1
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation",
     * @OA\JsonContent(ref="#/components/schemas/EventsReportResponse")
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthenticated"
     * ),
     * @OA\Response(
     * response=403,
     * description="Unauthorized / Invalid Tenant ID"
     * )
     * )
     */
    public function getEvents(Request $request)
    {
        $events = Event::select(DB::raw('DATE(occurred_at) as date'), 'event_type', DB::raw('count(*) as count'))
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get();
        $formattedData = [];
        foreach ($events as $event) {
            if (!isset($formattedData[$event->date])) {
                $formattedData[$event->date] = ['impression' => 0, 'click' => 0];
            }
            $formattedData[$event->date][$event->event_type] = $event->count;
        }
        return response()->json($formattedData);
    }
}
