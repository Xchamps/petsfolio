<?php

namespace App\Controllers\api;

use App\Controllers\BaseController;
use App\Helpers\CommonHelper;
use App\Libraries\PushNotify;
use App\Libraries\SmsEmailLibrary;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\User;
use App\Models\Wallet;
use DateTime;
use Exception;

class ProviderController extends BaseController
{
  protected $provider;
  protected $user;
  protected $wallet;
  protected $pushNotify;
  protected $booking;
  protected $smsEmailLibrary;

  public function __construct()
  {
    $this->provider        = new Provider();
    $this->user            = new User();
    $this->wallet          = new Wallet();
    $this->pushNotify      = new PushNotify();
    $this->booking         = new Booking();
    $this->smsEmailLibrary = new SmsEmailLibrary();
  }

  // profile data
  public function index($provider_id)
  {
    // Retrieve provider data
    $data = $this->provider->getProvider($provider_id);

    if (!$data) {
      return $this->response->setStatusCode(404)->setJSON(['status' => false, 'message' => 'Not Found']);
    }

    // Profile Image URL
    if ($data->profile) {
      $data->profile = base_url('public/uploads/providers/' . $data->profile);
    }

    // Fetch additional provider details
    $data->totalWithdrawalAmount = $this->provider->getWithdrawalAmount($provider_id);
    $data->totalOrders           = $this->provider->getTotalOrders($provider_id);
    $data->averageRating         = $this->provider->getAverageRating($provider_id);
    $data->totalRatings          = count($this->provider->getRatings($provider_id));
    $data->banks                 = $this->hasBankDetails($provider_id);

    // Service Data Mapping
    $data->services = $this->mapServices($provider_id);

    // Walking and Grooming Jobs
    $quotedJobIds        = $this->provider->getQuotedJobIds($provider_id);
    $data->walkingLeads  = $this->countNewJobs('walking', $quotedJobIds, $data);
    $data->groomingLeads = $this->countNewJobs('grooming', $quotedJobIds, $data);

    // Verifications
    $verifications              = $this->provider->getAllVerifications($provider_id);
    $data->verifications        = $verifications;
    $data->completionPercentage = $this->calculateCompletionPercentage($verifications);

    // Remove sensitive data
    unset($data->device_token, $data->auth_token, $data->aadhar_name, $data->aadhar_number, $data->status);

    // Check if provider address exists for quotes
    $data->quoteExists = $this->provider->checkProviderAddress($provider_id);

    return $this->response->setStatusCode(200)->setJSON(['status' => true, 'data' => $data]);
  }

  private function hasBankDetails($provider_id)
  {
    $bankDetails = $this->provider->getProviderBank($provider_id);
    return !empty($bankDetails) && !empty($bankDetails->beneficiaryAccountNumber);
  }

  private function mapServices($provider_id)
  {
    $allServices = $this->user->getServices();
    $spServices  = $this->provider->getSpServices($provider_id);
    $result      = array_fill_keys(array_column($allServices, 'name'), false);

    foreach ($spServices as $service) {
      $result[$service->service_name] = true;
    }
    return $result;
  }

  private function countNewJobs($type, $quotedJobIds, $data)
  {
    $method  = 'new' . ucfirst($type) . 'Jobs';
    $jobs    = $this->provider->$method();
    $newJobs = [];

    foreach ($jobs as $job) {
      if (in_array($job->booking_id, $quotedJobIds)) {
        continue;
      }

      $user = $this->provider->getAddressByBooking($type, $job->booking_id);
      if (!$user) {
        continue;
      }

      $distance = CommonHelper::distanceCalculator($user->latitude, $user->longitude, $data->service_latitude, $data->service_longitude);
      if ($distance <= 30) {
        $newJobs[] = $job;
      }
    }
    return count($newJobs);
  }

  private function calculateCompletionPercentage($verifications)
  {
    $totalVerifications     = count((array) $verifications);
    $completedVerifications = count(array_filter((array) $verifications));
    return ($totalVerifications > 0) ? ($completedVerifications / $totalVerifications) * 100 : 0;
  }


