<?php

namespace App\Libraries;

use PhonePe\payments\v1\PhonePePaymentClient;
require_once APPPATH . "ThirdParty/phonepe/vendor/autoload.php";

class PhonePeLibrary
{

    public static function phonePeClient()
    {
        $phonePePaymentsClient = new PhonePePaymentClient(
            getenv('PHPEMERCHANTID'),
            getenv('PHPESALTKEY'),
            getenv('PHPESALTINDEX'),
            getenv('PHPEENV'),
            getenv('PHPESHOULDPUBLISHEVENTS')
        );
        return $phonePePaymentsClient;
    }
}
