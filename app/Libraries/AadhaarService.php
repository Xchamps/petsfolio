<?php

namespace App\Libraries;


class AadhaarService
{
    protected $client;
    private $baseUrl;

    private $apiKey;
    private $apiSecret;

    public function __construct()
    {
        $this->client    = \Config\Services::curlrequest();
        $this->baseUrl   = getenv('productionUrl');
        $this->apiKey    = getenv('productionApiKey');
        $this->apiSecret = getenv('productionSecret');
    }

    /**
     * Authenticate and get the JWT token.
     */
    public function authenticate()
    {
        $url = $this->baseUrl . "/authenticate";

        $headers = [
            "accept"        => "application/json",
            "x-api-key"     => $this->apiKey,
            "x-api-secret"  => $this->apiSecret,
            "x-api-version" => "1.0"
        ];

        $response = $this->client->request('POST', $url, [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() !== 200) {
            return $response->getBody();
        }
        $responseBody = json_decode($response->getBody(), true);

        if (isset($responseBody['access_token'])) {
            return $responseBody['access_token'];
        }
    }

    /**
     * Refresh the JWT token using the refresh token.
     */
    public function authorize($request_token)
    {
        $url = $this->baseUrl . "/authorize?request_token=" . $request_token;

        $headers  = [
            "accept: application/json",
            "authorization: $request_token",
            "x-api-key" => $this->apiKey,
            "x-api-version: 1.0"
        ];
        $response = $this->client->request('POST', $url, [
            'headers' => $headers,
            'timeout' => 30,
        ]);
        if ($response->getStatusCode() !== 200) {
            return "Error: " . $response->getReason();
        }
        return $response->getBody();
    }

    /**
     * Send OTP request.
     */
    public function requestOtp($aadhaar_number)
    {
        $access_token = $this->authenticate();
        $data         = [
            '@entity'        => 'in.co.sandbox.kyc.aadhaar.okyc.otp.request',
            'aadhaar_number' => $aadhaar_number,
            'consent'        => 'y',
            'reason'         => 'for kyc'
        ];

        try {
            $response = $this->client->post($this->baseUrl . '/kyc/aadhaar/okyc/otp', [
                'json'    => $data,
                'headers' => [
                    'accept'        => 'application/json',
                    'authorization' => $access_token,
                    'content-type'  => 'application/json',
                    'x-api-key'     => $this->apiKey,
                    'x-api-version' => '2.0'
                ]
            ]);

            $body = $response->getBody();

            $body = json_decode($response->getBody(), true);
            return $body;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp($reference_id, $otp)
    {
        $access_token = $this->authenticate();

        $data = [
            '@entity'      => 'in.co.sandbox.kyc.aadhaar.okyc.request',
            'reference_id' => $reference_id,
            'otp'          => $otp
        ];
        try {
            $response = $this->client->post('https://api.sandbox.co.in/kyc/aadhaar/okyc/otp/verify', [
                'json'    => $data,
                'headers' => [
                    'accept'        => 'application/json',
                    'authorization' => $access_token,
                    'content-type'  => 'application/json',
                    'x-api-key'     => $this->apiKey,
                    'x-api-version' => '2.0'
                ]
            ]);
            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                return json_decode($body);
            } else {
                $body = $response->getBody();
                log_message('error', 'API Error: Status Code: ' . $response->getStatusCode() . ' Body: ' . $body);
                return json_decode($body);
            }
        } catch (\Exception $e) {
            log_message('error', 'Exception: ' . $e->getMessage());
            return json_decode($e->getMessage());
        }
    }


    public function BankVerify($acno, $ifsc, $name = null, $mobile = null)
    {
        $access_token = $this->authenticate();

        try {
            $response = $this->client->request('GET', 'https://api.sandbox.co.in/bank/' . $ifsc . '/accounts/' . $acno . '/penniless-verify?name=' . $name . '&mobile=' . $mobile, [
                'headers' => [
                    'accept'         => 'application/json',
                    'authorization'  => $access_token,
                    'content-type'   => 'application/json',
                    'x-api-key'      => $this->apiKey,
                    'x-accept-cache' => 'true',
                    'x-api-version'  => '1.0'
                ]
            ]);
            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();
                return json_decode($body);
            } else {
                $body = $response->getBody();
                log_message('error', 'API Error: Status Code: ' . $response->getStatusCode() . ' Body: ' . $body);
                return json_decode($body);
            }
        } catch (\Exception $e) {
            log_message('error', 'Exception: ' . $e->getMessage());
            return json_decode($e->getMessage());
        }
    }


    public function fetchBankAccountVerification()
    {

        $url = 'https://live.zoop.one/api/v1/in/financial/bav/lite';

        $headers = [
            'app-id' => '{{app-id}}',
            'api-key' => '{{api-key}}',
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'mode' => 'sync',
            'data' => [
                'account_number' => '397701503XXX',
                'ifsc' => 'ICIC00XXX45',
                'consent' => 'Y',
                'consent_text' => 'I hearby declare my consent agreement for fetching my information via ZOOP API'
            ],
            'task_id' => 'f26eb21e-4c35-4491-b2d5-41fa0e545a34'
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'json' => $payload
            ]);

            $body = $response->getBody();
            return $this->respond(json_decode($body, true), $response->getStatusCode());
        } catch (\Exception $e) {
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }

    public function requestAadhaarOTP($aadhaar_number, $name)
    {

        $url = 'https://live.zoop.one/in/identity/okyc/otp/request';

        $headers = [
            'app-id' => '676d3f8f4a47ae00283616a9',
            'api-key' => 'TKKKA42-B6140ZY-HDSKG6Y-F6Q1CYK',
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'mode' => 'sync',
            'data' => [
                'customer_aadhaar_number' => $aadhaar_number,
                'name_to_match' => $name,
                'consent' => 'Y',
                'consent_text' => 'I hearby declare my consent agreement for fetching my information via ZOOP API'
            ],
            'task_id' => '08b01aa8-9487-4e6d-a0f0-c796839d6b77'
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'json' => $payload
            ]);

            $body = $response->getBody();
            return json_decode($body, true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    public function verifyAadhaarOTP()
    {

        $url = 'https://live.zoop.one/in/identity/okyc/otp/verify';

        $headers = [
            'app-id' => '{{app-id}}',
            'api-key' => '{{api-key}}',
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'mode' => 'sync',
            'data' => [
                'request_id' => '0c869dd5-619c-46b7-b1e6-3419ca2cdaa7',
                'otp' => '480XXX',
                'consent' => 'Y',
                'consent_text' => 'I hearby declare my consent agreement for fetching my information via ZOOP API'
            ],
            'task_id' => '08b01aa8-9487-4e6d-a0f0-c796839d6b77'
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'json' => $payload
            ]);

            $body = $response->getBody();
            return $this->respond(json_decode($body, true), $response->getStatusCode());
        } catch (\Exception $e) {
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }
}
