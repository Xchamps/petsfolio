<?php

namespace App\Controllers\api;

use App\Models\Wallet;
use CodeIgniter\Controller;

class WalletController extends Controller
{
  protected $wallet;

  public function __construct()
  {
    $this->wallet = new Wallet();
  }
  public function index($user_id)
  {
    $wallet = $this->wallet->getUserWalletDetails($user_id);
    return $this->response->setStatusCode(200)->setJSON($wallet);
  }
  public function addMoney()
  {
    $rules = [
      'amount' => 'required|numeric',
      'user_id' => 'required|numeric',
      // 'wallet_id' => 'required|numeric'
    ];
    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(400)->setJSON(['errors' => $this->validator->getErrors()]);
    }

    $data = $this->request->getJSON();
    $wallet = $this->wallet->addMoney($data);
    if ($wallet) {
      return $this->response->setStatusCode(200)->setJSON(['status' => true, 'message' => 'Money
      added successfully']);
    } else {
      return $this->response->setStatusCode(400)->setJSON(['status' => false, 'message' => 'Failed
      to add money']);
    }
  }
  public function ProviderWallet($provider_id)
  {
    $wallet = $this->wallet->getProviderWalletDetails($provider_id);
    return $this->response->setStatusCode(200)->setJSON($wallet);
  }
}
