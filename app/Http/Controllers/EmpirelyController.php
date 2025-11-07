<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmpirelyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class EmpirelyController extends Controller
{
    protected $empirelyService;

    public function __construct(EmpirelyService $empirelyService)
    {
        $this->empirelyService = $empirelyService;
    }

    public function register(Request $request)
    {
        Log::info('Empirely Register Request: ', $request->all());
        http_response_code(200); // Set the response code to 200 OK to allow Empirely to proceed
        return $this->empirelyService->register($request->all());
    }

    /**
     * Check Empirely user status in VoiceNotely
     */
    public function statusCheck(Request $request)
    {
        $email = str_replace(' ', '+', $request->email);
        if($email==""){
            return [
                    'status' => 'error',
                    'statusCode' => 400,
                    'message' => "Email is required."
                ];
        }
        $user = User::where(['email' => $email])->first();
        if($user) {
            return response()->json([
                'email' => $user->email,
                'isActive' => $user->status,
                'currentPlan' => $user->plan_code,//'MTYCOON',
                'created' => $user->created_at->format('Y-m-d H:i:s')
            ], 200);
        }
        return response()->json([
            // 'email' => $request->email,
            // 'IsActive' => null,
            // 'currentPlan' => null,
            // 'created' => null,
            "status"=>"error",
            "statusCode"=>404,
            'message' => 'User not found',
        ], 404);

    }
    public function login(Request $request)
    {
        // $dummyEncryptedKey = "4b35ade9028fd3ccbad77c8b4411f1024b35ade9028fd3ccbad77c8b4411f102";
        $dummyEncryptedKey = config('app.auto_login_key');

        if ($request->secretKey !== $dummyEncryptedKey) {
            return response()->json([
                'success' => false,
                'status' => 302,
                'url' => 'https://app.empirely.ai/',
                'message' => 'Login Failed',
                // 'token' => $token,
                // 'user' => $user
            ], 302);
        }
        $email = str_replace(' ', '+', $request->email);
        $user = User::where('email', $email)->first();

        if($user){
            if ($user->status == 1) {

                // Auth::guard('web')->login($user);
                $token = $user->createToken('empirely-auto-login')->plainTextToken;
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Login Success',
                    'token' => $token,
                    'user' => $user
                ], 200);
            }
        }else{
            Log::info('Empirely Login Request: ', $request->all());
            Log::error("User not found with email: $email");
        }
        return response()->json([
            'success' => false,
            'status' => 302,
            'url' => 'https://app.empirely.ai/',
            'message' => 'Login Failed',
            // 'token' => $token,
            // 'user' => $user
        ], 302);
        return redirect()->away('https://app.empirely.ai/');
    }
}
