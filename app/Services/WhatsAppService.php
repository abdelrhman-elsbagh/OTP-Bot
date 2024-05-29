<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    private function getAppAccessToken()
    {
        // Use the access token from environment variables initially
        return env('YOUR_APP_ACCESS_TOKEN');
    }

    private function refreshAccessToken()
    {
        $url = 'https://graph.facebook.com/v19.0/oauth/access_token';
        $refreshToken = env('WHATSAPP_REFRESH_TOKEN');

        try {
            $response = $this->client->post($url, [
                'form_params' => [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => env('WHATSAPP_CLIENT_ID'),
                    'client_secret' => env('WHATSAPP_CLIENT_SECRET'),
                    'fb_exchange_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            Log::info('Token refresh response:', $data);

            if (isset($data['access_token'])) {
                $newAccessToken = $data['access_token'];
                $expiresIn = $data['expires_in']; // in seconds

                // Cache the new access token
                Cache::put('whatsapp_access_token', $newAccessToken, now()->addSeconds($expiresIn));
                Log::info('Obtained and cached new access token', ['token' => $newAccessToken]);

                return $newAccessToken;
            } else {
                Log::error('Failed to refresh access token', ['response' => $data]);
                return null;
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error("Error refreshing access token: " . $e->getMessage());
            return null;
        }
    }

    private function getCachedAccessToken()
    {
        if (Cache::has('whatsapp_access_token')) {
            $cachedToken = Cache::get('whatsapp_access_token');
            Log::info('Using cached access token', ['token' => $cachedToken]);
            return $cachedToken;
        }

        // Try to use the initial app access token
        $appAccessToken = $this->getAppAccessToken();
        if ($appAccessToken) {
            // Cache the initial app access token for a short duration (23 hours for this example)
            Cache::put('whatsapp_access_token', $appAccessToken, now()->addHours(23));
            Log::info('Using initial app access token', ['token' => $appAccessToken]);
            return $appAccessToken;
        }

        // If no cached token and no initial app token, try to refresh using the refresh token
        return $this->refreshAccessToken();
    }

    public function sendMessage($to, $message)
    {
        $accessToken = $this->getCachedAccessToken();
        if (!$accessToken) {
            Log::error("Failed to get access token");
            return null;
        }

        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        if (!$phoneNumberId) {
            Log::error("Phone number ID is not set.");
            return null;
        }

        $to = preg_replace('/[^0-9]/', '', $to);
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        Log::info('Sending message', [
            'to' => $to,
            'message' => $message,
            'url' => $url,
            'accessToken' => $accessToken,
            'phoneNumberId' => $phoneNumberId,
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ],
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            Log::info('Message sent successfully', $responseBody);

            return $responseBody;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error("Error sending message: " . $e->getMessage());
            if ($e->hasResponse()) {
                Log::error("Response: " . $e->getResponse()->getBody()->getContents());
            }
            return null;
        }
    }
}
