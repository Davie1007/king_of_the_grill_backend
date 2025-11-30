<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DynamicMpesaService
{
    protected $app;
    protected $branch;

    public function __construct($branchId)
    {
        $this->branch = Branch::with('darajaApp')->findOrFail($branchId);

        if ($this->branch->darajaApp) {
            $this->app = $this->branch->darajaApp;
        } else {
            // Fallback to .env credentials
            $this->app = new \stdClass();
            $this->app->id = 'env'; // Marker for default
            $this->app->consumer_key = env('MPESA_CONSUMER_KEY');
            $this->app->consumer_secret = env('MPESA_CONSUMER_SECRET');
            $this->app->shortcode = env('MPESA_SHORTCODE');
            $this->app->passkey = env('MPESA_PASSKEY');
            $this->app->environment = env('MPESA_ENV', 'sandbox');
        }
    }

    protected function getAccessToken()
    {
        $cacheKey = 'mpesa_access_token_' . ($this->app->id === 'env' ? 'env' : $this->app->id);

        return Cache::remember($cacheKey, 50 * 60, function () {
            $url = $this->app->environment === 'live'
                ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
                : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

            $response = Http::withBasicAuth($this->app->consumer_key, $this->app->consumer_secret)->get($url);

            if ($response->failed()) {
                Log::error('Failed to get M-Pesa token', [
                    'app_id' => $this->app->id,
                    'response' => $response->body()
                ]);
                throw new \Exception('Failed to get M-Pesa token');
            }

            return $response->json()['access_token'];
        });
    }

    protected function generatePassword($timestamp)
    {
        return base64_encode($this->app->shortcode . $this->app->passkey . $timestamp);
    }

    public function stkPush($phone, $amount, $accountReference = 'POS', $callbackUrl = null)
    {
        $timestamp = now()->format('YmdHis');
        $password = $this->generatePassword($timestamp);

        $url = $this->app->environment === 'live'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        // Determine PartyB (the till/paybill to receive funds):
        // - If branch has a DarajaApp, use the app's shortcode (this is the till number)
        // - Otherwise, use branch's tillNumber field (for branches using default .env credentials)
        // - Final fallback: app shortcode
        $partyB = $this->app->shortcode;

        // If using default env credentials and branch has a specific till number, use it
        if ($this->app->id === 'env' && $this->branch->tillNumber) {
            $partyB = $this->branch->tillNumber;
        }

        // Determine transaction type based on whether PartyB differs from BusinessShortCode
        $transactionType = ($partyB !== $this->app->shortcode)
            ? 'CustomerBuyGoodsOnline'
            : 'CustomerPayBillOnline';

        // Determine callback URL
        if (!$callbackUrl) {
            $baseUrl = config('mpesa.callback_url');

            if ($this->app->id === 'env') {
                $callbackUrl = $baseUrl ?? route('mpesa.stk.callback');
            } else {
                // If a base callback URL is configured (e.g. ngrok), use its host
                if ($baseUrl && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                    $parsed = parse_url($baseUrl);
                    $scheme = $parsed['scheme'] ?? 'https';
                    $host = $parsed['host'] ?? '';
                    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                    $path = '/api/mpesa/callback/' . $this->app->id;

                    $callbackUrl = "{$scheme}://{$host}{$port}{$path}";
                } else {
                    $callbackUrl = route('mpesa.callback', ['appId' => $this->app->id]);
                }
            }
        }

        $payload = [
            'BusinessShortCode' => $this->app->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => (int) $amount,
            'PartyA' => $this->formatPhone($phone),
            'PartyB' => $partyB,
            'PhoneNumber' => $this->formatPhone($phone),
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => 'Branch Payment',
        ];

        Log::info('STK Request', [
            'branch' => $this->branch->name,
            'daraja_app' => $this->app->id === 'env' ? 'default' : $this->app->name ?? $this->app->id,
            'till_number' => $partyB,
            'payload' => $payload
        ]);

        $response = Http::withToken($this->getAccessToken())->post($url, $payload);

        if ($response->failed()) {
            Log::error('STK Push failed', ['branch' => $this->branch->name, 'response' => $response->body()]);
            throw new \Exception('STK Push failed');
        }

        return $response->json();
    }

    protected function formatPhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        if (str_starts_with($phone, '7')) {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}
