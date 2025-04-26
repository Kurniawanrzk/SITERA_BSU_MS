<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\BankSampahUnit;
use Illuminate\Http\JsonResponse;

class CheckIfRealUser
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil token dari Authorization header
        $token = $request->header('Authorization');

        if (!$token) {
            return $this->errorResponse("Token Authorization tidak ditemukan.");
        }

        try {
            // Melakukan request ke API untuk mendapatkan profil user
            $response = $this->client->request("GET", env('AUTH_BASE_URI')."/api/v1/auth/profile", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $token
                ],
            ]);

            // Decode JSON response
            $data = json_decode($response->getBody(), true) ?? [];

            // Pastikan response memiliki struktur yang benar
            if (!isset($data['status']) || empty($data['data']['user']['id'])) {
                return $this->errorResponse("Data user tidak valid atau tidak ditemukan.");
            }
            return $next($request);
                

        } catch (RequestException $e) {
            // Tangani error jika request ke API gagal
            return response()->json([
                "status" => false,
                "message" => "Gagal menghubungi server autentikasi",
                "error" => $e->getMessage(),
                "bsu_profile" => false
            ], 500);
        }
    }

    /**
     * Mengembalikan response jika user tidak ditemukan atau terjadi error.
     */
    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            "status" => false,
            "message" => $message,
            "bsu_profile" => false
        ], 400);
    }
}
