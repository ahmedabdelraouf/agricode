<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = array_values($errors)[0][0];
            return response()->json($this->handleResponse(false, $firstError), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('agriCodeToken')->plainTextToken;
        return response()->json($this->handleResponse(true, 'User logged in successfully.', ['user' => $user, 'token' => $token,]), 201);

    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = array_values($errors)[0][0];
            return response()->json($this->handleResponse(false, $firstError), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json($this->handleResponse(false, "email or password are incorrect."), 422);
        }

        $token = $user->createToken('agriCodeToken')->plainTextToken;

        return response()->json($this->handleResponse(true, 'User logged in successfully.', ['user' => $user, 'token' => $token,]));
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            return response()->json($this->handleResponse(true, 'User logged out successfully.'));
        }
        return response()->json(['error' => 'Invalid token or user not authenticated.'], 401);
    }

    public function predictCrop(Request $request)
    {
        try {
            $request->validate([
                'features' => 'required|array',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = array_values($errors)[0][0];
            return response()->json($this->handleResponse(false, $firstError), 422);
        }
        $features = $request->input('features');

        // Prepare the payload for the API request
        $payload = json_encode(['features' => $features]);

        // Create a GuzzleHTTP client
        $client = new Client();

        try {
            // Make the request to the external API
            $response = $client->post('http://localhost:5000/predictCrop', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload,
            ]);

            // Get the response body
            $responseBody = $response->getBody()->getContents();

            // Return the response from the external API
            return response()->json(json_decode($responseBody), $response->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to make Crop prediction'], 500);
        }
    }

    public function handleResponse($status = false, $message = "something went wrong", ...$data)
    {
        return [
            'message' => $message,
            'status' => $status,
            'data' => $data,
        ];
    }

}
