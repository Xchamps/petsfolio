<?php

namespace App\Controllers\api;

use CodeIgniter\Controller;
use App\Helpers\CommonHelper;
use App\Libraries\AadhaarService;
use App\Libraries\SmsEmailLibrary;
use App\Libraries\PushNotify;
use App\Models\Provider;
use App\Models\User;
use Exception;

class ServiceController extends Controller
{
  protected $user;
  protected $provider;
  protected $smsEmailLibrary;

  public function __construct()
  {
    $this->smsEmailLibrary = new SmsEmailLibrary();
    $this->user     = new User();
    $this->provider = new Provider();
  }

  public function index()
  {
    $services = $this->user->getServices();
    return $this->response
      ->setJSON(['status' => true, 'data' => $services]);
  }

  public function packages()
  {
    $packages = $this->user->getPackages();
    return $this->response
      ->setJSON(['status' => true, 'data' => $packages]);
  }

  public function Servicepackages($service_id)
  {
    $packages = $this->user->getServicepackages($service_id);

    $trimmingPackages = [];
    $otherPackages = [];

    // Separate "Trim" packages and other packages
    foreach ($packages as $package) {
      if (strpos($package->package_name, 'Trim') !== false) {
        $trimmingPackages[] = $package;
      } else {
        $otherPackages[] = $package;
      }
    }

    // Sort only "Trim" packages by price in ascending order
    usort($trimmingPackages, function ($a, $b) {
      return $a->price <=> $b->price;
    });

    // Merge sorted Trim packages with other packages
    $sortedPackages = array_merge($trimmingPackages, $otherPackages);

    // Process sorted packages
    foreach ($sortedPackages as $package) {
      if ($package->icon) {
        $package->icon = base_url() . 'public/uploads/icons/' . $package->icon;
      }

      $package->addons = $package->addons ?? ''; // Set to empty string if null
      $package->addons = explode(',', $package->addons);
    }


    // $responseData = array_merge($responseData, $otherPackages);

    return $this->response->setJSON(['status' => true, 'data' => $otherPackages, 'Trimming' => $trimmingPackages]);
  }

  public function serviceAddons($service_id)
  {
    $addons = $this->user->getServiceaddons($service_id);
    return $this->response->setJSON(['status' => true, 'data' => $addons]);
  }

