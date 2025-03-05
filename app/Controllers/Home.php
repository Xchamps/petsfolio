<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        header('Location: https://www.petsfolio.com/in/');
        exit; 
    }
    


    public function withdrawAmount()
    {
        $rules = [
            'amount' => 'required|numeric|greater_than_equal_to[100]|less_than_equal_to[1000000]',
            'bank_id' => 'required',
            'provider_id' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
        }

        $amount = $this->request->getJsonVar('amount');
        $bankId = $this->request->getJsonVar('bank_id');
        $providerId = $this->request->getJsonVar('provider_id');
        $total = $this->wallet->getTotalWithdrawalAmount($providerId);

        if (!empty($total)) {
            $spDetails = $this->provider->getProvider($providerId);
            $userBank = $this->provider->getUserBankAccount($providerId, $bankId);

            if (!empty($spDetails) && !empty($userBank) && floatval($amount) > 0) {
                if (floatval($amount) > floatval($total->amount)) {
                    return $this->response->setJSON(['status' => false, 'message' => 'Invalid amount']);
                }

                $curl = \Config\Services::curlrequest();

                $phonePeMerchantId = getenv('PHONEPEMERCHANTID');
                $phonePeSaltKey = getenv('PHONEPESALTKEY');
                $phonePeSaltIndex = getenv('PHONEPESALTINDEX');
                $fundAccountId = $userBank->account_number ?? $userBank->upi_id;

                $phonePeRequest = [
                    "merchantId" => $phonePeMerchantId,
                    "amount" => intval($amount * 100),
                    "currency" => "INR",
                    "recipient" => [
                        "account" => $fundAccountId,
                    ],
                    "transactionId" => uniqid(),
                ];

                // Generate signature
                $payload = json_encode($phonePeRequest);
                $xVerify = $this->generateXVerify($phonePeRequest, $phonePeSaltKey, $phonePeSaltIndex);

                try {
                    $response = $curl->request('POST', 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/payouts', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'X-VERIFY' => $xVerify,
                        ],
                        'body' => $payload
                    ]);

                    if ($response->getStatusCode() === 200) {
                        return $this->response->setJSON(['status' => true, 'message' => 'Withdrawal successful.']);
                    } else {
                        $error = 'Transaction failed: ' . $response->getBody();
                        return $this->response->setJSON(['status' => false, 'message' => $error]);
                    }
                } catch (\Exception $e) {
                    return $this->response->setJSON(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
            } else {
                return $this->response->setJSON(['status' => false, 'message' => 'Invalid provider or bank details.']);
            }
        } else {
            return $this->response->setJSON(['status' => false, 'message' => 'Insufficient balance or invalid withdrawal amount.']);
        }
    }

    private function generateXVerify($phonePeRequest, $saltKey, $saltIndex)
    {

        $payload = json_encode($phonePeRequest);

        $base64Payload = base64_encode($payload);

        $uri = '/pg/v1/payouts/initiate';
        $concatString = $base64Payload . $uri . $saltKey;

        $hash = hash('sha256', $concatString);

        $xVerify = $hash . '###' . $saltIndex;

        return $xVerify;
    }
    public function phpInfo()
    {
        echo phpinfo();
        die;
        
    }
}
