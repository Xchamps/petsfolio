<?php

namespace App\Controllers\api;

use App\Helpers\CommonHelper;
use App\Libraries\PushNotify;
use App\Libraries\SmsEmailLibrary;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\User;
use CodeIgniter\Controller;
use DateTime;
use Exception;

class UserController extends Controller
{
  protected $smsEmailLibrary;
  protected $user;
  private $pushNotify;
  private $provider;
  private $booking;

  public function __construct()
  {
    $this->smsEmailLibrary = new SmsEmailLibrary();
    $this->user            = new User();
    $this->pushNotify      = new PushNotify();
    $this->provider        = new Provider();
    $this->booking         = new Booking();
  }

  //dashboard
  public function index($user_id)
  {
    $user = $this->user->getUser($user_id);
    if (!$user) {
      return $this->response->setStatusCode(404)->setJSON([
        'error' => 'User not found',
      ]);
    }
    $pets         = $this->user->getUserPets($user_id);
    $addresses    = $this->user->getUserAddresses($user_id);
    $refundAmount = $this->user->getUserRefundAmount($user_id);
    $provider     = $this->provider->getProvider($user_id);
    $rides        = $this->provider->getRideTracking($user_id);
    $bookings     = $this->booking->getLongTermCompletingBookings($user_id);

    $expiringBookings = '';

    foreach ($bookings as $booking) {
      // Get the current date and the service end date
      $currentDate = new DateTime(gmdate('Y-m-d'));
      $endDate     = new DateTime($booking->service_end_date);

      // Calculate the difference in days
      $daysToExpire = $currentDate->diff($endDate)->days;

      // Determine if it is expired or how many days left
      if ($endDate < $currentDate) {
        $expiringBookings = "Booking ID {$booking->id} has already expired.\n";
      } else {
        $expiringBookings = "Booking ID {$booking->id} will expire in {$daysToExpire} days.\n";
      }
    }

    foreach ($rides as &$ride) {
      if ($ride->status == 'started') {
        $ride->status = 'Your Walker ' . $ride->provider_name . ' is on the way....';
        unset($ride->provider_name);
      }
    }

    if (
      !empty($user->name) &&
      !empty($user->gender) &&
      !empty($user->profile)
    ) {
      $personal_details = true;
    } else {
      $personal_details = false;
    }
    $data = [
      'pets'             => count($pets),
      'addresses'        => count($addresses),
      'refundAmount'     => $refundAmount->amount ?? 0,
      'rides'            => $rides ?? [],
      'personal_details' => $personal_details,
      'expiringBookings' => $expiringBookings
    ];
    return $this->response
      ->setJSON($data);
  }

  //profile
  public function profile($user_id)
  {

    $user = $this->user->getUser($user_id);
    if (!$user) {
      return $this->response->setStatusCode(404)->setJSON([
        'error' => 'User not found',
      ]);
    }
    if ($user->profile) {
      $user->profile = base_url() . 'public/uploads/users/' . $user->profile;
    }
    return $this->response->setJSON(['status' => true, 'data' => $user]);
  }

