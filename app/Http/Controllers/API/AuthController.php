<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader;
use League\Csv\Statement;

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
        return response()->json($this->handleResponse(true, 'User logged in successfully.',
            ['user' => $user, 'token' => $token,]), 201);

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
        return response()->json($this->handleResponse(true, 'User logged in successfully.',
            ['user' => $user, 'token' => $token,]));
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
            $request->validate(['features' => 'required|array',]);
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
            return response()->json($this->handleResponse(true, 'Crop prediction is successfull performed',
                json_decode($responseBody)), $response->getStatusCode());
        } catch (\Exception $e) {
            return response()->json($this->handleResponse(false, 'Failed to make Crop prediction'), 422);
        }
    }

    public function getSoilType($soilType)
    {
        /*feature elements in order:
Soil_Type, ({'Black': 0, 'Clayey': 1, 'Loamy': 2, 'Red': 3, 'Sandy': 4})
Crop_Type, ({'Barley': 0, 'Cotton': 1, 'Cucumber': 2, 'Maize': 3, 'Millets': 4, 'Oil seeds': 5, 'Paddy': 6, 'Sugarcane': 7, 'Tobacco': 8, 'Wheat': 9, 'rice': 10, 'tomatoes': 11})
N,
P,
K*/
        switch ($soilType) {
            case 0:
                return "Black";
            case 1:
                return "Clayey";
            case 2:
                return "Loamy";
            case 3:
                return "Red";
            case 4:
                return "Sandy";
            default:
                return "Black";
        }

    }

    public function getFertilizerPrediction($soilType, $cropType)
    {
        dd($soilType, $cropType);
        $csvFilePath = public_path('Fertilizer_Prediction_with_Fertilization.csv');
        $matchedRow = [];
        if (($handle = fopen($csvFilePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if ($data[0] == $soilType && $data[5] == $cropType) {
                    $matchedRow = $data;
                    break;
                }
            }
            fclose($handle);
        }
        return $matchedRow;
    }

    public function predictFertilizer(Request $request)
    {
        // Validate the incoming request
        $request->validate(['features' => 'required|array']);
        $features = $request->input('features');
        // Prepare the payload for the API request
        $payload = json_encode(['features' => $features]);
        $client = new Client();
//        try {
            // Make the request to the external API
            $response = $client->post('http://localhost:5000/predictFertilizer', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload,
            ]);
            // Get the response body
            $responseBody = $response->getBody()->getContents();
            $response = json_decode($responseBody);
            $v = $this->getFertilizerPrediction($this->getSoilType($features[0]),$response[0]);
            dd($features,$response,$v);
            // Return the response from the external API
            return response()->json($this->handleResponse(true, 'fertilizer prediction is successfull performed',
                json_decode($responseBody)), $response->getStatusCode());
//        } catch (\Exception $e) {
//            return response()->json($this->handleResponse(false, 'Failed to make fertilizer prediction'), 422);
//        }
    }

    public function predictDisease(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',]);
        $image = $request->file('image');
        $imageContent = file_get_contents($image->getRealPath());
        $base64Image = base64_encode($imageContent);
        $payload = ['image' => $base64Image];
        $client = new Client();
        try {
            $response = $client->post('http://localhost:5000/predict-disease', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            $responseBody = $response->getBody()->getContents();
            return response()->json($this->handleResponse(true, 'Disease prediction is successfull performed',
                json_decode($responseBody)), $response->getStatusCode());
        } catch (\Exception $e) {
            return response()->json($this->handleResponse(false, 'Failed to make fertilizer prediction'), 422);
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