  // update profile data
  public function updateProfile()
  {
    $rules = [
      'provider_id'       => [
        'label'  => 'Provider ID',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the Service Provider ID',
          'numeric'  => 'Service Provider ID must be a number',
        ],
      ],
      'name'              => [
        'label'  => 'Name',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Name',
        ],
      ],
      'phone'             => [
        'label'  => 'Phone',
        'rules'  => 'required|numeric',
        'errors' => [
          'required' => 'Please enter the Phone',
          'numeric'  => 'Phone must be a number',
        ],
      ],
      'gender'            => [
        'label'  => 'Gender',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the Gender',
        ],
      ],
      'email'             => [
        'label'  => 'Email',
        'rules'  => 'required|valid_email',
        'errors' => [
          'required'    => 'Please enter the Country',
          'valid_email' => 'Please enter the valid email',
        ],
      ],
      'service_address'   => [
        'label'  => 'service address',
        'rules'  => 'required|string',
        'errors' => [
          'required' => 'Please enter the service address',
          'string'   => 'service address must be a string',
        ],
      ],
      'service_latitude'  => [
        'label'  => 'latitude',
        'rules'  => 'required|decimal|validateLatitude',
        'errors' => [
          'required'         => 'Please enter the latitude',
          'decimal'          => 'latitude must be a decimal',
          'validateLatitude' => 'Please enter valid latitude',
        ],
      ],
      'service_longitude' => [
        'label'  => 'longitude',
        'rules'  => 'required|decimal|validateLongitude',
        'errors' => [
          'required'          => 'Please enter the longitude',
          'decimal'           => 'longitude must be a decimal',
          'validateLongitude' => 'Please enter valid longitude',
        ],
      ],
      'city'              => [
        'label'  => 'City',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the City',
        ],
      ],
      'state'             => [
        'label'  => 'State',
        'rules'  => 'required',
        'errors' => [
          'required' => 'Please enter the State',
        ],
      ],
      'zip_code'          => [
        'label'  => 'zip code',
        'rules'  => 'required|numeric|max_length[7]|min_length[5]',
        'errors' => [
          'required' => 'Please enter the zip code',
        ],
      ],
    ];
    if (!$this->validate($rules)) {
      $validationErrors = $this->validator->getErrors();
      return $this->response->setJSON(['status' => false, 'errors' => $validationErrors]);
    }
    $json        = $this->request->getJSON();
    $json        = $this->request->getJSON();
    $provider_id = $json->provider_id;
    $proivder    = $this->provider->getProvider($provider_id);
    if (!$proivder) {
      return $this->response->setJSON(['status' => false, 'message' => 'Service Provider Not Found']);
    }

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
        'upload_path' => ROOTPATH . 'public/uploads/providers',
      ];
      $fileName = CommonHelper::upload($fileData);
    } else {
      $provider = $this->provider->getProvider($provider_id);
      $fileName = $provider->profile;
    }
    $data   = [
      'provider_id'       => $json->provider_id,
      'name'              => $json->name,
      'email'             => $json->email,
      'phone'             => $json->phone,
      'gender'            => $json->gender,
      'dob'               => $json->dob,
      "area"              => $json->area ?? '',
      "service_address"   => $json->service_address,
      "service_latitude"  => $json->service_latitude,
      "service_longitude" => $json->service_longitude,
      'city'              => $json->city,
      "state"             => $json->state,
      "zip_code"          => $json->zip_code,
      'profile'           => $fileName,
      'ip_address'        => $this->request->getIPAddress(),

    ];
    $result = $this->provider->updateProfile($data);
    if ($result) {
      return $this->response->setJSON(['status' => true, 'message' => 'Service Provider updated successfully...']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Failed to update']);
    }
  }

  // add bank to withdrawl money
  public function addBank()
  {
    $json = $this->request->getJSON();

    $rules = [
      'provider_id'    => 'required',
      'account_number' => 'required|numeric|min_length[8]|max_length[18]',
      'ifsc_code'      => 'required|regex_match[/^[A-Za-z]{4}[0-9]{6,7}$/]',

    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $provider_id = $json->provider_id;
    $proivder    = $this->provider->getProvider($provider_id);

    if (!$proivder) {
      return $this->response->setJSON(['status' => false, 'message' => 'Service Provider Not Found']);
    }
    $result = $this->provider->addBank($json);
    if ($result) {
      return $this->response->setJSON(['status' => true, 'message' => 'Bank added successfully..']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Failed to Add bank']);
    }
  }

  // ratings , photos of previous services jobs
  public function ratingsPhotos()
  {
    $rules = [
      'provider_id' => 'required|numeric',
      'user_id'     => 'required|numeric',
      'service_id'  => 'required|numeric',
      'booking_id'  => 'required',
    ];
    if (!$this->validate($rules)) {
      $validationErrors = $this->validator->getErrors();
      return $this->response->setJSON(['status' => true, 'errors' => $validationErrors]);
    }
    $json        = $this->request->getJSON();
    $provider_id = $json->provider_id;
    $booking_id  = $json->booking_id;
    $user_id     = $json->user_id;
    $service_id  = $json->service_id;

    $reviews = $this->provider->getRatings($provider_id);
    $photos  = $this->provider->getPhotos($provider_id);
    $data    = $this->provider->getVerfications($provider_id);
    if ($data->profile) {

      $data->profile = base_url() . 'public/uploads/providers/' . $data->profile;
    }
    foreach ($reviews as &$review) {
      if ($review->profile) {
        $review->profile = base_url() . 'public/uploads/users/' . $review->profile;
      }
    }
    foreach ($photos as &$photo) {
      if ($photo->file_name) {
        $photo->file_name = base_url() . 'public/uploads/providers_service/' . $photo->file_name;
      }
    }
    $averageRating          = $this->provider->getAverageRating($provider_id);
    $totalCompletedBookings = $this->provider->getTotalCompletedBookings($provider_id);
    if ($service_id == '1') {
      $address = $this->provider->getBoardingBookingAddress($user_id, $booking_id);
    } else if ($service_id == '2') {
      $address = $this->provider->getGroomingBookingAddress($user_id, $booking_id);
    } else if ($service_id == '3') {
      $address = $this->provider->getTrainingBookingAddress($user_id, $booking_id);
    } else if ($service_id == '4') {
      $address = $this->provider->getWalkingBookingAddress($user_id, $booking_id);
    } else {
    }
    $provider = $this->provider->getProvider($provider_id);
    $lat1     = $provider->service_latitude;
    $long1    = $provider->service_longitude;

    $lat2     = $address->latitude;
    $long2    = $address->longitude;
    $distance = CommonHelper::distanceCalculator($lat1, $long1, $lat2, $long2);

    $data->averageRating            = $averageRating;
    $data->total_completed_bookings = $totalCompletedBookings;
    $data->distance                 = number_format($distance, 1);

    if ($data) {
      return $this->response->setJSON([
        'status'  => true,
        'data'    => $data,
        'reviews' => array_values($reviews),
        'photos'  => $photos,

      ]);
    } else {
      return $this->response->setJSON(['status' => true, 'message' => 'No ratings & photos found']);
    }
  }

  // service profile
  public function addServiceDetails()
  {

    $rules = [
      'service_id'                 => 'required',
      'provider_id'                => 'required',
      'can_handle_aggressive_pets' => 'required',
      'is_certified'               => 'required',
      'is_accidentally_insured'    => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json = $this->request->getJSON();
    if ($json->certificate) {
      $certificate       = base64_decode($json->certificate);
      $fileData          = [
        'file'        => $certificate,
        'upload_path' => ROOTPATH . 'public/uploads/providers/verifications',
      ];
      $json->certificate = CommonHelper::upload($fileData);
    }
    $check = $this->provider->checkProviderServices($json->provider_id, $json->service_id);
    if (!$check) {
      $this->provider->addProviderServices($json->provider_id, $json->service_id);

      if ($json->service_id == '1') {
        $this->provider->addBoardingDetails((array) $json);
        return $this->response->setJSON(['status' => true, 'message' => 'Success']);
      } else if ($json->service_id == '2') {
        $this->provider->addGroomingDetails((array) $json);
        return $this->response->setJSON(['status' => true, 'message' => 'Success']);
      } else if ($json->service_id == '3') {
        $this->provider->addTrainingDetails((array) $json);
        return $this->response->setJSON(['status' => true, 'message' => 'Success']);
      } else if ($json->service_id == '4') {
        $this->provider->addWalkingDetails((array) $json);
        return $this->response->setJSON(['status' => true, 'message' => 'Success']);
      } else {
        return $this->response->setJSON(['status' => false, 'message' => 'Invalid Service Id']);
      }
    } else {
      return $this->response->setJSON([
        'status'  => false,
        'message' => 'Service already added',
      ]);
    }
  }

  //  manage walk: start,end,complete
  public function manageWalk()
  {
    $json   = $this->request->getJSON();
    $action = $json->action;

    $rules = [];
    switch ($action) {
      case 'start':
        $rules = [
          'booking_id'   => 'required',
          'provider_id'  => 'required',
          'pet_id'       => 'required',
          'service_time' => 'required',
        ];
        break;
      case 'end':
        $rules = [
          'booking_id'  => 'required',
          'provider_id' => 'required',
          'pet_id'      => 'required',
        ];
        break;
      case 'complete':
        $rules = [
          'booking_id'  => 'required',
          'provider_id' => 'required',
          'pet_id'      => 'required',
        ];
        break;
      default:
        return $this->response->setJSON(['status' => false, 'message' => 'Invalid action']);
    }

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $type     = '';
    $pet      = $this->booking->getPetName($json->pet_id);
    $provider = $this->provider->getProvider($json->provider_id);

    switch ($action) {
      case 'start':
        $check = $this->provider->checkWalk((array) $json);
        if ($check) {
          return $this->response->setJSON(['status' => false, 'message' => 'already started']);
        }
        $this->provider->startWalk((array) $json);
        $this->provider->endRide((array) $json);
        $title = 'Walking service';
        $message = $provider->name . ' has started walking ' . ($pet->name ?? '');
        $type = 'start_walk';
        break;
      case 'end':
        $this->provider->endWalk((array) $json);
        $this->provider->endRide((array) $json);
        $title = "Walking service";
        $message = $provider->name . " has finished walking " . ($pet->name ?? '') . " Please Confirm!";
        $type = 'end_walk';
        break;
      case 'complete':

        $this->provider->completeWalk((array) $json);
        $this->provider->endRide((array) $json);
        $title = "Walking service";
        $message = "Payment for today's walk has been successfully released.";
        $type = 'end_walk';
        break;
    }
    $user  = $this->booking->getUserByBooking($json->booking_id, 'walking_service_bookings');
    $token = $user->device_token;
    $this->booking->createNotification([
      'user_id'   => $user->id,
      'user_type' => 'user',
      'type'      => $type,
      'message'   => $message,
    ]);
    $this->pushNotify->notify($token, $title, $message);
    return $this->response->setJSON(['status' => true, 'message' => 'success']);
  }

  public function newJobs($provider_id, $service_id)
  {
    // Get the provider details
    $provider = $this->provider->getProvider($provider_id);

    if (!$provider) {
      return $this->response->setJSON(['status' => false, 'message' => 'Provider not found']);
    }
    $cityJobs = [];

    // Fetch jobs based on the service_id
    switch ($service_id) {
      case '1':
        $jobs = $this->provider->newBoardingJobs();
        break;
      case '2':
        $jobs = $this->provider->newGroomingJobs();
        $quotedJobIds = $this->provider->getQuotedJobIds($provider_id);

        foreach ($jobs as $job) {

          $service      = [];
          $service_mode = $this->provider->getServiceDetails($provider_id)->service_location;
          $service      = [
            [
              'name'  => 'van_service',
              'price' => getenv('VAN_SERVICE'),
              'type'  => ($service_mode == 'van_service')
            ],
            [
              'name'  => 'home_service',
              'price' => '0',
              'type'  => ($service_mode == 'home_service')
            ]
          ];
          $job->service = $service;
          if (in_array($job->booking_id, $quotedJobIds)) {
            continue;
          }
          $job->totalQuotes = $this->provider->getTotalQuotes($job->booking_id, $service_id);
          $job->pets        = $this->provider->getBookingPets($job->original_booking_id ?? $job->booking_id);
          $distance         = CommonHelper::distanceCalculator($job->latitude, $job->longitude, $provider->service_latitude, $provider->service_longitude);
          $job->distance    = number_format($distance, 1) . ' Km';
          // Process pet images
          foreach ($job->pets as &$pet) {
            if ($pet->image) {
              $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
            }
            $pet->age = CommonHelper::ageCalculator($pet->dob);
          }
          if ($distance <= 30) {
            $cityJobs[] = $job;
          }
        }
        break;
      case '3':
        $jobs = $this->provider->newTrainingJobs();
        break;

      case '4':
        $jobs = $this->provider->newWalkingJobs();
        $quotedJobIds = $this->provider->getQuotedJobIds($provider_id);

        foreach ($jobs as $job) {
          if (in_array($job->booking_id, $quotedJobIds)) {
            continue;
          }

          // Populate job details
          $job->totalQuotes = $this->provider->getTotalQuotes($job->booking_id, $service_id);
          $job->pets        = $this->provider->getBookingPets($job->original_booking_id ?? $job->booking_id);
          $job->addons      = $this->provider->getBookingAddons($job->original_booking_id ?? $job->booking_id);

          if ($job->service_frequency == 'once a day') {
            $frequency = 1;
          } elseif ($job->service_frequency == 'twice a day') {
            $frequency = 2;
          } elseif ($job->service_frequency == 'thrice a day') {
            $frequency = 3;
          } else {
            $frequency = 0;
          }
          // Update addon prices
          foreach ($job->addons as &$addon) {
            $days = $job->days;

            if ($addon->name == 'Poo Picking') {
              $addon->price = (string) (getenv('POO_PICKING_PRICE') * $days * $frequency);
            } elseif ($addon->name == 'Combing') {
              $addon->price = (string) (getenv('COMBING_PRICE') * $days * $frequency);
            } elseif ($addon->name == 'Paw Cleaning') {
              $addon->price = (string) (getenv('PAW_CLEANING_PRICE') * $days * $frequency);
            }
          }
          // Calculate distance between user and provider
          $distance      = CommonHelper::distanceCalculator($job->latitude, $job->longitude, $provider->service_latitude, $provider->service_longitude);
          $job->distance = number_format($distance, 1) . ' Km';
          // Process pet images
          foreach ($job->pets as &$pet) {
            if ($pet->image) {
              $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
            }
            $pet->age = CommonHelper::ageCalculator($pet->dob);
          }

          if ($job->original_booking_id) {
            $originalBooking = $this->provider->getBookingDetails($job->original_booking_id);

            // Check booking type logic
            if ($job->type === 'extend') {
              if (!$job->repost) { // If repost is false
                if ($originalBooking->provider_id != $provider_id) {
                  continue; // Only show to the original provider
                }
              }
              // If repost is true, no restrictions, continue to process this job
            } elseif (in_array($job->type, ['temporary', 'permanent'])) {

              $job->days = $this->calculateTotalDays($job->service_start_date, $job->service_end_date, $job->service_days);
              foreach ($job->addons as &$addon) {

                if ($addon->name == 'Poo Picking') {
                  $addon->price = (string) (getenv('POO_PICKING_PRICE') * $job->days * $frequency);
                } elseif ($addon->name == 'Combing') {
                  $addon->price = (string) (getenv('COMBING_PRICE') * $job->days * $frequency);
                } elseif ($addon->name == 'Paw Cleaning') {
                  $addon->price = (string) (getenv('PAW_CLEANING_PRICE') * $job->days * $frequency);
                }
              }

              if ($originalBooking->provider_id == $provider_id) {
                continue; // Exclude if the original provider is the same
              }
            }
          }

          if ($distance <= 30) {
            $cityJobs[] = $job;
          }
        }
        break;
      default:
        return $this->response->setJSON(['status' => false, 'message' => 'Invalid service type']);
    }



    return $this->response->setJSON(['status' => true, 'data' => $cityJobs]);
  }
  private function calculateTotalDays($startDateStr, $endDateStr, $serviceDays)
  {
    $startDate = new DateTime($startDateStr);
    $endDate = new DateTime($endDateStr);
    $totalDays = 0;

    // Loop through each day from start to end date (inclusive)
    while ($startDate <= $endDate) {
      if ($serviceDays === 'weekdays') {
        // Count only weekdays (Monday to Saturday, skip Sunday)
        if ($startDate->format('N') < 7) {
          $totalDays++;
        }
      } else {
        // Count all days
        $totalDays++;
      }
      // Move to the next day
      $startDate->modify('+1 day');
    }

    return $totalDays;
  }

  private function hasJobBeenQuoted($job_id)
  {
    return $this->provider->hasQuoteForJob($job_id);
  }

  //bidded quotes
  public function myQuotes($provider_id, $service_id)
  {

    if ($service_id == '1') {
      $quotes = $this->provider->myBoardingQuotes($provider_id);
    } elseif ($service_id == '2') {
      $quotes = $this->provider->myGroomingQuotes($provider_id);
    } elseif ($service_id == '3') {
      $quotes = $this->provider->myTrainingQuotes($provider_id);
    } elseif ($service_id == '4') {
      $quotes = $this->provider->myWalkingQuotes($provider_id);
    } else {
    }
    $provider = $this->provider->getProvider($provider_id);
    foreach ($quotes as &$quote) {
      // $service_mode = $this->provider->getServiceDetails($provider_id)->service_location;
      if ($service_id == '2') {

        $service_mode = $quote->service_mode;
        $service      = [
          [
            'name'  => 'van_service',
            'price' => getenv('VAN_SERVICE'),
            'type'  => ($service_mode == 'van_service')
          ],
          [
            'name'  => 'home_service',
            'price' => '0',
            'type'  => ($service_mode == 'home_service')
          ]
        ];

        $quote->service = $service;
      }
      if ($quote->sp_timings) {
        $quote->preferable_time = $quote->sp_timings;
      }
      $quote->pets = $this->provider->getBookingPets($quote->booking_id);
      foreach ($quote->pets as &$pet) {
        if ($pet->image) {
          $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
        }
        $pet->age = CommonHelper::ageCalculator($pet->dob);
      }
      if ($quote->provider_profile) {
        $quote->provider_profile = base_url() . 'public/uploads/providers/' . $quote->provider_profile;
      }
      $quote->totalQuotes = $this->provider->getTotalQuotes($quote->booking_id, $service_id);
      $quote->addons      = $this->provider->getQuoteAddons($quote->quotation_id, $provider_id);
      $bookingAddons      = $this->provider->getBookingAddons($quote->booking_id);

      if (!$quote->addons) {
        $quote->addons = $bookingAddons;
      }
      $quote->bookingAddOns = $bookingAddons;
      $lat1                 = $provider->service_latitude;
      $long1                = $provider->service_longitude;

      $lat2     = $quote->latitude;
      $long2    = $quote->longitude;
      $distance = CommonHelper::distanceCalculator($lat1, $long1, $lat2, $long2);

      $quote->distance = number_format($distance, 1) . ' Km';
      unset($quote->latitude);
      unset($quote->longitude);
    }
    return $this->response->setJSON(['status' => true, 'data' => $quotes]);
  }

  //confirmed jobs
  public function activeJobs($provider_id, $service_id)
  {
    if ($service_id == '1') {
      $jobs = $this->provider->activeBoardingJobs($provider_id);
    } elseif ($service_id == '2') {
      $jobs              = $this->provider->activeGroomingJobs($provider_id);
      $todays_bookings   = [];
      $upcoming_bookings = [];
      $today             = gmdate('Y-m-d');
      foreach ($jobs as &$job) {
        $job->pets   = $this->provider->getBookingPets($job->booking_id);
        $job->addons = $this->provider->getBookingAddons($job->booking_id);
        if ($job->profile) {
          $job->profile = base_url() . 'public/uploads/users/' . $job->profile;
        }

        foreach ($job->addons as $addon) {
          $addon->status = 'pending';
        }

        foreach ($job->pets as &$pet) {

          $pet->addons      = $this->provider->getBookingAddons2($job->booking_id);
          $pet->packages    = $this->provider->getBookingPackages($job->booking_id);
          $trackingAddons   = $this->booking->getGroomingTracking($job->booking_id, $provider_id, $pet->pet_id);
          $trackingPackages = $this->booking->getGroomingTrackingPackages($job->booking_id, $provider_id, $pet->pet_id);

          $trackingStatusMapAddons     = [];
          $trackingApprovalMapAddons   = [];
          $trackingStatusMapPackages   = [];
          $trackingApprovalMapPackages = [];

          foreach ($trackingAddons as $tracking) {
            $trackingStatusMapAddons[$tracking->addon]   = $tracking->status;
            $trackingApprovalMapAddons[$tracking->addon] = $tracking->is_approved ?? false;
          }

          foreach ($trackingPackages as $tracking) {
            $trackingStatusMapPackages[$tracking->package_name]   = $tracking->status;
            $trackingApprovalMapPackages[$tracking->package_name] = $tracking->is_approved ?? false;
          }

          $petAddonsWithStatus = [];
          $allAddonsCompleted  = true;
          $anyAddonRejected    = false;
          foreach ($pet->addons as $addon) {
            $status                = $trackingStatusMapAddons[$addon] ?? 'pending';
            $is_approved           = $trackingApprovalMapAddons[$addon] ?? false;
            $petAddonsWithStatus[] = (object) [
              'name'        => $addon,
              'status'      => $status,
              'is_approved' => $is_approved,
            ];
            if ($status === 'rejected' && !$is_approved) {
              $anyAddonRejected = true;
            }
            if ($status !== 'completed' || !$is_approved) {
              $allAddonsCompleted = false;
            }
          }

          $petPackagesWithStatus = [];
          $allPackagesCompleted  = true;
          $anyPackageRejected    = false;
          foreach ($pet->packages as $package) {
            $status                  = $trackingStatusMapPackages[$package->package_name] ?? 'pending';
            $is_approved             = $trackingApprovalMapPackages[$package->package_name] ?? false;
            $petPackagesWithStatus[] = (object) [
              'package_id'      => $package->package_id,
              'package_name'    => $package->package_name,
              'price'           => $package->price,
              'included_addons' => $package->included_addons,
              'status'          => $status,
              'is_approved'     => $is_approved,
            ];
            if ($status === 'rejected' && !$is_approved) {
              $anyPackageRejected = true;
            }
            if ($status !== 'completed' || !$is_approved) {
              $allPackagesCompleted = false;
            }
          }

          $pet->addons   = $petAddonsWithStatus;
          $pet->packages = $petPackagesWithStatus;

          if ($anyAddonRejected || $anyPackageRejected) {
            $pet->track_status = 'rejected';
          } elseif ($allAddonsCompleted && $allPackagesCompleted) {
            $pet->track_status = 'approved';
          } else {
            $pet->track_status = $job->track_status == 'in_progress' ? 'in_progress' : ($trackingAddons || $trackingPackages ? 'completed' : 'not_started');
          }

          if ($pet->image) {
            $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
          }

          $pet->age  = CommonHelper::ageCalculator($pet->dob);
          $pet->city = $job->city;
        }
        $job->addons   = $petAddonsWithStatus;
        $job->packages = $petPackagesWithStatus;

        if ($job->service_start_date <= $today) {
          if ($job->service_start_date > $today) {
            $upcoming_bookings[] = $job;
          } else {
            $todays_bookings[] = $job;
          }
        } elseif ($job->service_start_date > $today) {
          $upcoming_bookings[] = $job;
        }
      }
    } elseif ($service_id == '3') {
      $jobs = $this->provider->activeTrainingJobs($provider_id);
    } elseif ($service_id == '4') {
      $jobs              = $this->provider->activeWalkingJobs($provider_id);
      $todays_bookings   = [];
      $upcoming_bookings = [];
      $today             = gmdate('Y-m-d');
      foreach ($jobs as &$job) {

        $service_days = $job->service_days;

        // Check if the job's service days are weekdays (Mon-Sat) and it's not Sunday
        $current_day = date('l'); // Get the current day of the week (e.g., "Monday")

        // If the job is for weekdays (Mon-Sat) and today is Sunday, skip it
        if ($service_days == 'weekdays' && $current_day === 'Sunday') {
          continue; // Skip this job if it's Sunday
        }

        // Fetch pets and add-ons for the job
        if ($job->profile) {
          $job->profile = base_url() . 'public/uploads/users/' . $job->profile;
        }
        $job->pets            = $this->provider->getBookingPets($job->original_booking_id ?? $job->booking_id);
        $job->addons          = $this->provider->getBookingAddons($job->booking_id);
        $job->preferable_time = $job->sp_timings ?? $job->preferable_time;

        foreach ($job->pets as &$pet) {
          // Process pet image path
          if ($pet->image) {
            $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
          }
          $pet->age = CommonHelper::ageCalculator($pet->dob);

          $pet->city            = $job->city;
          $pet->preferable_time = $job->sp_timings ?? $job->preferable_time;

          $trackings             = $this->provider->getTracking($job->id, $provider_id, $pet->pet_id);
          $completed_timings_set = [];
          $pet->completedTime    = '';
          $pet->service_time     = null;
          $pet->walkstatus       = null;
          $all_timings_completed = true;

          $preferable_times = $job->sp_timings ? explode(',', $job->sp_timings) : explode(',', $job->preferable_time);
          if ($trackings) {

            foreach ($trackings as $track) {
              if ($track->service_time && $track->status) {
                $preferable_times = $job->sp_timings ? explode(',', $job->sp_timings) : explode(',', $job->preferable_time);

                foreach ($preferable_times as $time) {
                  $time = trim($time);
                  if ($track->service_time == $time && $track->status == 'completed') {
                    $completed_timings_set[] = $time;
                    $pet->service_time       = '';
                    $pet->walkstatus         = '';
                  } elseif ($track->status == 'in_progress') {
                    $pet->service_time     = $track->service_time;
                    $pet->walkstatus       = 'in_progress';
                    $all_timings_completed = false;
                  } else {
                    $pet->service_time     = '';
                    $pet->walkstatus       = 'not_started';
                    $all_timings_completed = false;
                  }
                }
              } else {
                $pet->service_time     = '';
                $pet->walkstatus       = 'not_started';
                $all_timings_completed = false;
              }
            }
          } else {
            $pet->service_time     = '';
            $pet->walkstatus       = 'not_started';
            $all_timings_completed = false;
          }
          $pet->completedTime = implode(',', $completed_timings_set);
          if ($all_timings_completed && count($completed_timings_set) == count($preferable_times)) {
            $pet->walkstatus = 'completed';
          } elseif (!$all_timings_completed) {
            // $pet->walkstatus = 'in_progress';
          } else {
            $pet->walkstatus = 'not_started';
          }
        }

        if ($job->sp_timings && $job->preferable_time) {
          $job->preferable_time = $job->sp_timings;
        }

        if (!empty($job->booking_id)) {
          // Fetch the temporary booking details using the original_booking_id
          $temp_booking = $this->provider->getTemporaryBooking($job->booking_id);
          if ($temp_booking) {
            $temp_start_date = $temp_booking->service_start_date;
            $temp_end_date = $temp_booking->service_end_date;


            // Check if today's date falls within the temporary booking period
            $is_temp_period = ($job->service_start_date >= $temp_start_date && $job->service_end_date >= $temp_end_date);

            // Check if the current provider is the original provider
            $is_original_provider = ($job->provider_id == $provider_id);

            // If the original provider is viewing and a temp booking exists, skip the original booking
            if ($is_original_provider && $is_temp_period) {
              continue; // Don't show the original booking to the original provider
            }
          }
        }

        unset($job->sp_timings, $job->status, $job->service_time);
        if ($job->service_start_date <= $today && $job->service_end_date >= $today) {
          // Check if all timings are completed and end date is in the future
          if (count($completed_timings_set) == count($preferable_times) && $job->service_end_date > $today) {
            $upcoming_bookings[] = $job; // Move to upcoming if all walks are completed
          } else {
            $todays_bookings[] = $job; // Otherwise, keep it as today's booking
          }
        } elseif ($job->service_start_date > $today) {
          $upcoming_bookings[] = $job; // Future jobs
        }
      }
    } else {
      return $this->response
        ->setJSON(['staus' => false, 'message' => 'Invalid Service']);
    }

    return $this->response->setJSON([
      'status' => true,
      'data'   => [
        'todays_bookings'   => $todays_bookings,
        'upcoming_bookings' => $upcoming_bookings
      ]
    ]);
    // return $this->response->setJSON(['status' => true, 'data' =>
    // $jobs]);
  }

  public function myWalks($provider_id, $service_id)
  {
    $jobs = [];

    $boardingJobs = $this->provider->activeBoardingJobs($provider_id);
    $groomingJobs = $this->provider->activeGroomingJobs($provider_id);
    $trainingJobs = $this->provider->activeTrainingJobs($provider_id);
    $walkingJobs = $this->provider->activeWalkingJobs($provider_id);
    // Combine all jobs into a single array
    $jobs = array_merge($boardingJobs, $groomingJobs, $trainingJobs, $walkingJobs);


    $currentDate = date('Y-m-d');
    $filteredJobs = [];

    $filteredJobs = [];

    if (is_array($jobs)) {
      foreach ($jobs as $job) {
        // Directly add Grooming jobs without date checks
        if (
          $job->service_name == 'Grooming' ||
          (isset($job->service_start_date, $job->service_end_date) &&
            $job->service_start_date <= $currentDate &&
            $job->service_end_date >= $currentDate)
        ) {

          $filteredJobs[] = [
            'location'        => $job->city ?? 'Unknown',
            'service'         => $job->service_name ?? 'Unknown',
            'preferable_time' => $job->sp_timings ?? $job->preferable_time ?? 'Unknown',
          ];
        }
      }
    }



    return $this->response->setJSON(['status' => true, 'data' => $filteredJobs]);
  }

  // uploads photos & videos of previous services
  public function upload()
  {
    $rules = [
      'files'       => 'required',
      // 'files.*.size'=>'max:1024',
      'provider_id' => 'required',
    ];
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $files       = $this->request->getJsonVar('files');
    $provider_id = $this->request->getJsonVar('provider_id');

    $fileData = [];
    foreach ($files as $file) {
      $file_name = base64_decode($file->name);
      $file_type = $file->type;
      $fileData  = [
        'file_type'   => $file_type,
        'file'        => $file_name,
        'upload_path' => ROOTPATH . 'public/uploads/providers_service',
      ];
      $fileName  = CommonHelper::upload($fileData);
      $result    = $this->provider->uploadServicePhoto($provider_id, $fileName, $file_type);
    }
    if ($result) {
      return $this->response->setJSON(['status' => true, 'message' => 'Success']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'Failed']);
    }
  }

  public function getBookingProviders()
  {
    $rules = [
      'booking_id'  => 'required',
      'service_id'  => 'required',
      'provider_id' => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $booking_id  = $this->request->getJsonVar('booking_id');
    $provider_id = $this->request->getJsonVar('provider_id');
    $service_id  = $this->request->getJsonVar('service_id');

    if ($service_id == '1') {
      $providers = $this->provider->getBoardingBookingProviders($booking_id);
    } else if ($service_id == '2') {
      $providers = $this->provider->getGroomingBookingProviders($booking_id);
    } else if ($service_id == '3') {
      $providers = $this->provider->getTrainingBookingProviders($booking_id);
    } else if ($service_id == '4') {
      // Walking service
      $providers = $this->provider->getWalkingBookingProviders($booking_id);
    }

    $currentProvider = null;
    foreach ($providers as &$provider) {
      if ($provider->profile) {
        $provider->profile = base_url() . 'public/uploads/providers/' . $provider->profile;
      }
      if ($provider_id == $provider->provider_id) {
        // $provider->name = $provider->name;
        $currentProvider = $provider;
      }
    }

    if ($currentProvider) {
      $providers = array_filter($providers, function ($provider) use ($provider_id) {
        return $provider->provider_id != $provider_id;
      });

      // array_unshift($providers, $currentProvider);
    }

    return $this->response->setJSON(['status' => true, 'data' => array_values($providers)]);
  }

  public function extendRequest($provider_id)
  {
    $requests = $this->provider->getExtendRequest($provider_id);
    foreach ($requests as $request) {
      $request->pets   = $this->provider->getPetDetails($request->original_booking_id);
      $request->addons = $this->provider->getBookingAddons($request->booking_id);
      foreach ($request->pets as &$pet) {
        if ($pet->image) {
          $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
        }
      }
    }
    return $this->response->setJSON(['status' => true, 'data' => $requests]);
  }

  public function manageRequest()
  {
    $rules = [
      'booking_id'  => 'required',
      'service_id'  => 'required',
      'provider_id' => 'required',
      'action'      => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $booking_id  = $this->request->getJsonVar('booking_id');
    $provider_id = $this->request->getJsonVar('provider_id');
    $service_id  = $this->request->getJsonVar('service_id');
    $action      = $this->request->getJsonVar('action');
    if ($service_id == '4') {
      $this->provider->updateRequest($booking_id, $action);
    } else {
      // $this->provider->updateRequest($booking_id, $provider_id, $service_id);
    }
    $user             = $this->booking->getUserToken($booking_id);
    $provider         = $this->provider->getProvider($provider_id);
    $original_booking = $this->booking->getBookingById($booking_id);

    $pet  = $this->booking->getPet($original_booking->original_booking_id ?? $booking_id);
    $type = '';

    if ($action == 'accepted') {
      $title   = 'Dog walking service extension';
      $message = "Walking service extension approved by " . $provider->name;
      $type    = 'extend_approved';
    } else {
      $title   = 'Dog walking service extension';
      $message = "Walking service extension rejected by " . $provider->name;
      $type    = 'extend_rejected';
    }

    $notifiData = [
      'user_id'   => $user->id,
      'user_type' => 'user',
      'type'      => $type,
      'message'   => $message,
    ];

    $this->booking->createNotification($notifiData);

    $response = $this->pushNotify->notify($user->device_token, $title, $message);

    return $this->response->setJSON([
      'status'  => true,
      'message' =>
      'Request updated successfully.',
    ]);
  }

  // grooming tracking
  public function manageAndApproveGroomingTracking()
  {
    $rules = [
      'booking_id'  => 'required',
      'provider_id' => 'required',
      'pet_id'      => 'required',
      'action'      => 'required|in_list[create,update,approve,reject]',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json = $this->request->getJSON();
    log_message('info', 'Received JSON: ' . json_encode($json));

    $data = [
      'booking_id'  => $json->booking_id,
      'provider_id' => $json->provider_id,
      'pet_id'      => $json->pet_id,
    ];

    $pendingAmount   = 0;
    $completedAmount = 0;

    $booking           = $this->booking->getGroomingPriceCalculate($json->booking_id, $json->provider_id);
    $discount          = floatval($booking->discount);
    $platform_charges  = floatval(getenv('PLATFORM_PERCENTAGE'));
    $bookingPackages   = $this->provider->getBookingPackages($data['booking_id']);
    $bookingPackageIds = array_column($bookingPackages, 'package_id');
    log_message('info', "Booking details - Discount: $discount, Platform Charges: $platform_charges");

    // Process package tracking
    foreach ($json->packages as $package) {
      $data['package_id'] = $package->package_id;
      $data['status']     = $package->status;;

      $existingPkgRecord = $this->provider->getGroomingTrackingByPackage($data['booking_id'], $data['package_id'], $json->pet_id);

      if (in_array($json->action, ['create', 'update'])) {
        if ($json->action === 'create' && !$existingPkgRecord) {
          $this->provider->createGroomingTrackingPkg($data);
          $this->provider->updateGroomingBooking($data['booking_id'], 'completed');
        } elseif ($json->action === 'update' && $existingPkgRecord) {
          $this->provider->updateGroomingTracking($data, $existingPkgRecord->id);
        }
      } elseif ($json->action === 'approve') {
        if (!$existingPkgRecord) {
          $createdRecordId = $this->provider->createGroomingTrackingPkg($data);
          $this->provider->approveGroomingTracking($data, $createdRecordId);
        } else {
          $this->provider->approveGroomingTracking($data, $existingPkgRecord->id);
        }
        $this->provider->updateGroomingBooking($data['booking_id'], 'approved');
      } elseif ($json->action === 'reject') {
        $data['status'] = 'rejected';
        if (!$existingPkgRecord) {
          $createdRecordId = $this->provider->createGroomingTrackingPkg($data);
          $this->provider->rejectGroomingTracking($data, $createdRecordId);
        } else {
          $this->provider->rejectGroomingTracking($data, $existingPkgRecord->id);
        }
      }

      if ($existingPkgRecord && in_array($package->package_id, $bookingPackageIds)) {
        $packagePrice = floatval($this->provider->getPackageById($package->package_id)->price);
        log_message('info', "Processing Package ID: $package->package_id, Price: $packagePrice");
        if ($existingPkgRecord->status === 'completed') {
          $completedAmount += floatval($packagePrice);
        } elseif ($existingPkgRecord->status === 'pending') {
          log_message('info', "Processing pending Package ID: $package->package_id, Price: $packagePrice");
          $pendingAmount += floatval($packagePrice);
        }
      }
    }

    // Process addon tracking
    foreach ($json->addons as $addon) {
      $data['addon']      = $addon->name;
      $data['status']     = $addon->status;
      $data['package_id'] = '';

      $existingAddonRecord = $this->provider->getGroomingTrackingByAddon($data['booking_id'], $data['addon']);

      if (in_array($json->action, ['create', 'update'])) {
        if ($json->action === 'create' && !$existingAddonRecord) {
          $this->provider->createGroomingTracking($data);
          $this->provider->updateGroomingBooking($data['booking_id'], 'completed');
        } elseif ($json->action === 'update' && $existingAddonRecord) {
          $this->provider->updateGroomingTracking($data, $existingAddonRecord->id);
        }
      } elseif ($json->action === 'approve') {
        if (!$existingAddonRecord) {
          $createdRecordId = $this->provider->createGroomingTracking($data);
          $this->provider->approveGroomingTracking($data, $createdRecordId);
        } else {
          $this->provider->approveGroomingTracking($data, $existingAddonRecord->id);
        }
        $this->provider->updateGroomingBooking($data['booking_id'], 'approved');
        $this->provider->updateBooking($data['booking_id']);
      } elseif ($json->action === 'reject') {
        $this->provider->updateBooking($data['booking_id']);

        $data['status'] = 'rejected';
        if (!$existingAddonRecord) {
          $createdRecordId = $this->provider->createGroomingTracking($data);
          $this->provider->rejectGroomingTracking($data, $createdRecordId);
        } else {
          $this->provider->rejectGroomingTracking($data, $existingAddonRecord->id);
        }
      }

      $addonDetails = $this->provider->getAddonByName($addon->name);
      if ($addonDetails && $existingAddonRecord) {
        $addonPrice = floatval($addonDetails->price);
        log_message('info', "Processing Addon: {$addon->name}, Price: $addonPrice");
        if ($existingAddonRecord->status === 'completed') {
          $completedAmount += floatval($addonDetails->price);
        } elseif ($existingAddonRecord->status === 'pending') {
          log_message('info', "Processing pending Addon: {$addon->name}, Price: $addonPrice");

          $pendingAmount += floatval($addonDetails->price);
        }
      }
    }

    $vanAmount = ($booking->service_mode == 'van_service') ? floatval(getenv('VAN_SERVICE')) : 0;
    log_message('info', "Van Service Charge: $vanAmount");

    $totalWithVanAmount = floatval($completedAmount + $vanAmount);
    log_message('info', "Total Completed Amount : $completedAmount");

    log_message('info', "Total Completed Amount (with van charge): $totalWithVanAmount");

    $discountAmount = floatval(($discount > 0) ? ($totalWithVanAmount * $discount) / 100 : 0);
    log_message('info', "Discount Applied: $discountAmount");


    $netAmount = floatval($totalWithVanAmount - $discountAmount);
    log_message('info', "Net Amount after Discount: $netAmount");

    $platformAmount = floatval(($netAmount * $platform_charges) / 100);
    log_message('info', "Platform Charges: $platformAmount");

    $providerAmount = floatval($netAmount - $platformAmount);
    log_message('info', "Provider Amount (Earnings): $providerAmount");

    if ($json->action === 'approve') {
      if ($netAmount > 0) {
        // Process provider and user wallet transactions
        $this->wallet->creditWallet($json->provider_id, $providerAmount);
        $this->wallet->debitGroomingRefundAmount($json->user_id, $netAmount, $json->pet_id);
        $this->wallet->debitSpGroomingRefundAmount($json->provider_id, $providerAmount, $json->pet_id);

        $this->wallet->logTransaction($json->provider_id, 'credit', $providerAmount, 'Approved Grooming service amount', 'sp_wallet_histories');
        $this->wallet->logTransaction($json->user_id, 'debit', $netAmount, 'Approved Grooming service amount', 'user_wallet_histories');
        log_message('info', "Credited Provider Wallet: $providerAmount");
        log_message('info', "Debited User Wallet: $netAmount");
      }

      if ($pendingAmount > 0) {

        $totalAmount = floatval($pendingAmount);
        log_message('info', "pendingAmount: $pendingAmount");

        $discounPendingtAmount = floatval(($discount > 0) ? ($totalAmount * $discount) / 100 : 0);
        log_message('info', "pendingAmount discount: $discounPendingtAmount");
        log_message('info', "discount: $discount");

        $pendingNetAmount = floatval($totalAmount - $discounPendingtAmount);
        log_message('info', "pendingNetAmount discount: $pendingNetAmount");

        $platformPendingAmount = floatval(($pendingNetAmount * getenv('PLATFORM_PERCENTAGE')) / 100);
        log_message('info', "platformAmount discount: $platformPendingAmount");

        $providerPendingAmount = floatval($pendingNetAmount - $platformPendingAmount);
        log_message('info', "providerAmount discount: $providerPendingAmount");


        $this->wallet->addRefundAmount($json->user_id, $pendingNetAmount, $json->pet_id, $service_id = '2');
        $this->wallet->debitGroomingRefundAmount($json->user_id, $pendingNetAmount, $json->pet_id);
        $this->wallet->debitSpGroomingRefundAmount($json->provider_id, $providerPendingAmount, $json->pet_id);

        $this->wallet->logTransaction($json->provider_id, 'debit', $providerPendingAmount, 'Rejected Grooming service refund', 'sp_wallet_histories');
        $this->wallet->logTransaction($json->user_id, 'credit', $pendingNetAmount, 'Rejected Grooming service refund', 'user_wallet_histories');
        log_message('info', "Refunded Pending Amount to User: $pendingNetAmount");
        log_message('info', "Refunded Pending Amount to Provider: $providerPendingAmount");
      }
    }

    return $this->response->setJSON([
      'status'  => true,
      'message' => ucfirst($json->action) . ' completed successfully',
    ]);
  }



  public function withdrawAmount()
  {
    $rules = [
      'amount'      => 'required|numeric|greater_than_equal_to[0]|less_than_equal_to[1000000]',
      'provider_id' => 'required',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $amount     = $this->request->getJsonVar('amount');
    $providerId = $this->request->getJsonVar('provider_id');

    $total = $this->wallet->getTotalWithdrawalAmount($providerId);

    if (!empty($total)) {
      $spDetails = $this->provider->getProvider($providerId);
      // $spBank = $this->provider->getUserBankAccount($providerId, $bankId);
      if (!empty($spDetails)) {

        if (floatval($amount) > floatval($total->amount)) {
          return $this->response
            ->setJSON(['status' => false, 'message' => 'Invalid amount']);
        }
      }
      $this->provider->updateWithdrawlRequest($providerId, $amount);

      return $this->response->setJSON(['status' => true, 'message' => 'Withdrawal successful.']);
      // if (!empty($spDetails)) {
      //   if (!empty($spBank->upi_id)) {
      //     $fundAccountId = $this->createRazorpayFundAccount($spBank);
      //     $response = $this->initiateRazorpayPayout($fundAccountId, $amount, "UPI");
      //   } elseif (!empty($spBank->account_number)) {
      //     $fundAccountId = $this->createRazorpayFundAccount($spBank);
      //     $response = $this->initiateRazorpayPayout($fundAccountId, $amount, "IMPS");
      //   } else {
      //     return $this->response->setJSON(['status' => false, 'message' => 'Invalid withdrawal details.']);
      //   }
      // }

      // $statusCode = $response->getStatusCode();
      // if ($response && ($statusCode == 200 || $statusCode == 201)) {
      //   $this->wallet->debitProviderWallet($providerId, $amount);
      //   return $this->response->setJSON(['status' => true, 'message' => 'Withdrawal successful.']);
      // } else {
      //   return $this->response->setJSON(['status' => false, 'message' => 'Payout failed: ' . $response->error->description]);
      // }
      // } else {
      //   return $this->response->setJSON(['status' => false, 'message' => 'Failed']);
      // }
    }
  }

  private function createRazorpayFundAccount($userBankOrUpi)
  {
    $curl = \Config\Services::curlrequest();

    $razorpayKeyId     = getenv('RAZORPAY_KEY_ID');
    $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');

    $fundAccountData = [
      "contact_id"   => "cont_PBguRi0kW7Tugv",
      "account_type" => isset($userBankOrUpi->upi_id) ? "vpa" : "bank_account", // Check if it's UPI or Bank
    ];

    if (isset($userBankOrUpi->upi_id)) {
      $fundAccountData["vpa"] = [
        "address" => $userBankOrUpi->upi_id,
      ];
    } else {
      $fundAccountData["bank_account"] = [
        "name"           => $userBankOrUpi->account_holder_name,
        "ifsc"           => $userBankOrUpi->ifsc_code,
        "account_number" => $userBankOrUpi->account_number,
      ];
    }

    try {
      $response = $curl->request('POST', 'https://api.razorpay.com/v1/fund_accounts', [
        'auth'    => [$razorpayKeyId, $razorpayKeySecret],
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json'    => $fundAccountData,
      ]);

      if ($response->getStatusCode() === 200) {
        $result = json_decode($response->getBody());
        return $result->id;
      } else {
        return false;
      }
    } catch (\Exception $e) {
      return false;
    }
  }

  private function initiateRazorpayPayout($fundAccountId, $amount, $mode = "IMPS")
  {
    $curl              = \Config\Services::curlrequest();
    $razorpayKeyId     = getenv('RAZORPAY_KEY_ID');
    $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');

    if (intval($amount) <= 0) {
      return false;
    }

    $payoutData = [
      "account_number"       => "2323230099936812",
      "fund_account_id"      => $fundAccountId,
      "amount"               => intval($amount * 100), // Amount in paise
      "currency"             => "INR",
      "mode"                 => $mode, // IMPS, UPI, etc.
      "purpose"              => "payout",
      "queue_if_low_balance" => true,
      "reference_id"         => uniqid(),
      "narration"            => "Withdrawal to provider",
      "notes"                => [],
    ];

    try {
      $response = $curl->request('POST', 'https://api.razorpay.com/v1/payouts', [
        'auth'    => [$razorpayKeyId, $razorpayKeySecret],
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json'    => $payoutData,
      ]);

      if ($response->getStatusCode() === 200) {
        return $response;
      } else {
        return false;
      }
    } catch (\Exception $e) {
      return false;
    }
  }

  public function serviceImages($provider_id)
  {
    $serviceImages = $this->provider->getserviceImages($provider_id);
    foreach ($serviceImages as &$img) {
      $img_url    = base_url() . 'public/uploads/providers_service/' . $img->image;
      $img->image = $img_url;
    }
    return $this->response->setJSON(['status' => true, 'data' => $serviceImages]);
  }
  public function deleteImage()
  {
    $rules = [
      'imageId'     => 'required',
      'provider_id' => 'required',
    ];

    // Validate the request data
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $imageId     = $this->request->getJsonVar('imageId');
    $provider_id = $this->request->getJsonVar('provider_id');
    $result      = $this->provider->deleteImage($imageId, $provider_id);
    if ($result) {
      return $this->response->setJSON(['status' => true, 'message' => 'Image deleted successfully']);
    } else {
      return $this->response->setJSON(['status' => false, 'message' => 'unable to delete']);
    }
  }

  public function saveTracking()
  {
    $json = $this->request->getJSON();

    $existingEntry = $this->provider->getExistingTrack($json->pet_id, $json->date);

    $mediaFields = ['morning_photo', 'morning_video', 'afternoon_photo', 'afternoon_video', 'evening_photo', 'evening_video'];
    $fileNames   = [];

    foreach ($mediaFields as $field) {
      if (!empty($json->$field)) {
        $file     = base64_decode($json->$field);
        $fileData = [
          'file'        => $file,
          'upload_path' => ROOTPATH . 'public/uploads/providers_service',
        ];
        $fileName = CommonHelper::upload($fileData);
        if ($fileName) {
          $fileNames[$field] = $fileName;
        } else {
          return $this->response->setJSON(['status' => false, 'message' => 'Failed to upload ' . $field . '.']);
        }
      }
    }

    $data = [
      'booking_id'      => $json->booking_id,
      'provider_id'     => $json->provider_id,
      'pet_id'          => $json->pet_id,
      'tracking_date'   => $json->date,
      'morning_photo'   => $fileNames['morning_photo'] ?? null,
      'morning_video'   => $fileNames['morning_video'] ?? null,
      'afternoon_photo' => $fileNames['afternoon_photo'] ?? null,
      'afternoon_video' => $fileNames['afternoon_video'] ?? null,
      'evening_photo'   => $fileNames['evening_photo'] ?? null,
      'evening_video'   => $fileNames['evening_video'] ?? null,
    ];

    if ($existingEntry) {
      if ($this->provider->updateTracking($existingEntry->id, $data)) {
        return $this->response->setJSON(['status' => true, 'message' => 'Updated successfully.']);
      } else {
        return $this->response->setJSON(['status' => false, 'message' => 'Failed to update tracking entry.']);
      }
    } else {
      if ($this->provider->saveTracking($data)) {
        return $this->response->setJSON(['status' => true, 'message' => 'Created successfully.']);
      } else {
        return $this->response->setJSON(['status' => false, 'message' => 'Failed to create tracking entry.']);
      }
    }
  }

  public function manageRide()
  {
    $rules = [
      'booking_id'  => 'required',
      'provider_id' => 'required',
      'status'      => 'required',
    ];

    // Validate the request data
    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json          = $this->request->getJSON();
    if ($json->service_id == '1') {
      $table = 'boarding_service_bookings';
      $service = '';
    } elseif ($json->service_id == '2') {
      $table = 'grooming_service_bookings';
      $type = 'groomer';
      $service = 'grooming';
    } elseif ($json->service_id == '3') {
      $table = 'training_service_bookings';
      $service = '';
    } elseif ($json->service_id == '4') {
      $table = 'walking_service_bookings';
      $type = 'walker';
      $service = 'walk';
    }
    $user          = $this->provider->getUserByBookingId($json->booking_id, $table);
    $existingEntry = $this->provider->getEntryById($json->booking_id, $json->provider_id);
    $existing      = $this->provider->getEntryBy($json->booking_id, $json->provider_id);
    if ($existing) {
      return $this->response->setJSON([
        'status'  => true,
        'message' => 'Ride Already Started',
      ]);
    }
    $title   = '';
    $message = '';
    if (!$existingEntry) {
      // Start the ride if no existing entry found
      $data = [
        'booking_id'  => $json->booking_id,
        'provider_id' => $json->provider_id,
        'start_time'  => gmdate('Y-m-d h:i:s'),
        'ride_date'   => gmdate('Y-m-d'),
        'status'      => 'started',
        'service_id'  => $json->service_id
      ];
      $this->provider->saveEntry($data);

      $title   = 'Your pet ' . $type . ' is on the way';
      $message = 'please have your pet ready for the ' . $service . '!';
    } else {
      // Stop the ride if entry is found
      $data = [
        'booking_id'  => $json->booking_id,
        'provider_id' => $json->provider_id,
        'entry_id'    => $existingEntry->id,
        'status'      => 'started',
        'end_time'    => gmdate('Y-m-d h:i:s'),
        'service_id'  => $json->service_id

      ];
      $this->provider->updateEntry($data);
    }

    $data = [
      'title'        => $title,
      'message'      => $message,
      'device_token' => $user->device_token,
    ];
    $this->booking->createNotification([
      'user_id'   => $user->user_id,
      'user_type' => 'user',
      'type'      => 'start_ride',
      'message'   => $message,
    ]);
    $response = $this->pushNotify->notify($user->device_token, $title, $message);

    return $this->response->setJSON([
      'status'  => true,
      'message' => isset($data['end_time']) ? 'Ride stopped' : 'Ride started',
    ]);
  }
  //notifications
  public function notifications($provider_id)
  {
    $provider = $this->provider->getProvider($provider_id);
    if ($provider) {

      $notifications = $this->provider->getNotifications($provider_id);
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

  public function banks($provider_id)
  {
    $provider = $this->provider->getProvider($provider_id);
    if ($provider) {
      $banks = $this->provider->getBanks($provider_id);
      if ($banks) {
        return $this->response
          ->setStatusCode(200)
          ->setJSON(['status' => true, 'data' => $banks]);
      } else {
        return $this->response
          ->setStatusCode(200)
          ->setJSON(['status' => false, 'message' => 'No banks']);
      }
    } else {
      return $this->response
        ->setStatusCode(404)
        ->setJSON(['status' => false, 'message' => 'User not found']);
    }
  }

  public function sendOtpToMail()
  {
    $json = $this->request->getJSON(true);
    if (!$json || !isset($json['email'])) {
      return $this->response->setStatusCode(400)->setJSON([
        'status'  => false,
        'message' => 'Invalid JSON payload or missing email field.'
      ]);
    }

    $rules = [
      'email' => 'required|valid_email',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(422)->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors()
      ]);
    }

    $email    = $json['email'];
    $provider = $this->provider->getProviderByEmail($email);

    if (!$provider) {
      return $this->response->setStatusCode(404)->setJSON([
        'status'  => false,
        'message' => 'Email not found in our records.'
      ]);
    }

    // Generate OTP
    $otp = random_int(1111, 9999);

    $subject = 'Verify Your Email Address';
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
                    font-size: 24px;
                    color: #4CAF50;
                    margin-bottom: 20px;
                }
                .content {
                    font-size: 16px;
                    line-height: 1.5;
                }
                .otp {
                    font-size: 18px;
                    font-weight: bold;
                    color: #D32F2F;
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
                .footer a {
                    color: #4CAF50;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    Verify Your Email Address
                </div>
                <div class="content">
                    <p>Hi {$provider->name},</p>
                    <p>Thank you for using <strong>Petsfolio</strong>. To complete your verification, Please use the One-Time Password (OTP) provided below.</p>
                    <p class="otp">Your OTP is: <strong>$otp</strong></p>
                    <p>Please do not share this code with anyone.</p>
                    <p>If you didn’t make this request, please <a href="mailto:support@petsfolio.com">contact our support team</a> immediately.</p>
                </div>
                <div class="footer">
                <img src="https://cmschamps.com/psf-admin/public/assets/images/icons/Petsfolio.png" alt="Petsfolio Logo">
                    <p>Thank you,<br><strong>The Petsfolio Team</strong></p>
                </div>
            </div>
        </body>
    </html>
    EOT;

    // Send the email
    try {
      $this->smsEmailLibrary->sendEmail($email, $subject, $body);

      $this->provider->storeOtp($email, $otp);

      return $this->response->setStatusCode(200)->setJSON([
        'status'  => true,
        'message' => 'OTP sent successfully to your email.'
      ]);
    } catch (Exception $e) {
      log_message('error', 'Failed to send email: ' . $e->getMessage());
      return $this->response->setStatusCode(500)->setJSON([
        'status'  => false,
        'message' => 'Failed to send verification email. Please try again later.'
      ]);
    }
  }

  public function verifyOtpFromMail()
  {
    $rules = [
      'email' => 'required|valid_email',
      'otp'   => 'required'
    ];

    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(422)->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors()
      ]);
    }
    $json     = $this->request->getJSON();
    $provider = $this->provider->getProviderByEmail($json->email);

    $data = $this->provider->checkMailOtp($json->email, $json->otp);
    if ($data) {
      $this->provider->removeMailOtp($json->email);
      $this->provider->updateMailVerification($provider->id);
      return $this->response->setStatusCode(200)->setJSON([
        'status'  => true,
        'message' => 'OTP verified successfully.'
      ]);
    } else {
      return $this->response->setStatusCode(422)->setJSON([
        'status' => false,
        'errors' => 'Invalid OTP'
      ]);
    }
  }

  public function uploadCertificate()
  {
    $rules = [
      'provider_id'      => 'required',
      'certificate'      => 'required',
      'certificate_type' => 'required'

    ];
    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(422)->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors()
      ]);
    }
    $json     = $this->request->getJSON();
    $provider = $this->provider->getProviderById($json->provider_id);
    if (!$provider) {
      return $this->response->setStatusCode(404)->setJSON([
        'status' => false,
        'errors' => 'Provider not found'
      ]);
    }
    try {
      $certificate = base64_decode($json->certificate);
    } catch (Exception $e) {
      return $this->response->setStatusCode(400)->setJSON([
        'status' => false,
        'error'  => 'Invalid certificate',
      ]);
    }
    $fileData = [
      'file'        => $certificate,
      'upload_path' => ROOTPATH . 'public/uploads/providers/verifications',
    ];
    $fileName = CommonHelper::upload($fileData);
    $this->provider->updateCertificate($provider->id, $fileName, $json->certificate_type);
    return $this->response->setStatusCode(200)->setJSON([
      'status'  => true,
      'message' => 'Certificate uploaded successfully.',
    ]);
  }
  public function startGrooming()
  {
    $rules = [
      'pet_id'     => 'required',
      'booking_id' => 'required',
      'action'     => 'required'

    ];
    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(422)->setJSON([
        'status' => false,
        'errors' => $this->validator->getErrors()
      ]);
    }
    $json = $this->request->getJSON();
    $this->provider->startGrooming($json->booking_id, $json->action);
    return $this->response->setStatusCode(200)->setJSON([
      'status'  => true,
      'message' => 'success.',
    ]);
  }

  public function terminateGrooming()
  {
    $rules = [
      'booking_id'  => 'required',
      'provider_id' => 'required',
      'pet_id'      => 'required'
    ];

    if (!$this->validate($rules)) {
      return $this->response->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json = $this->request->getJSON();

    // Validate JSON input
    if (empty($json)) {
      return $this->response->setStatusCode(400)->setJSON([
        'status' => false,
        'error'  => 'Invalid JSON input',
      ]);
    }

    // Handle image uploads
    $fileName = [];
    if (!empty($json->images)) {
      foreach ($json->images as $image) {
        try {
          $profile    = base64_decode($image);
          $fileData   = [
            'file'        => $profile,
            'upload_path' => ROOTPATH . 'public/uploads/providers_service/grooming',
          ];
          $fileName[] = CommonHelper::upload($fileData);
        } catch (Exception $e) {
          return $this->response->setStatusCode(400)->setJSON([
            'status' => false,
            'error'  => 'Invalid image',
          ]);
        }
      }
    }

    $data = [
      'booking_id'  => $json->booking_id,
      'provider_id' => $json->provider_id,
      'pet_id'      => $json->pet_id,
      'reason'      => $json->reason ?? '',
      'rejected_by' => 'provider',
      'images'      => !empty($fileName) ? implode(',', $fileName) : '',
    ];

    // Helper function to handle tracking and rejection
    $handleTracking = function ($type, $id, $status = '') use ($data, $json) {
      $data['package_id'] = $type === 'package' ? $id : '';
      $data['addon']  = $type === 'addon' ? $id : '';
      $data['status'] = $status;

      $existingRecord = $type === 'package'
        ? $this->provider->getGroomingTrackingByPackage($data['booking_id'], $id, $json->pet_id)
        : $this->provider->getGroomingTrackingByAddon($data['booking_id'], $id);

      if (!$existingRecord) {
        $createdRecordId = $type === 'package'
          ? $this->provider->createGroomingTrackingPkg($data)
          : $this->provider->createGroomingTracking($data);
        $this->provider->rejectGroomingTracking($data, $createdRecordId);
      } else {
        $this->provider->rejectGroomingTracking($data, $existingRecord->id);
      }
      $this->provider->rejectGrooming($data['booking_id']);
    };

    // Handle package tracking
    if (!empty($json->package_id)) {
      foreach ($json->package_id as $package_id) {
        $handleTracking('package', $package_id);
      }
    }

    // Handle addon tracking
    if (!empty($json->addons)) {
      foreach ($json->addons as $addon) {
        $handleTracking('addon', $addon->name, $addon->status);
      }
    }

    // Return success response
    return $this->response->setJSON([
      'status'  => true,
      'message' => 'Rejected completed successfully',
    ]);
  }
}
