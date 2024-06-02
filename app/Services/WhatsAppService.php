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

                Cache::put('whatsapp_access_token', $newAccessToken, now()->addSeconds($expiresIn));

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

        $appAccessToken = $this->getAppAccessToken();
        if ($appAccessToken) {
            Cache::put('whatsapp_access_token', $appAccessToken, now()->addHours(23));
            return $appAccessToken;
        }

        return $this->refreshAccessToken();
    }

    private function checkAndRefreshToken()
    {
        $accessToken = $this->getCachedAccessToken();

        try {
            $response = $this->client->get('https://graph.facebook.com/v19.0/debug_token', [
                'query' => [
                    'input_token' => $accessToken,
                    'access_token' => env('YOUR_APP_ACCESS_TOKEN')
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['data']['is_valid']) && $data['data']['is_valid']) {
                return $accessToken;
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::info('Access token is not valid, obtaining a new token.');
        }

        return $this->refreshAccessToken();
    }

    public function sendTemplateMessage($to, $templateName, $templateLanguageCode, $templateParameters, $buttonParameters)
    {
        $accessToken = $this->checkAndRefreshToken();
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
        $url = "https://graph.facebook.com/v12.0/{$phoneNumberId}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $templateLanguageCode
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $templateParameters
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => $buttonParameters
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            return $responseBody;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error("Error sending template message: " . $e->getMessage());
            if ($e->hasResponse()) {
                Log::error("Response: " . $e->getResponse()->getBody()->getContents());
            }
            return null;
        }
    }

    public function sendOtpMessage($to, $otp)
    {
        $templateName = 'otp_template'; // Custom template name
        $templateLanguageCode = 'en_US'; // Language code
        $templateParameters = [
            ['type' => 'text', 'text' => $otp]
        ];
        $buttonParameters = [
            ['type' => 'text', 'text' => $otp] // Use OTP for button text parameter
        ];

        return $this->sendTemplateMessage($to, $templateName, $templateLanguageCode, $templateParameters, $buttonParameters);
    }
}
