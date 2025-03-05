<?php

namespace App\Libraries;

use Twilio\Rest\Client;

class SmsEmailLibrary
{
  protected $email;
  protected $bulkSmsUrl;
  protected $bulkSmsUser;
  protected $bulkSmsPassword;
  protected $from;
  protected $domain;

  public function __construct()
  {
    $this->email = \Config\Services::email();
    $this->bulkSmsUrl = getenv('bulkSmsUrl');
    $this->bulkSmsPassword = getenv('bulkSmsPassword');
    $this->bulkSmsUser = getenv('bulkSmsUser');
    $this->from = getenv('emailFrom');
    $this->domain = getenv('emailDomain');
  }
  public function sendEmail($email, $subject, $body)
  {
    $this->email->setTo($email);
    $this->email->setFrom($this->from, $this->domain);
    $this->email->setSubject($subject);
    $this->email->setMessage($body);
    $this->email->setMailType('html');
    $this->email->send();
    return true;
  }
  public function sendMail($email, $subject, $body)
  {
    $this->email->setTo('shivamway@gmail.com');
    $this->email->setCC('info.petsfolio@gmail.com');

    $this->email->setFrom($this->from, $this->domain);
    $this->email->setSubject($subject);
    $this->email->setMessage($body);
    $this->email->setMailType('html');

    $this->email->send();
    return true;
  }
  public function sendSms($phone, $body)
  {
    $curl = \Config\Services::curlrequest();

    try {
      $message = "%27Dear%20Customer,Your%20OTP%20number%20is%20$body,Regards%20PETSFOLIO%27";
      $url = $this->buildSmsUrl($phone, $message);
      $response = $curl->request('GET', $url);
      $statusCode = $response->getStatusCode();
      if ($statusCode == 200) {
        return 200;
      } else {
        return $statusCode;
      }
    } catch (\Exception $e) {
      return 500;
    }
  }
  private function buildSmsUrl($phone, $message)
  {
    return $this->bulkSmsUrl .
      'user=' . $this->bulkSmsUser .
      '&password=' . $this->bulkSmsPassword .
      '&mobile=' . $phone .
      '&message=' . $message .
      '&sender=PFSIND&type=3&template_id=1207168240213327862';
  }

  public function sendWhatsappNotification(string $otp, string $recipient)
  {
    $twilio_whatsapp_number = getenv('+14155238886');
    $account_sid = getenv("TWILIO_ACCOUNT_SID");
    $auth_token = getenv("TWILIO_AUTH_TOKEN");

    $client = new Client($account_sid, $auth_token);
    $message = "Your registration pin code is $otp";
    return $response = $client->messages->create("whatsapp:$recipient", array('from' => "whatsapp:$twilio_whatsapp_number", 'body' => $message));
  }
}
