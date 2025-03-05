<?php

namespace App\Validation;

class CustomRules
{

  public function validateLatitude($value, ?string &$error = null)
  {

    $lat = (float) $value;
    if ($lat < -90 || $lat > 90) {
      return false;
    }
    if (!preg_match('/^-?\d{1,2}(\.\d+)?$/', $value)) {
      return false;
    }
    return true;
  }

  public function validateLongitude($value, ?string &$error = null)
  {
    if (!preg_match('/^(-?)(\d{1,3})([.]\d+)?$/', $value)) {
      return false;
    }
    $long = (float) $value;
    if ($long < -180 || $long > 180) {
      return false;
    }
    return true;
  }
}