  //update profile
  public function updateProfile()
  {
    $rules = [
      'user_id' => [
        'label'  => 'User ID',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the User ID',
          'numeric'  => 'User ID must be a number',
        ],
      ],
      'name'    => [
        'label'  => 'Name',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Name',
        ],
      ],
      'email'   => [
        'label'  => 'Email',
        'rules'  => 'required|valid_email',
        'errors' => [
          'required'    => 'Please enter the Country',
          'valid_email' => 'Please enter the valid email',
        ],
      ],
      'phone'   => [
        'label'  => 'Phone',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the Phone',
          'numeric'  => 'Phone must be a number',
        ],
      ],
      'gender'  => [
        'label'  => 'Gender',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Gender',
        ],
      ],
    ];
    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(400)->setJSON([
        'status' => false,
        'error'  => $this
          ->validator->getErrors(),
      ]);
    }
    $json = $this->request->getJSON();
    if ($json->profile) {
      try {
        $profile = base64_decode($json->profile);
      } catch (Exception $e) {
        return $this->response->setStatusCode(400)->setJSON([
          'status' => false,
          'error'  => 'Invalid profile image',
        ]);
      }
      $fileData = [
        'file'        => $profile,
        'upload_path' => ROOTPATH . 'public/uploads/users',
      ];
      $fileName = CommonHelper::upload($fileData);
    } else {
      $user     = $this->user->getUser($json->user_id);
      $fileName = $user->profile;
    }

    $data = [
      'name'       => $json->name,
      'email'      => $json->email,
      'phone'      => $json->phone,
      'gender'     => $json->gender,
      'profile'    => $fileName,
      'user_id'    => $json->user_id,
      'ip_address' => $this->request->getIPAddress(),

    ];
    $this->user->updateProfile($data);
    return $this->response->setJSON(['status' => true, 'message' => 'Updated Successfully..']);
  }

  //user add address
  public function addAddress()
  {
    $rules = [
      'user_id'   => [
        'label'  => 'User ID',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the User ID',
          'numeric'  => 'User ID must be a number',
        ],
      ],

      'address'   => [
        'label'  => 'Address',
        'rules'  => 'required|string',
        'errors' => [
          'required' => 'Please enter the address',
          'string'   => 'Address must be a string',
        ],
      ],
      'city'      => [
        'label'  => 'City',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the City',
        ],
      ],
      'state'     => [
        'label'  => 'State',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the State',
        ],
      ],
      'country'   => [
        'label'  => 'Country',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Country',
        ],
      ],
      'zip_code'  => [
        'label'  => 'zip code',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the zip code',
          'numeric'  => 'zip code must be a number',
        ],
      ],
      'latitude'  => [
        'label'  => 'latitude',
        'rules'  => 'required|decimal|validateLatitude',
        'errors' => [
          'required'         => 'Please enter the latitude',
          'decimal'          => 'latitude must be a decimal',
          'validateLatitude' => 'Please enter valid latitude',
        ],
      ],
      'longitude' => [
        'label'  => 'longitude',
        'rules'  => 'required|decimal|validateLongitude',
        'errors' => [
          'required'          => 'Please enter the longitude',
          'decimal'           => 'longitude must be a decimal',
          'validateLongitude' => 'Please enter valid longitude',
        ],
      ],
      'type'      => [
        'label'  => 'type',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the zip code',
        ],
      ],
    ];
    if (!$this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json             = $this->request->getJSON();
    $json->created_at = gmdate('Y-m-d H:i:s');
    $result           = $this->user->addAddress($json);
    if ($result) {
      return $this->response
        ->setJSON(['status' => true, 'message' => 'Address added successfully..', 'address_id' => $result]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Failed to add address']);
    }
  }

  //update address
  public function updateAddress()
  {
    $rules = [
      'id'        => [
        'label'  => 'id',
        'rules'  => 'required|integer',
        'errors' => [
          'required' => 'Please enter the id',
          'integer'  => 'id must be an integer',
        ],
      ],
      'user_id'   => [
        'label'  => 'User ID',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the pet\'s name',
          'numeric'  => 'User ID must be a number',
        ],
      ],
      'address'   => [
        'label'  => 'Address',
        'rules'  => 'required|string',
        'errors' => [
          'required' => 'Please enter the address',
          'string'   => 'Address must be a string',
        ],
      ],
      'city'      => [
        'label'  => 'City',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the City',
        ],
      ],
      'state'     => [
        'label'  => 'State',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the State',
        ],
      ],
      'country'   => [
        'label'  => 'Country',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Country',
        ],
      ],
      'zip_code'  => [
        'label'  => 'zip code',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the zip code',
          'numeric'  => 'zip code must be a number',
        ],
      ],
      'latitude'  => [
        'label'  => 'latitude',
        'rules'  => 'required|decimal|validateLatitude',
        'errors' => [
          'required'         => 'Please enter the latitude',
          'decimal'          => 'latitude must be a decimal',
          'validateLatitude' => 'Please enter valid latitude',
        ],
      ],
      'longitude' => [
        'label'  => 'longitude',
        'rules'  => 'required|decimal|validateLongitude',
        'errors' => [
          'required'          => 'Please enter the longitude',
          'decimal'           => 'longitude must be a decimal',
          'validateLongitude' => 'Please enter valid longitude',
        ],
      ],
      'type'      => [
        'label'  => 'type',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the zip code',
        ],
      ],
    ];
    if (!$this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json             = $this->request->getJSON();
    $json->updated_at = gmdate('Y-m-d H:i:s');
    $result           = $this->user->updateAddress($json);
    if ($result) {
      return $this->response
        ->setJSON(['status' => true, 'message' => 'Address updated successfully..']);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Failed to update address']);
    }
  }

  //delete address
  public function delete($user_id, $id)
  {
    $check = $this->user->checkAddressBooking($user_id, $id);
    if ($check) {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Not allowed to delete address']);
    }

    $result = $this->user->deleteAddress($user_id, $id);
    if ($result) {
      return $this->response
        ->setJSON(['status' => true, 'message' => 'Address deleted successfully..']);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'Failed to delete address']);
    }
  }
  // user addresses
  public function address($user_id)
  {
    $result = $this->user->getAddress($user_id);
    if ($result) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $result]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No address found']);
    }
  }

  // address view
  public function show($id)
  {
    $result = $this->user->getAddressById($id);
    if ($result) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $result]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No address found']);
    }
  }

  //user all confirmed bookings
  public function userBookings($user_id)
  {
    // $bookings   = $this->user->getUserBookings($user_id);
    $walkingBookings  = $this->user->getUserwalkingBookings($user_id);
    $trainingBookings = $this->user->getUsertrainingBookings($user_id);
    $boardingBookings = $this->user->getUserboardingBookings($user_id);
    $groomingBookings = $this->user->getUsergroomingBookings($user_id);

    $bookings = array_merge($walkingBookings, $trainingBookings, $boardingBookings, $groomingBookings);

    $user       = $this->user->getUser($user_id);
    $quotations = $this->user->getUserQuotations($user_id);
    if (empty($quotations)) {
      $quotations = $this->user->getRandomQuotations();
    }
    if (!empty($quotations)) {
      foreach ($quotations as &$quot) {
        if ($quot->profile) {
          $quot->profile = base_url() . 'public/uploads/providers/' . $quot->profile;
        }
      }
    }

    if (!empty($bookings)) {
      $filteredBookings = [];
      foreach ($bookings as &$booking) {

        if ($booking->sp_timings) {
          $booking->preferable_time = $booking->sp_timings;
        }
        // Set the profile URL
        if ($booking->profile) {
          $booking->profile = base_url() . 'public/uploads/providers/' . $booking->profile;
        }

        $booking->completed_timings = [];

        $trackings = $this->user->getTracking($booking->id, $booking->provider_id);

        $completed_timings_set = [];

        foreach ($trackings as &$track) {
          if ($track->service_time && $track->status) {
            if (($booking->sp_timings) && ($booking->preferable_time)) {
              $preferable_times         = explode(',', string: $booking->sp_timings);
              $booking->preferable_time = $booking->sp_timings;
            } else {
              $preferable_times = explode(',', $booking->preferable_time);
            }

            foreach ($preferable_times as $time) {
              $time = trim($time);

              if ($track->service_time == $time && !in_array($time, $completed_timings_set)) {
                $completed_timings_set[] = $time;
              }
            }
          }
        }
        $booking->completed_timings = ($completed_timings_set);
        unset($booking->sp_timings);
        unset($booking->status);
        unset($booking->service_time);

        $startDate   = strtotime($booking->service_start_date); // Convert to timestamp
        $endDate     = strtotime($booking->service_end_date ?? $booking->service_start_date); // Convert to timestamp
        $currentDate = time(); // Current timestamp

        // Check if all timings are completed
        if (count(explode(',', $booking->preferable_time)) !== count($completed_timings_set)) {
          $booking->next_date = '';
        } else {
          // Find the next available date
          $nextDate = $currentDate;

          // Ensure nextDate is between the start and end date
          while ($nextDate < $endDate) {
            $nextDate = strtotime('+1 day', $nextDate); // Increment by one day

            // If nextDate is valid, break the loop
            if ($nextDate >= $startDate && $nextDate <= $endDate) {
              $booking->next_date = date('Y-m-d', $nextDate); // Format to Y-m-d
              break;
            }
          }

          // If no valid date found, set next_date to empty
          if ($nextDate > $endDate) {
            $booking->next_date = '';
          }
        }
      }
    }

    $data = [
      'quotations' => $quotations,
      'bookings'   => $bookings,
    ];
    if ($data) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $data]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No bookings found']);
    }
  }
  // user new bookings not confirmed bookings
  public function userNewBookings($user_id)
  {

    $bookings = array_merge(
      $this->user->getUserNewWalkingBookings($user_id),
      $this->user->getUserNewTrainingBookings($user_id),
      $this->user->getUserNewBoardingBookings($user_id),
      $this->user->getUserNewGroomingBookings($user_id)
    );

    $extendRequests = $this->user->getExtendRequests($user_id);

    foreach ($bookings as &$booking) {
      $booking->address = trim("{$booking->houseno_floor}, {$booking->building_blockno}" .
        (!empty($booking->landmark_areaname) ? ", {$booking->landmark_areaname}" : "") .
        ", {$booking->address}");

      $booking->pets = $this->user->getPetDetails($booking->original_booking_id ?? $booking->booking_id);
      foreach ($booking->pets as &$pet) {
        if ($pet->image) {
          $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
        }
      }
    }
    foreach ($extendRequests as &$booking) {
      $booking->pets   = $this->user->getPetDetails($booking->original_booking_id);
      $booking->addons = $this->user->fetchBookingAddons($booking->booking_id);

      foreach ($booking->pets as &$pet) {
        if ($pet->image) {
          $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
        }
      }
    }
    $data = array_merge($bookings, $extendRequests);
    if ($data) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $data]);
    } else {
      return $this->response
        ->setJSON(['status' => false, 'message' => 'No bookings found']);
    }
  }

  // all quotations by user or city
  public function quotations($user_id)
  {
    $quotations = $this->user->getQuotations($user_id);
    if ($quotations) {
      return $this->response
        ->setJSON(['status' => true, 'data' => $quotations]);
    } else {
      $user       = $this->user->getUser($user_id);
      $quotations = $this->user->getQuotationsByCity($user->city);
      if ($quotations) {
        return $this->response
          ->setJSON(['status' => true, 'data' => $quotations]);
      } else {
        return $this->response
          ->setJSON(['status' => false, 'message' => 'No quotations found']);
      }
    }
  }

  public function sendNotification($userId)
  {
    $title   = '';
    $message = '';

    $user        = $this->user->getUser($userId);
    $deviceToken = $user->device_token;

    $body = [
      "message" => [
        "token"        => $deviceToken,
        "notification" => [
          "title" => $title,
          "body"  => $message,
        ],
        "android"      => [
          "priority" => "HIGH",
        ],
        "apns"         => [
          "headers" => [
            "apns-priority" => "10",
          ],
          "payload" => [
            "aps" => [
              "sound" => "default",
            ],
          ],
        ],
      ],
    ];

    // Convert the payload to JSON
    $bodyJson = json_encode($body);

    $response = $this->pushNotify->sendNotification($bodyJson);
    if ($response['status_code'] == 200) {
      return $this->response->setJSON([
        'status'      => true,
        'status_code' => $response['status_code'],
        'response'    => $response['response'],
      ]);
    } else {
      return $this->response
        ->setStatusCode($response['status_code'])
        ->setJSON([
          'status'  => false,
          'message' => 'Failed',
        ]);
    }
  }

  //notifications
  public function notifications($userId)
  {
    $user = $this->user->getUser($userId);
    if ($user) {

      $notifications = $this->user->getNotifications($user->id);
      if ($notifications) {
        return $this->response
          ->setStatusCode(200)
          ->setJSON(['status' => true, 'data' => $notifications]);
      } else {
        return $this->response
          ->setStatusCode(200)
          ->setJSON(['status' => false, 'message' => 'No notifications']);
      }
    } else {
      return $this->response
        ->setStatusCode(404)
        ->setJSON(['status' => false, 'message' => 'User not found']);
    }
  }

  // review to provider
  public function reviewToProvider()
  {
    $rules = [
      'user_id'     => 'required|numeric',
      'provider_id' => 'required|numeric',
      'service_id'  => 'required',
      'rating'      => 'required',
      'booking_id'  => 'required',
    ];
    if (!$this->validate($rules)) {
      return $this->response
        ->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json = $this->request->getJSON();

    $data   = [
      'user_id'     => $json->user_id,
      'provider_id' => $json->provider_id,
      'service_id'  => $json->service_id,
      'rating'      => $json->rating,
      'comment'     => $json->comment,
      'booking_id'  => $json->booking_id,
      'created_at'  => gmdate("Y-m-d H:i:s"),
    ];
    $result = $this->user->createRating((object) $data);
    if ($result) {
      return $this->response
        ->setStatusCode(200)
        ->setJSON(['status' => true, 'message' => 'Rating created']);
    } else {
      return $this->response
        ->setStatusCode(404)
        ->setJSON(['status' => false, 'message' => 'Error creating rating']);
    }
  }

  public function completedWalks($user_id)
  {
    $walks = $this->user->getCompletedWalks($user_id);

    return $this->response
      ->setStatusCode(200)
      ->setJSON(['status' => true, 'data' => $walks]);
  }
}
