<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortcode;
    protected $passkey;
    protected $env;

    public function __construct()
    {
        $this->consumerKey = env('MPESA_CONSUMER_KEY');
        $this->consumerSecret = env('MPESA_CONSUMER_SECRET');
        $this->shortcode = env('MPESA_SHORTCODE');
        $this->passkey = env('MPESA_PASSKEY');
        $this->env = env('MPESA_ENV', 'sandbox'); // sandbox or live
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function getShortcode()
    {
        return $this->shortcode;
    }

    public function getAccessToken()
    {
        return Cache::remember('mpesa_access_token', 50 * 60, function () {
            $url = $this->env === 'live'
                ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
                : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->get($url);

            if ($response->failed()) {
                Log::error('Failed to get M-Pesa access token', ['body' => $response->body()]);
                throw new \Exception('Failed to get M-Pesa access token');
            }

            return $response->json()['access_token'];
        });
    }

    protected function generatePassword($timestamp)
    {
        return base64_encode($this->shortcode . $this->passkey . $timestamp);
    }

    public function stkPush($phone, $amount, $accountReference = 'POS', $callbackUrl = null)
    {
        $timestamp = now()->format('YmdHis');
        $password = $this->generatePassword($timestamp);

        $url = $this->env === 'live'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $callbackUrl = $callbackUrl ?? config('mpesa.callback_url');

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $amount,
            'PartyA' => $this->formatPhone($phone),
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $this->formatPhone($phone),
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => 'POS Payment',
        ];

        Log::info('Sending STK Push request', $payload);
        Log::info('Password components', [
            'ShortCode' => $this->shortcode,
            'Passkey' => $this->passkey,
            'Timestamp' => $timestamp,
            'Password' => $password,
        ]);

        $response = Http::withToken($this->getAccessToken())->post($url, $payload);

        if ($response->failed()) {
            Log::error('STK Push failed', ['response' => $response->body()]);
            throw new \Exception('STK Push request failed');
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