  public function offers()
  {
    $offers = $this->user->getOffers();
    if ($offers) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $offers]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No offers found']);
    }
  }

  public function breeds()
  {
    $breeds = $this->user->getBreeds();
    if ($breeds) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $breeds]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No breeds found']);
    }
  }

  public function support()
  {
    $rules = [
      'user_id' => 'required|numeric',
      'message' => 'required',
      'type'    => 'required|in_list[user,provider]',

    ];
    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json = $this->request->getJSON();
    log_message('info', 'json data', (array) $json);
    if ($json->type == 'user') {
      $user = $this->user->getUser($json->user_id);
      $this->sendMail($user->email, $user->phone, $json->type, $json->message);
    } else {
      $provider = $this->provider->getProvider($json->user_id);
      $this->sendMail($provider->email, $provider->phone, $json->type, $json->message);
    }
    $data = [
      'user_id'   => $json->user_id,
      'message'   => $json->message,
      'user_type' => $json->type,
      'status'    => 'new',
    ];

    $result = $this->user->createQuery($data);
    if ($result) {
      return $this->response
        ->setStatusCode(200)
        ->setJSON(['status' => true, 'message' => 'success']);
    } else {
      return $this->response
        ->setStatusCode(404)
        ->setJSON(['status' => false, 'message' => 'no submitted']);
    }
  }

  private function sendMail($email, $phone, $type, $message)
  {
    if (! $email) {
      log_message('error', 'Email address is missing in sendMail method.');
      return false;
    }

    // Set type label
    $typeLabel = ($type === 'user') ? 'Client' : 'Service Provider';

    // Define subject and body
    $subject = 'Support Request';
    $body    = <<<EOT
<html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                color: #333333;
                background-color: #f9f9f9;
                margin: 0;
                padding: 20px;
            }
            .email-container {
                background-color: #ffffff;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                margin: 0 auto;
            }
            .header {
                text-align: center;
                font-size: 22px;
                color: #4CAF50;
                margin-bottom: 20px;
            }
            .content {
                font-size: 16px;
                line-height: 1.5;
            }
            .details {
                background-color: #f1f1f1;
                border-left: 4px solid #4CAF50;
                padding: 10px;
                margin: 15px 0;
                font-size: 14px;
            }
            .footer {
                text-align: center;
                font-size: 14px;
                color: #777777;
                margin-top: 20px;
            }
             .footer img {
                max-width: 150px;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                New Support Request
            </div>
            <div class="content">
                <p>Hello Admin,</p>
                <p>A support message has been submitted. Below are the details:</p>
                <div class="details">
                    <p><strong>Sender's Email ID:</strong> $email</p>
                    <p><strong>Sender's Mobile Number:</strong> $phone</p>
                    <p><strong>Message:</strong> $message</p>
                </div>
                <p>Please address this query promptly.</p>
            </div>
            <div class="footer">
                <img src="https://cmschamps.com/psf-admin/public/assets/images/icons/Petsfolio.png" alt="Petsfolio Logo">
                <p>Best regards,<br><strong>FROM: $typeLabel</strong></p>
            </div>
        </div>
    </body>
</html>
EOT;

    // Send the email
    try {
      $this->smsEmailLibrary->sendMail($email, $subject, $body);
      return true;
    } catch (Exception $e) {
      log_message('error', 'Failed to send email: ' . $e->getMessage());
      return false;
    }
  }

  public function aadharVerify()
  {
    $rules = [
      'provider_id'    => 'required',
      'aadhaar_number' => 'required',
      'aadhaar_name'   => 'required',
    ];

    // Validate input
    if (!$this->validate($rules)) {
      log_message('debug', 'Validation failed: ' . json_encode($this->validator->getErrors()));
      return $this->response
        ->setContentType('application/json')
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    // Parse request JSON
    $json = $this->request->getJSON();
    log_message('debug', 'Request JSON: ' . json_encode($json));

    // Check required fields
    if (isset($json->aadhaar_number) && isset($json->aadhaar_name)) {
      // Check if Aadhaar number already exists
      $exists = $this->provider->checkAadharExists($json->aadhaar_number);
      if ($exists) {
        log_message('debug', 'Aadhaar number already exists.');
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status'  => false,
            'message' => 'Aadhaar number already exists',
            'data'    => $json->aadhaar_number,
          ]);
      }

      // Call Aadhaar Service for OTP
      $aadhaarLib = new AadhaarService();
      $response   = $aadhaarLib->requestOtp($json->aadhaar_number);
      log_message('debug', 'Aadhaar Service Response: ' . json_encode($response));

      // Extract response details
      $statusCode = $response['code'] ?? 500; // Default to 500 if code is missing
      $message    = $response['data']['message'] ?? 'Unexpected error';
      $referenceId = isset($response['data']['reference_id']) ? (string) $response['data']['reference_id'] : '0';

      // Handle specific responses
      if ($statusCode === 200 && $message === 'OTP sent successfully') {
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status' => true,
            'data'   => [
              'reference_id' => $referenceId,
              'message'      => 'OTP sent successfully',
            ],
          ]);
      } elseif ($statusCode === 200 && $message === 'Invalid Aadhaar Card') {
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status'  => false,
            'message' => 'Invalid Aadhaar details. Please check and try again.',
          ]);
      } elseif ($statusCode === 200 && $message === 'OTP generated for this aadhaar, please try after 45 seconds') {
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status'  => false,
            'message' => 'An OTP has already been generated for this Aadhaar number. Please wait before trying again.',
          ]);
      } elseif ($statusCode === 422) {
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status'  => false,
            'message' => 'Invalid Aadhaar number pattern. Please check and enter a valid Aadhaar number.',
          ]);
      } else {
        return $this->response
          ->setContentType('application/json')
          ->setJSON([
            'status'  => false,
            'message' => 'An unexpected error occurred. Please try again later.',
          ]);
      }
    } else {
      log_message('debug', 'Invalid Aadhaar details provided.');
      return $this->response
        ->setContentType('application/json')
        ->setJSON(['status' => false, 'message' => 'Invalid Aadhaar details provided']);
    }
  }


  public function aadharOTPVerify()
  {
    $rules = [
      'provider_id'    => 'required',
      'reference_id'   => 'required',
      'otp'            => 'required',
      'aadhaar_number' => 'required',
      'aadhaar_name'   => 'required',
    ];

    if (! $this->validate($rules)) {
      return $this->response->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors(),
      ]);
    }

    $json = $this->request->getJSON();

    // Validate basic structure of input payload
    if (! isset($json->reference_id, $json->otp, $json->aadhaar_number, $json->aadhaar_name)) {
      log_message('error', 'Invalid request payload: ' . json_encode($json));
      return $this->response->setJSON([
        'status'  => false,
        'message' => 'Invalid request parameters.',
      ]);
    }

    if ($this->provider->checkAadharExists($json->aadhaar_number)) {
      return $this->response->setJSON([
        'status'  => false,
        'message' => 'Aadhaar number already exists',
        'data'    => $json->aadhaar_number,
      ]);
    }

    $aadhaarLib = new AadhaarService();
    $response   = $aadhaarLib->verifyOtp($json->reference_id, $json->otp);

    // Ensure response is valid and properly structured
    if (! $response || ! isset($response->code, $response->data->message)) {
      log_message('error', 'Invalid Aadhaar response: ' . json_encode($response));
      return $this->response->setJSON([
        'status'  => false,
        'message' => 'Unexpected response from the Aadhaar verification service.',
      ]);
    }

    $responseMessages = [
      'Aadhaar Card Exists'                                => 'Aadhaar verified successfully.',
      'Invalid OTP'                                        => 'The OTP entered is invalid.',
      'Invalid Reference Id'                               => 'The provided reference ID is invalid. Please check and try again.',
      'OTP expired'                                        => 'The OTP has expired. Please request a new one.',
      'Request under process, please try after 30 seconds' => 'Aadhaar verified successfully.',
      'OTP missing in request'                             => 'OTP is missing from the request.',
    ];

    if ($response->data && $response->code == 200) {
      log_message('info', 'Aadhaar response: ' . json_encode($response));

      if (in_array($response->data->message, ['Aadhaar Card Exists', 'Request under process, please try after 30 seconds'])) {
        if (! $this->verifyName($json->aadhaar_name, $response->data->name)) {
          return $this->response->setJSON([
            'status'  => false,
            'message' => 'Invalid Aadhaar name',
          ]);
        }

        $this->provider->updateProvider((array) $json, $response->data->full_address);
        $this->provider->updateOrCreateIdentityVerify($json->provider_id);
        $this->provider->addAadhar($json->provider_id, json_encode($response->data));

        return $this->response->setJSON([
          'status'  => true,
          'message' => $responseMessages[$response->data->message],
        ]);
      } elseif (isset($responseMessages[$response->data->message])) {
        return $this->response->setJSON([
          'status'  => false,
          'message' => $responseMessages[$response->data->message],
        ]);
      }
      $data = $this->provider->checkAadharExists($json->aadhaar_number);
      $PushNotify = new PushNotify();
      $title = 'Welcome onboard!';
      $message = 'Start offering your pet care services and connect with pet owners today';

      $PushNotify->notify($data->device_token, $title, $message);
    } elseif ($response->code == 422) {
      return $this->response->setJSON([
        'status'  => false,
        'message' => $responseMessages[$response->message] ?? 'A validation error occurred. Please check your input.',
      ]);
    }

    // Log unexpected responses
    log_message('error', 'Unexpected Aadhaar response: ' . json_encode($response));
    return $this->response->setJSON([
      'status'  => false,
      'message' => 'Unexpected response from the Aadhaar verification service.',
    ]);
  }

  private function verifyName($enteredName, $aadharName)
  {
    $normalizedEnteredName = CommonHelper::normalizeName($enteredName);
    $normalizedAadharName  = CommonHelper::normalizeName($aadharName);
    $distance              = levenshtein($normalizedEnteredName, $normalizedAadharName);
    if ($distance === 0) {
      if ($normalizedEnteredName === $normalizedAadharName) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  public function BankVerify()
  {
    $rules = [
      'provider_id'    => 'required',
      'account_number' => 'required',
      'ifsc'           => 'required|regex_match[/^[A-Za-z]{4}0[A-Z0-9a-z]{6}$/]',
    ];

    if (! $this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json           = $this->request->getJSON();
    $providerId     = $json->provider_id;
    $provider       = $this->provider->getProvider($providerId);
    $account_number = $json->account_number;
    $ifsc           = $json->ifsc;
    $bank           = new AadhaarService();
    $response       = $bank->BankVerify($account_number, $ifsc);
    if ($response->code == 200) {
      $responseMessage = $response->data->message;
      $nameAtBank      = trim($response->data->name_at_bank);
      $nameAtBank      = preg_replace('/^(Miss\.|Mr\.|Mrs\.|Master)\s+/i', '', $nameAtBank);

      if ($responseMessage == "Bank Account details verified successfully.") {
        $verify = $this->verifyName($provider->aadhar_name, $nameAtBank);
        if (! $verify) {
          return $this->response
            ->setJSON([
              'status'  => false,
              'message' => 'Invalid name at Bank',
            ]);
        }
        return $this->response
          ->setJSON(['status' => true, 'message' => 'Bank verified successfully.']);
      } else {
        return $this->response
          ->setJSON(['status' => false, 'message' => $responseMessage]);
      }
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => $response->message]);
    }
  }
  public function events()
  {
    try {
      $url = 'https://www.petsfolio.com/in/test.php';
      $ch  = curl_init($url);

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
      }

      curl_close($ch);

      $data = json_decode($response);
      if ($data === null) {
        throw new Exception("Failed to decode JSON response.");
      }
      foreach ($data as $post) {

        // Remove all HTML tags and unwanted characters
        $post->post_content = strip_tags($post->post_content);

        // Update the post URL
        $post->post_url = 'https://www.petsfolio.com/in/events/' . $post->post_name;
        $content        = preg_replace(['/(\xC2\xA0)/', '/&amp;/'], [' ', ' & '], $post->post_content);

        // Extract Address
        preg_match('/Address\s*:-\s*(.*?)\r\n/', $content, $addressMatch);
        $post->address = isset($addressMatch[1]) ? trim($addressMatch[1]) : '';

        if (preg_match('/Date(?:\s*&\s*time)?\s*:-\s*(.*?)(?:\r\n|\n|$)/i', $content, $dateMatch)) {
          $post->dateTime = isset($dateMatch[1]) ? trim($dateMatch[1]) : '';
        } else {
          $post->dateTime = '';
        }

        preg_match('/website\s*:-\s*(https?:\/\/[^\s\r\n]+)/i', $content, $websiteMatch);
        $post->website = isset($websiteMatch[1]) ? trim($websiteMatch[1]) : '';
      }

      return $this->response->setJSON($data);
    } catch (Exception $e) {
      return $this->response->setStatusCode(500)->setJSON('Error fetching events: ' . $e->getMessage());
    }
  }
}
