<?php

namespace App\Helpers;

class CommonHelper
{

  public function __construct() {}
  public static function ageCalculator($dob)
  {
    $dob = new \DateTime($dob);
    $today = new \DateTime();
    $interval = $today->diff($dob);

    if ($interval->y >= 1) {
      // If age is more than or equal to 1 year
      $age = $interval->y . ' Year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m >= 1) {
      // If age is less than 1 year but more than or equal to 1 month
      $age = ($interval->y * 12 + $interval->m) . ' Month' . ($interval->m > 1 ? 's' : '');
    } else {
      // If age is less than a month
      $age = $interval->days . ' Day' . ($interval->days > 1 ? 's' : '');
    }

    return $age;
  }


  public static function upload($data)
  {
    $uploadPath = $data['upload_path'];

    if (is_array($data['file'])) {
      foreach ($data['file'] as $file) {
        $fileName = self::generateRandomString();
        $filePath = $uploadPath . '/' . $fileName;
        file_put_contents($filePath, $file);
        return $fileName;
      }
    } else {
      $file = $data['file'];
      $fileName = self::generateRandomString();
      $filePath = $uploadPath . '/' . $fileName;
      file_put_contents($filePath, $file);
      return $fileName;
    }
  }
  public static function generateRandomString($length = 27)
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString . '.jpg';
  }
  public static function generateID($uniqueId, $type)
  {
    // PSF26399WLK
    $prefix = 'PSF';
    $suffix = '';

    if ($type == 'user') {
      $suffix = 'UR';
    } elseif ($type == 'provider') {
      $suffix = 'SP';
    } elseif ($type == 'walking') {
      $suffix = 'WLK';
    } elseif ($type == 'grooming') {
      $suffix = 'GRM';
    } elseif ($type == 'training') {
      $suffix = 'TRN';
    } elseif ($type == 'boarding') {
      $suffix = 'BRD';
    } elseif ($type == 'walkingExtend') {
      $suffix = 'WLKE';
    } elseif ($type == 'trainingExtend') {
      $suffix = 'TRNE';
    } elseif ($type == 'boardingExtend') {
      $suffix = 'BRDE';
    } elseif ($type == 'groomingExtend') {
      $suffix = 'GRME';
    } elseif ($type == 'walkingTemporary') {
      $suffix = 'WLKT';
    } elseif ($type == 'trainingTemporary') {
      $suffix = 'TRNT';
    } elseif ($type == 'boardingTemporary') {
      $suffix = 'BRDT';
    } elseif ($type == 'groomingTemporary') {
      $suffix = 'GRMT';
    } elseif ($type == 'walkingPermanent') {
      $suffix = 'WLKP';
    } elseif ($type == 'trainingPermanent') {
      $suffix = 'TRNP';
    } elseif ($type == 'boardingPermanent') {
      $suffix = 'BRDP';
    } elseif ($type == 'groomingPermanent') {
      $suffix = 'GRMP';
    } else {
      $suffix = '';
    }

    return $prefix . $uniqueId . $suffix;
  }
  public static function ratingCalculator($sum, $count)
  {
    // overall_rating = (sum_of_ratings / count_of_ratings)
    $overallRating = null;
    $overallRating = $sum / $count;
    return $overallRating;
  }
  public static function distanceCalculator($lat1, $lon1, $lat2, $lon2)
  {
    $earthRadius = 6371; // Earth radius in kilometers

    $lat1 = self::deg2rad($lat1);
    $lon1 = self::deg2rad($lon1);
    $lat2 = self::deg2rad($lat2);
    $lon2 = self::deg2rad($lon2);

    $dLat = $lat2 - $lat1;
    $dLon = $lon2 - $lon1;

    $a = sin($dLat / 2) * sin($dLat / 2) + cos($lat1) * cos($lat2) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $d = $earthRadius * $c;

    return round($d, 4);
  }
  private static function deg2rad($deg)
  {
    return $deg * pi() / 180;
  }

  public static function estimateTime($lat1, $lon1, $lat2, $lon2, $speedKmh)
  {
    // Calculate the distance
    $distance = self::distanceCalculator($lat1, $lon1, $lat2, $lon2);

    // Calculate time in hours, handle case where speed is zero
    $timeHours = $speedKmh > 0 ? $distance / $speedKmh : INF;
    return $timeHours;
  }

  public static function normalizeName($name)
  {

    $name = strtolower($name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    $nameParts = explode(' ', $name);
    sort($nameParts);
    return implode(' ', $nameParts);
  }
}
