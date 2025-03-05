<?php

namespace App\Libraries;

use GuzzleHttp\Client;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;

require_once APPPATH . 'ThirdParty/firebasephp-jwt/vendor/autoload.php';
class PushNotify
{
    protected $client;
    protected $accessToken;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://fcm.googleapis.com/v1/projects/petsfolio-2204f/messages:send',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $jsonPath = ROOTPATH . 'petsfolio-2204f-65162b859efb.json';

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $jsonPath);

        $credentials->fetchAuthToken();
        return $credentials->getLastReceivedToken()['access_token'];
    }
    public function notify($token, $title, $message)
    {
        $body = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $message,
                ],
                "android" => [
                    "priority" => "HIGH"
                ],
                "apns" => [
                    "headers" => [
                        "apns-priority" => "10"
                    ],
                    "payload" => [
                        "aps" => [
                            "sound" => "default"
                        ]
                    ]
                ]
            ]
        ];

        // Convert the payload to JSON
        $bodyJson = json_encode($body);
        return $this->sendNotification($bodyJson);
    }
    public function sendNotification($body)
    {

        try {
            $response = $this->client->post('', [
                'json' => json_decode($body, true),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
            ]);
            $statusCode = $response->getStatusCode();
            $responseData = $response->getBody()->getContents();
            return [
                'status_code' => $statusCode,
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            return [
                'status_code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }
}
