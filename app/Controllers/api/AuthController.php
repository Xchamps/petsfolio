<?php

namespace App\Controllers\api;

use App\Libraries\SmsEmailLibrary;
use App\Models\Provider;
use App\Models\User;
use App\Models\Wallet;
use CodeIgniter\Controller;
use App\Libraries\PushNotify;

class AuthController extends Controller
{
  protected $smsEmailLibrary;
  protected $user;
  protected $provider;
  protected $wallet;

  public function __construct()
  {
    $this->smsEmailLibrary = new SmsEmailLibrary();
    $this->user            = new User();
    $this->provider        = new Provider();
    $this->wallet          = new Wallet();
  }

  //user login
  public function index()
  {
    $rules = [
      'email'        => 'required|valid_email',
      'phone'        => 'required',
      'device_token' => 'required'
    ];

    if (!$this->validate($rules)) {
      return $this->response
        ->setStatusCode(403)
        ->setJSON(['status' => false, 'error' => $this->validator->getErrors()]);
    }

    $json = $this->request->getJSON();

    if ($json->phone == '9731747471' && $json->email == 'client@gmail.com') {
      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Default login successful',
        'data'    => ['otp' => 1234]
      ]);
    }

    // Generate a random OTP
    $otp = random_int(1000, 9999);

    // Check if the user already exists by phone or email
    $checkPhone  = $this->user->checkPhone($json->phone);
    $checkEmail  = $this->user->checkEmail($json->email);
    $checkExists = $this->user->checkExists($json->email, $json->phone);

    if ($checkExists) {
      // If the user already exists with the same email and phone, update the OTP
      $this->user->updateOtp([
        'device_token' => $json->device_token,
        'user_id'      => $checkExists->id,
        'otp'          => $otp,
        'ip_address'   => $this->request->getIPAddress(),
      ]);
      $message = 'OTP sent successfully';
      // if (!empty($json->email)) {
      //   $this->smsEmailLibrary->sendEmail($json->email, $otp);
      // }
      if (!empty($json->phone)) {
        $response = $this->smsEmailLibrary->sendSms($json->phone, $otp);

        if ($response != 200) {
          return $this->response->setJSON(['status' => false, 'message' => 'Failed to send OTP via SMS']);
        }
      }

      return $this->response->setJSON(['status' => true, 'message' => $message]);
    } elseif ($checkPhone && !$checkEmail) {
      // If phone exists but email is new
      return $this->response->setJSON(['status' => false, 'message' => 'Phone number already exists with a different email.']);
    } elseif (!$checkPhone && $checkEmail) {
      // If email exists but phone is new
      return $this->response->setJSON(['status' => false, 'message' => 'Email already exists with a different phone number.']);
    } elseif ($checkPhone && $checkEmail) {
      // Check if the phone and email belong to the same user
      if ($checkPhone->id === $checkEmail->id) {
        // Same user found, proceed with OTP update as for $checkExists case
        $this->user->updateOtp([
          'device_token' => $json->device_token,
          'user_id'      => $checkPhone->id,
          'otp'          => $otp,
          'ip_address'   => $this->request->getIPAddress(),
        ]);
        $message = 'OTP sent successfully';

        if (!empty($json->phone)) {
          $response = $this->smsEmailLibrary->sendSms($json->phone, $otp);
          if ($response != 200) {
            return $this->response->setJSON(['status' => false, 'message' => 'Failed to send OTP via SMS']);
          }
        }

        return $this->response->setJSON(['status' => true, 'message' => $message]);
      } else {
        // Email and phone belong to different users
        return $this->response->setJSON(['status' => false, 'message' => 'Email and phone number are associated with different accounts.']);
      }
    } else {
      // Handle user creation if not exists
      $this->user->create([
        'device_token' => $json->device_token,
        'email'        => $json->email,
        'phone'        => $json->phone,
        'otp'          => $otp,
        'status'       => 'active',
        'created_at'   => gmdate('Y-m-d H:i:s'),
        'ip_address'   => $this->request->getIPAddress()
      ]);
      // if (!empty($json->email)) {
      //   $this->smsEmailLibrary->sendEmail($json->email, $otp);
      // }
      if (!empty($json->phone)) {
        $response = $this->smsEmailLibrary->sendSms($json->phone, $otp);
        if ($response != 200) {
          return $this->response->setJSON(['status' => false, 'message' => 'Failed to send OTP via SMS']);
        }
      }

      return $this->response->setJSON(['status' => true, 'message' => 'OTP sent successfully']);
    }
  }


  //user OTP verification
  public function validateOtp()
  {
    $rules = [
      'otp'   => 'required|numeric',
      'email' => 'required|valid_email',
      'phone' => 'required|numeric',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(403)->setJSON([
        'error' => $this
          ->validator->getErrors(),
      ]);
    }
    $json  = $this->request->getJSON();
    $otp   = $json->otp;
    $email = $json->email;
    $phone = $json->phone;
    if ($phone == '9731747471' && $email == 'client@gmail.com' && $otp == 1234) {
      $data = $this->user->check('client@gmail.com', '9731747471', 1234);

      $token            = bin2hex(openssl_random_pseudo_bytes(16));
      $this->user->updateToken($email, $phone, $token);
      $data->token = $token;
      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Default OTP validation successful',
        'data'    => $data
      ]);
    }

    // Check if the user otp exists
    $data = $this->user->check($email, $phone, $otp);
    if ($data) {
      // remove otp
      $token       = bin2hex(openssl_random_pseudo_bytes(16));
      $data->token = $token;
      $this->user->removeOtp($email, $phone);
      $this->user->updateToken($email, $phone, $token);
      // Check if a wallet already exists for the provider
      $walletExists = $this->wallet->checkUserWallet($data->id);
      $PushNotify = new PushNotify();
      $title = 'Welcome to Petsfolio!';
      $message = 'Get started now to book top-notch pet services for your furry friend!';

      $PushNotify->notify($data->device_token, $title, $message);
      // If wallet does not exist, create one
      if (!$walletExists) {
        $this->wallet->createUserWWallet($data->id);
      }
      return $this->response->setJSON(['status' => true, 'message' => 'OTP is valid', 'data' => $data]);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'OTP is invalid']);
    }
  }

  //service provider login
  public function providerLogin()
  {
    $rules = [
      'email' => 'required|valid_email',
      'phone' => 'required|integer',
      // 'device_token' => 'required'
    ];

    if (!$this->validate($rules)) {
      return $this->response
        ->setStatusCode(403)
        ->setJSON(['status' => false, 'error' => $this->validator->getErrors()]);
    }

    $json  = $this->request->getJSON();
    $email = $json->email;
    $phone = $json->phone;

    if ($phone == '9731747471' && $email == 'mastertest@gmail.com') {
      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Default login successful',
        'data'    => ['mobile_otp' => 1234]
      ]);
    }

    $otp = random_int(1000, 9999);

    $checkPhone  = $this->provider->checkPhone($phone);
    $checkEmail  = $this->provider->checkEmail($email);
    $checkExists = $this->provider->checkExists($email, $phone);

    $data = [
      'email'        => $email,
      'phone'        => $phone,
      'mobile_otp'          => $otp,
      'device_token' => $json->device_token ?? '',
      'ip_address'   => $this->request->getIPAddress(),
    ];

    if ($checkExists) {
      // Update existing user's OTP
      $this->provider->updateOTP($data);
    } elseif ($checkPhone && !$checkEmail) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Phone number already exists with a different email.']);
    } elseif (!$checkPhone && $checkEmail) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Email already exists with a different phone number.']);
    } elseif ($checkPhone && $checkEmail) {
      if ($checkPhone->id === $checkEmail->id) {
        // Same user, update OTP
        $this->provider->updateOTP($data);
      } else {
        return $this->response->setJSON([
          'status' => false,
          'message' => 'Email and phone number are associated with different accounts.'
        ]);
      }
    } else {
      // Ensure no duplicate entries happen
      try {
        $data['status']       = 'active';
        $data['created_at']   = gmdate('Y-m-d H:i:s');
        $this->provider->createProvider($data);
      } catch (\Exception $e) {
        return $this->response->setJSON([
          'status'  => false,
          'message' => 'Failed to create provider: ' . $e->getMessage()
        ]);
      }
    }

    // Send OTP
    if (!empty($phone)) {
      $response = $this->smsEmailLibrary->sendSms($phone, $otp);
      if ($response != 200) {
        return $this->response->setJSON([
          'status' => false,
          'message' => 'Failed to send OTP via SMS'
        ]);
      }
    }

    return $this->response->setJSON([
      'status' => true,
      'message' => 'OTP sent successfully.'
    ]);
  }


  //service provider OTP verification
  public function providerValidateOtp()
  {
    $rules = [
      'email' => 'required|valid_email',
      'phone' => 'required',
      'otp'   => 'required|numeric',

    ];
    if (!$this->validate($rules)) {
      return $this->response
        ->setStatusCode(403)
        ->setJSON(['status' => false, 'error' => $this->validator->getErrors()]);
    }
    $json  = $this->request->getJSON();
    $email = $json->email;
    $phone = $json->phone;
    $otp   = $json->otp;

    if ($phone == '9731747471' && $email == 'mastertest@gmail.com' && $otp == 1234) {
      $data = $this->provider->check('mastertest@gmail.com', '9731747471', 1234);

      $defaultData = (object) [
        'token'            => bin2hex(openssl_random_pseudo_bytes(16)),
        'personal_details' => false,
        'service_details'  => false,
        'id_verifications' => false,
        'userId'           => $data->id
      ];
      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Default OTP validation successful',
        'data'    => $defaultData
      ]);
    }


    // Check if the OTP is valid
    $data = $this->provider->check($email, $phone, $otp);

    if ($data) {
      // Generate token
      $token       = bin2hex(openssl_random_pseudo_bytes(16));
      $data->token = $token;
      // Fetch personal, service, and verification details

      $personal_details = $this->provider->checkPersonalDetails($email, $phone);
      $service_details  = $this->provider->checkServiceDetails($data->id);
      $id_verifications = $this->provider->checkVerificationDetails($data->id);
      $data->personal_details = $this->hasValidPersonalDetails($personal_details);
      $data->service_details  = !empty($service_details) ? true : false;
      $data->id_verifications = ($id_verifications && $id_verifications->identity_proof_verified) ? true : false;
      $res                    = $this->provider->checkVerification($data->id);
      if (!empty($res)) {
        $this->provider->updateVerifications($data->id);
      } else {
        $this->provider->createVerifications($data->id);
      }
      // Remove OTP and update token in the database
      $this->provider->removeOtp($email, $phone);
      $this->provider->updateToken($email, $phone, $token);
      // Check if a wallet already exists for the provider
      $walletExists = $this->wallet->checkProviderWallet($data->id);

      // If wallet does not exist, create one
      if (!$walletExists) {
        $this->wallet->createProviderWallet($data->id);
      }
      return $this->response->setJSON(['status' => true, 'message' => 'OTP is valid', 'data' => $data]);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'OTP is invalid']);
    }
  }
  private function hasValidPersonalDetails($personalDetails)
  {
    if (empty($personalDetails)) {
      return false;
    }

    // Check each required field in $personalDetails
    return !empty($personalDetails->name) &&
      !empty($personalDetails->gender) &&
      !empty($personalDetails->dob) &&
      // !empty($personalDetails->profile) &&
      // !empty($personalDetails->permanent_address) &&
      !empty($personalDetails->service_address);
  }
}
