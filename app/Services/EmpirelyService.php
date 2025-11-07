<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmpirelyService
{
    public function register($data)
    {
        try {
            // Validate the incoming request data
            $validatedData = $this->validateRegisterData($data);

            // Register the user in the Empirely system
            $response = $this->registerUser($validatedData);
        } catch (ValidationException $e) {
            // Handle validation failure
            Log::error('Empirely Registration Validation Error: ', $e->errors());
            return [
                'status' => 'error',
                'statusCode' => 400,
                'message' => $e->errors()
            ];
        } catch (\Exception $e) {
            // Handle any other exceptions
            Log::error('Empirely Registration Error: '. $e->getMessage());
            return [
                'status' => "error",
                'statusCode' => 500,
                'message' => $e->getMessage(),
                // 'error' => $e->getMessage(),
            ];
        }

        return $response;
    }

    protected function validateRegisterData($data)
    {
        // replace any spaces in email with +
        if(!isset($data['email'])){
            throw ValidationException::withMessages([
                'email' => 'Email is required.'
            ]);
        }
        $data['email'] = str_replace(' ', '+', trim($data['email']));

        // Define the validation rules
        $rules = [
            'email' => 'required|email|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'plan_code' => 'required|string',
            'trans_type' => 'required|string|in:SALE,TEST_SALE,RFND',
            'password' => 'nullable|string|min:5',
            'send_email' => 'nullable|boolean',
        ];

        // Validate the data
        return validator($data, $rules)->validate();
    }

    protected function registerUser($data)
    {
        // Register the user in the Empirely system
        // $assignedPassword = $data['password'] ?? md5(uniqid(rand(), true));
        $updating = false;
        $existingUser = User::where('email', $data['email'])->first();
        if($existingUser){
            $updating = true;
        }else{
            if( $data['trans_type'] === 'RFND' ){
                return [
                    'status' => 'error',
                    'statusCode' => 400,
                    'message' => "User doesn't exist"
                ];
            }
            if(!isset($data["password"])){
                return [
                    'status' => 'error',
                    'statusCode' => 400,
                    'message' => "Password field is required for registering user."
                ];
            }
            
        }

        $empirelyUser = $existingUser ?? new User();
        //$empirelyUser->name = $data['first_name'] . ' ' . $data['last_name'];
        $empirelyUser->name = $data['first_name'] . ' ' . $data['last_name'];
        $empirelyUser->email = $data['email'];
        $empirelyUser->plan_code = $data['plan_code'];
        $empirelyUser->password = $empirelyUser->password??Hash::make($data['password']);
        $empirelyUser->email_verified_at = $empirelyUser->email_verified_at ?? now();
        // $empirelyUser->status = $data['trans_type'] === 'SALE' || $data['trans_type'] === 'TEST_SALE' ? 1 : 0;
        if( $data['trans_type'] === 'RFND' ){
            $empirelyUser->status = 0;
        }elseif( $data['trans_type'] === 'SALE' || $data['trans_type'] === 'TEST_SALE' ){
            $empirelyUser->status = 1;
        }
        if ($empirelyUser->save()) {
            // $plan_code = strtolower(trim($data['plan_code']));
            // $yearly = strpos($plan_code, '-yearly') !== false;

            // // Remove '-YEARLY' from $plan_code if present
            // $plan_code = str_replace('-yearly', '', $plan_code);

            // // Clean up $plan_code by removing leading 'm' if present, and replace 'ogul' with 'mogul'
            // $plan_code = ltrim($plan_code, 'm');
            // $plan_code = ($plan_code === 'ogul') ? 'mogul' : $plan_code;
            unset($data["password"]);
            $response = [
                'status' => "success",
                'statusCode' => 200,
                'message' => $empirelyUser->status == 1 ? 'User '.($updating?'updated':'registered ').' successfully' : 'User set to inactive',
                // 'data' => $data
            ];
        } else {
            $response = [
                'status' => "error",
                'statusCode' => 500,
                'message' => 'User registration failed',
            ];
        }

        return $response;
    }

}
