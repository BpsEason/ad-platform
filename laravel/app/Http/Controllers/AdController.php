<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @OA\Get(
     * path="/api/ads",
     * operationId="getAdsList",
     * tags={"Ads"},
     * summary="Get list of ads for the current tenant",
     * description="Retrieves a paginated list of ads filtered by the authenticated user's tenant ID.",
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
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Ad")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
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
    public function index(Request $request)
    {
        // TenantScope automatically filters ads for the current tenant.
        $ads = Ad::paginate(10);
        return response()->json($ads);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @OA\Post(
     * path="/api/ads",
     * operationId="createAd",
     * tags={"Ads"},
     * summary="Create a new ad for the current tenant",
     * description="Creates a new ad, automatically associating it with the authenticated user's tenant ID.",
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
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(ref="#/components/schemas/AdStoreRequest")
     * ),
     * @OA\Response(
     * response=201,
     * description="Ad created successfully",
     * @OA\JsonContent(ref="#/components/schemas/Ad")
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error"
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
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'content' => 'required|string',
                'start_time' => 'required|date',
                'end_time' => 'required|date|after:start_time',
                'target_audience' => 'nullable|json',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        // tenant_id is automatically set by the Ad model's booted method.
        $ad = Ad::create($validatedData);

        return response()->json($ad, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Ad  $ad
     * @return \Illuminate\Http\JsonResponse
     * @OA\Get(
     * path="/api/ads/{id}",
     * operationId="getAdById",
     * tags={"Ads"},
     * summary="Get a specific ad by ID",
     * description="Retrieves a single ad by its ID, ensuring it belongs to the current tenant.",
     * security={{"bearerAuth": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the ad to retrieve",
     * @OA\Schema(
     * type="integer",
     * format="int64",
     * example=1
     * )
     * ),
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
     * @OA\JsonContent(ref="#/components/schemas/Ad")
     * ),
     * @OA\Response(
     * response=404,
     * description="Ad not found or does not belong to tenant"
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
    public function show(Ad $ad)
    {
        // TenantScope ensures only ads belonging to the current tenant are found.
        return response()->json($ad);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Ad  $ad
     * @return \Illuminate\Http\JsonResponse
     * @OA\Put(
     * path="/api/ads/{id}",
     * operationId="updateAd",
     * tags={"Ads"},
     * summary="Update an existing ad",
     * description="Updates the specified ad, ensuring it belongs to the current tenant.",
     * security={{"bearerAuth": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the ad to update",
     * @OA\Schema(
     * type="integer",
     * format="int64",
     * example=1
     * )
     * ),
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
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(ref="#/components/schemas/AdUpdateRequest")
     * ),
     * @OA\Response(
     * response=200,
     * description="Ad updated successfully",
     * @OA\JsonContent(ref="#/components/schemas/Ad")
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error"
     * ),
     * @OA\Response(
     * response=404,
     * description="Ad not found or does not belong to tenant"
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
    public function update(Request $request, Ad $ad)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'start_time' => 'sometimes|required|date',
                'end_time' => 'sometimes|required|date|after:start_time',
                'target_audience' => 'nullable|json',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $ad->update($validatedData);

        return response()->json($ad);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Ad  $ad
     * @return \Illuminate\Http\JsonResponse
     * @OA\Delete(
     * path="/api/ads/{id}",
     * operationId="deleteAd",
     * tags={"Ads"},
     * summary="Delete an ad",
     * description="Deletes the specified ad, ensuring it belongs to the current tenant.",
     * security={{"bearerAuth": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the ad to delete",
     * @OA\Schema(
     * type="integer",
     * format="int64",
     * example=1
     * )
     * ),
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
     * response=204,
     * description="Ad deleted successfully (No Content)"
     * ),
     * @OA\Response(
     * response=404,
     * description="Ad not found or does not belong to tenant"
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
    public function destroy(Ad $ad)
    {
        $ad->delete();

        return response()->json(null, 204);
    }
}
