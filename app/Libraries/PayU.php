<?php

namespace App\Libraries;

use Exception;

require_once APPPATH . "ThirdParty/phonepe/vendor/autoload.php";

class PayU
{
    protected $client;
    private $baseUrl;
    private $payoutMerchantId;
    private $clientId;
    private $clientSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->client           = \Config\Services::curlrequest();
        $this->baseUrl          = getenv('PAYU_URL');
        $this->payoutMerchantId = getenv('PAYU_MERCHANT_ID');
        $this->clientId         = getenv('PAYU_CLIENT_ID');
        $this->clientSecret     = getenv('PAYU_CLIENT_SECRET');
        $this->accessToken      = $this->getAccessToken($this->clientId, $this->clientSecret);
    }

    /**
     * authentication
     */

    public function getAccessToken($clientId, $clientSecret)
    {
        try {
            $response = $this->client->request('POST', 'https://accounts.payu.in/oauth/token', [
                'form_params' => [
                    'grant_type'    => 'password',
                    'scope'         => 'create_payout_transactions',
                    'client_id'     => $clientId,
                    // 'client_secret' => $clientSecret,
                    'username'      => 'accounts@petsfolio.com',
                    'password'      => 'Petsfolio@123'
                ],
                'headers'     => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
            ]);

            $body = $response->getBody();
            log_message('info', 'auth response: ' . $body);
            $result = json_decode($body, true);

            if (isset($result['access_token'])) {
                return $result['access_token'];
            } else {
                throw new Exception('Failed to get access token: ' . $result);
            }
        } catch (Exception $e) {
            log_message('error', 'Authentication failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create Payment
     */

    public function initiateTransfer($data)
    {

        $accessToken = $this->getAccessToken($this->clientId, $this->clientSecret);
        log_message('info', 'initiateTransfer accessToken response: ' . $accessToken);

        try {
        $payload = [
            [
                "disableApprovalFlow"      => false,
                "beneficiaryAccountNumber" => $data['beneficiaryAccountNumber'] ?? '',
                "beneficiaryIfscCode"      => $data['beneficiaryIfscCode'] ?? '',
                "beneficiaryName"          => $data['beneficiaryName'] ?? '',
                "beneficiaryMobile"        => $data['beneficiaryMobile'] ?? '',
                "purpose"                  => "Withdrawal Payment",
                "amount"                   => $data['amount'] ?? '',
                "batchId"                  => $data['batchId'] ?? '1',
                "merchantRefId"            => $data['merchantRefId'] ?? '878789',
                "paymentType"              => "IMPS",
                "retry"                    => false
            ]
        ];

        $response = $this->client->request('POST', 'https://payout.payumoney.com/payout/payment', [
            'json'    => $payload,
            'headers' => [
                'authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
                'pid'           => $this->payoutMerchantId,
            ],
        ]);
        log_message('info', 'initiateTransfer response: ' . $response);

        return $response->getBody();
        } catch (Exception $e) {
            log_message('error', 'Authentication failed: ' . $e->getMessage());

            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Check Transfer Status
     */

    public function checkTransferStatus($data)
    {
        $accessToken = $this->getAccessToken($this->clientId, $this->clientSecret);

        try {
            $response = $this->client->request('POST', 'https://payout.payumoney.com/payout/payment/listTransactions', [
                'form_params' => [
                    'transferStatus' => $data['transferStatus'] ?? '',
                    'from'           => $data['from'] ?? '',
                    'to'             => $data['to'] ?? '',
                    'page'           => $data['page'] ?? '',
                    'pageSize'       => $data['pageSize'] ?? '',
                    'merchantRefId'  => $data['merchantRefId'] ?? '',
                    'batchId'        => $data['batchId'] ?? ''
                ],
                'headers'     => [
                    'authorization'    => 'Bearer ' . $accessToken,
                    'Content-Type'     => 'application/x-www-form-urlencoded',
                    'payoutMerchantId' => $this->payoutMerchantId,
                ],
            ]);

            return $response->getBody();
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}
