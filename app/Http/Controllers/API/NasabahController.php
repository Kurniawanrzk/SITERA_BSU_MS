<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
class NasabahController extends Controller
{
    protected $client;
    public function __construct()
    {
        $this->client = new Client([
            "timeout" => 5
        ]);
    }

    public function isiSaldoNasabah(Request $request)
    {
        $request->validate([
            "user_id" => "required|string",
            "saldo" => "required"
        ]);

        $response = $this->client("PUT", "http://127.0.0.1:7000/api/v1/nasabah/isi-saldo-nasabah", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token,
            ],
            'json' => [
                "user_id" => $request->user_id,
                "saldo" => $request->saldo
            ]
        ]);

        $response = json_decode($response->getBody());

        return $response;

    }
}
