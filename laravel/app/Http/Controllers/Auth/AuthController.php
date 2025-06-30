<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
/**
 * @OA\Schema(
 * schema="LoginRequest",
 * title="Login Request",
 * required={"email", "password"},
 * @OA\Property(property="email", type="string", format="email", example="viewerB@example.com", description="User's email address"),
 * @OA\Property(property="password", type="string", format="password", example="password", description="User's password")
 * )
 * @OA\Schema(
 * schema="AuthTokenResponse",
 * title="Authentication Token Response",
 * @OA\Property(property="token", type="string", example="1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", description="Sanctum personal access token"),
 * @OA\Property(
 * property="user",
 * type="object",
 * @OA\Property(property="id", type="integer", example=1, description="User ID"),
 * @OA\Property(property="name", type="string", example="Viewer B", description="User Name"),
 * @OA\Property(property="email", type="string", format="email", example="viewerB@example.com", description="User Email"),
 * @OA\Property(property="tenant_id", type="integer", example=2, description="Tenant ID of the user")
 * )
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/login",
     * operationId="userLogin",
     * tags={"Authentication"},
     * summary="Authenticate user and issue API token",
     * description="Authenticates a user with email and password and returns a Sanctum API token.",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     * ),
     * @OA\Response(
     * response=200,
     * description="Login successful",
     * @OA\JsonContent(ref="#/components/schemas/AuthTokenResponse")
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthorized / Invalid credentials",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Unauthorized")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error"
     * )
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('authToken')->plainTextToken;
            return response()->json(['token' => $token, 'user' => $user]);
        }
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    /**
     * @OA\Post(
     * path="/api/logout",
     * operationId="userLogout",
     * tags={"Authentication"},
     * summary="Log out authenticated user",
     * description="Revokes the current user's API token, effectively logging them out.",
     * security={{"bearerAuth": {}}},
     * @OA\Response(
     * response=200,
     * description="Logged out successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Logged out successfully")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthenticated"
     * )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
