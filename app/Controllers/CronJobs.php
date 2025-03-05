<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\User;
use App\Models\Quotation;
use App\Models\Wallet;
use App\Libraries\PushNotify;
use App\Libraries\SmsEmailLibrary;
use App\Helpers\CommonHelper;
use DateInterval;
use DatePeriod;
use DateTime;

class CronJobs extends Controller
{
    protected $user;

    protected $booking;
    protected $quotations;
    protected $wallet;
    protected $provider;
    protected $pushNotify;

    public function __construct()
    {
        $this->user       = new User();
        $this->booking    = new Booking();
        $this->quotations = new Quotation();
        $this->wallet     = new Wallet();
        $this->provider   = new Provider();
        $this->pushNotify = new PushNotify();
    }
    public function processUnquotedBookings()
    {
        // Time limit of 1 hour in seconds
        $timeLimit = 3600;

        // Process each service
        $this->processTrainingUnquotedBookings($timeLimit);
        $this->processWalkingUnquotedBookings($timeLimit);
        $this->processBoardingUnquotedBookings($timeLimit);
        $this->processGroomingUnquotedBookings($timeLimit);

        return true;
    }

    // Training service unquoted bookings processing
    private function processTrainingUnquotedBookings($timeLimit)
    {
        $unquotedBookings = $this->booking->getUnquotedBookingsForTraining($timeLimit);
        $this->notifyProviders($unquotedBookings, 'Training');
        return $unquotedBookings;
    }

    // Walking service unquoted bookings processing
    private function processWalkingUnquotedBookings($timeLimit)
    {
        $unquotedBookings = $this->booking->getUnquotedBookingsForWalking($timeLimit);
        $this->notifyProviders($unquotedBookings, 'Walking');
        return $unquotedBookings;
    }

    // Boarding service unquoted bookings processing
    private function processBoardingUnquotedBookings($timeLimit)
    {
        $unquotedBookings = $this->booking->getUnquotedBookingsForBoarding($timeLimit);
        $this->notifyProviders($unquotedBookings, 'Boarding');
        return $unquotedBookings;
    }

    // Grooming service unquoted bookings processing
    private function processGroomingUnquotedBookings($timeLimit)
    {
        $unquotedBookings = $this->booking->getUnquotedBookingsForGrooming($timeLimit);
        $this->notifyProviders($unquotedBookings, 'Grooming');
        return $unquotedBookings;
    }

    // Notify providers for unquoted bookings
    private function notifyProviders($unquotedBookings, $serviceType)
    {
        // Log the start of the method
        log_message('info', "notifyProviders started with serviceType: {$serviceType}");

        $providers = $this->provider->getProviders();
        log_message('debug', 'Fetched providers: ' . json_encode($providers));

        foreach ($unquotedBookings as $booking) {
            log_message('info', "Processing booking ID: {$booking->id}");

            $notifiedProviders = [];
            $pet               = $this->booking->getPet($booking->id);
            $user              = $this->booking->getUserByBooking($booking->id);

            log_message('debug', "Fetched pet data for booking ID {$booking->id}: " . json_encode($pet));
            log_message('debug', "Fetched user data for booking ID {$booking->id}: " . json_encode($user));

            foreach ($providers as $provider) {
                log_message('info', "Checking provider ID: {$provider->id}");

                if ($provider->device_token && !in_array($provider->id, $notifiedProviders)) {
                    $distance = CommonHelper::distanceCalculator(
                        $user->latitude,
                        $user->longitude,
                        $provider->service_latitude,
                        $provider->service_longitude
                    );

                    log_message('debug', "Calculated distance to provider ID {$provider->id}: {$distance}");

                    if ($distance <= 30) {
                        $token   = $provider->device_token;
                        $title   = 'New job alert - Walking';
                        $message = "Don’t miss out—submit your quote now!";

                        log_message('info', "Notifying provider ID: {$provider->id} with message: {$message}");

                        $this->booking->createNotification([
                            'user_id'   => $provider->id,
                            'user_type' => 'provider',
                            'type'      => 'booking_requested',
                            'message'   => $message,
                        ]);

                        $PushNotify = new PushNotify();
                        $PushNotify->notify($token, $title, $message);

                        log_message('info', "Notification sent to provider ID: {$provider->id}");
                        $notifiedProviders[] = $provider->id;
                    }
                }

                if (!empty($notifiedProviders)) {
                    log_message('info', "Breaking loop after notifying provider ID: {$provider->id}");
                    break;
                }
            }
        }

        log_message('info', "notifyProviders completed.");
    }


    public function sendUnquotedBookings()
    {
        // Time limit of 2 hour in seconds
        $timeLimit = 3600 * 5;

        // Process each service
        // $trainingBookings = $this->processTrainingUnquotedBookings($timeLimit);
        $walkingBookings = $this->processWalkingUnquotedBookings($timeLimit);
        // $boardingBookings = $this->processBoardingUnquotedBookings($timeLimit);
        // $groomingBookings = $this->processGroomingUnquotedBookings($timeLimit);

        $bookings = $walkingBookings;
        return $bookings;
    }
    public function updateBooking()
    {
        $bookings = $this->booking->getCompletedBookings();

        $currentDate = new DateTime();
        foreach ($bookings as $booking) {
            if ($booking->service_frequency == 'once a day') {
                $frequency = 1;
            } elseif ($booking->service_frequency == 'twice a day') {
                $frequency = 2;
            } elseif ($booking->service_frequency == 'thrice a day') {
                $frequency = 3;
            } else {
                $frequency = 0;
            }

            if ($booking->service_end_date === $currentDate->format('Y-m-d')) {
                // Verify if today's walks are completed
                $todayWalks = $this->booking->getCompletedWalks($booking->id, $currentDate->format('Y-m-d'));

                if ($todayWalks >= $frequency) {
                    // Update booking status to 'completed'
                    $this->booking->updateBookingStatus($booking->id, 'completed');
                    $this->booking->updateQuoteStatus($booking->id, 'completed');

                    // Notify the provider
                    $title   = 'Walk Completed';
                    $message = "All walks have been successfully completed for booking." . ($pet->name ?? '');
                }
            }
        }
        return $this->response->setJSON(['status' => true, 'message' => 'success']);
    }

    public function update()
    {
        log_message('info', 'Update process started.');
        $currentDate = date('Y-m-d');

        // Fetch all completed bookings
        $completedBookings = $this->booking->getCompletedBookings();
        log_message('info', 'Retrieved ' . count($completedBookings) . ' completed bookings.');

        foreach ($completedBookings as $booking) {
            log_message('info', 'Processing booking ID: ' . $booking->id);

            // Handle temporary bookings
            $temporaryBookings = $this->booking->getTempBooking($booking->user_id);
            foreach ($temporaryBookings as $tempBooking) {
                log_message('info', 'Processing temporary booking ID: ' . $tempBooking->id);
                $originalBooking = $this->booking->updateTemp($tempBooking->user_id, $tempBooking->original_booking_id);
                if ($originalBooking) {
                    $this->booking->updateOldBooking($tempBooking->original_booking_id, 'onHold');
                }
                if (strtotime($tempBooking->service_end_date) <= time()) {
                    $this->booking->updateTempBookingStatus($tempBooking->id, 'Completed');
                    $this->booking->updateOldBooking($tempBooking->original_booking_id, 'Confirmed');
                }
            }

            // Handle permanent bookings
            $permanentBookings = $this->booking->getPermanentBooking($booking->user_id);
            foreach ($permanentBookings as $perBooking) {
                log_message('info', 'Processing permanent booking ID: ' . $perBooking->id);
                $originalBooking = $this->booking->updateTemp($perBooking->user_id, $perBooking->original_booking_id);
                if ($originalBooking) {
                    $this->booking->updateOldBooking($perBooking->original_booking_id, 'onHold');
                }
                if (strtotime($perBooking->service_end_date) <= time()) {
                    $this->booking->updateTempBookingStatus($perBooking->id, 'Completed');
                }
            }

            // Handle extend bookings
            $extendBookings = $this->booking->getExtendBooking($booking->user_id);
            foreach ($extendBookings as $exBooking) {
                if (strtotime($booking['service_end_date']) <= time()) {
                    log_message('info', 'Confirming extended booking ID: ' . $exBooking->id);
                    $this->booking->updateBookingStatus($exBooking->id, 'Confirmed');
                }
            }
        }

        // Cancel unconfirmed temporary bookings
        $bookings = $this->booking->getTemporaryBookingsToCancel($currentDate);
        foreach ($bookings as $booking) {
            log_message('info', 'Cancelling temporary booking ID: ' . $booking->id);
            $this->booking->cancelBooking($booking->id);
        }

        // Cancel unconfirmed permanent bookings
        $bookings = $this->booking->getPermanentBookingsToCancel($currentDate);
        foreach ($bookings as $booking) {
            log_message('info', 'Cancelling permanent booking ID: ' . $booking->id);
            $this->booking->cancelPermanentBooking($booking->id);
        }

        log_message('info', 'Update process completed successfully.');
        return $this->response->setJSON(['status' => true, 'message' => 'success']);
    }


    public function PaymentAutomate()
    {
        log_message('info', 'Payment automation process started.');

        $paymentPendingWalks = $this->booking->getPaymentPendingWalks();
        log_message('info', 'Retrieved ' . count($paymentPendingWalks) . ' payment pending walks.');

        foreach ($paymentPendingWalks as $walk) {
            log_message('info', 'Processing walk ID: ' . $walk->id);

            $pets = $this->booking->getBookingPets($walk->booking_id);

            if ($walk->status == 'completed') {
                log_message('info', 'Walk ID ' . $walk->id . ' is completed. Proceeding with payment.');

                $this->booking->updatePaymentStatus($walk->id, 'paid');
                $this->booking->updatePayment($walk->id);

                $booking      = $this->booking->getBookingforPriceCalculate($walk->booking_id, $walk->provider_id);
                $pricePerWalk = $this->calculatePricePerWalk($booking, $walk->provider_id, $pets);

                $frequency = $this->getFrequency($booking->service_frequency);
                $walks     = $frequency * $booking->duration_days;

                $platformCharges = $booking->platform_charges / $walks;
                $discount        = $booking->discount_amount / $walks;

                $providerCreditAmount = $pricePerWalk - $platformCharges - $discount;
                $clientDebitAmount    = $pricePerWalk - $discount;

                log_message('info', "Walk ID {$walk->id} - Calculated amounts: Client Debit = {$clientDebitAmount}, Provider Credit = {$providerCreditAmount}");

                $clientWallet = $this->wallet->getServiceWallet($booking->user_id, $walk->pet_id);

                if ($clientWallet->balance < $clientDebitAmount) {
                    log_message('error', "Walk ID {$walk->id} - Client has insufficient funds.");
                    return $this->response->setJSON(['status' => false, 'message' => 'Client has insufficient funds.']);
                }

                $this->wallet->debitWallet($booking->user_id, $walk->pet_id, $clientDebitAmount);
                $this->wallet->creditWallet($walk->provider_id, $providerCreditAmount);
                $this->wallet->debitSpRefundAmount($walk->provider_id, $providerCreditAmount, $walk->pet_id);

                log_message('info', "Walk ID {$walk->id} - Wallet updated successfully.");

                $this->wallet->logTransaction($booking->user_id, 'debit', $clientDebitAmount, 'Walking service amount', 'user_wallet_histories');
                $this->wallet->logTransaction($walk->provider_id, 'credit', $providerCreditAmount, 'Walking service amount', 'sp_wallet_histories');

                log_message('info', "Walk ID {$walk->id} - Transactions logged.");

                $provider = $this->provider->getProvider($walk->provider_id);
                $title    = "Walking Service";
                $message  = "Payment for today's walk has been successfully released.";

                $this->booking->createNotification([
                    'user_id'   => $walk->provider_id,
                    'user_type' => 'provider',
                    'type'      => 'walk_approved',
                    'message'   => $message,
                ]);

                log_message('info', "Walk ID {$walk->id} - Notification sent to provider.");

                $response = $this->pushNotify->notify($provider->device_token, $title, $message);
            }
        }

        log_message('info', 'Processing pending walks.');

        $pendingWalks = $this->booking->getPendingWalks();

        foreach ($pendingWalks as $pwalk) {
            $pets = $this->booking->getBookingPets($pwalk->booking_id);


            if ($pwalk->status == 'in_progress') {
                $this->booking->updateStatus($pwalk->id, 'rejected');
                $pet      = $this->booking->getPetName($pwalk->pet_id);
                $provider = $this->provider->getProvider($pwalk->provider_id);

                $booking      = $this->booking->getBookingforPriceCalculate($pwalk->booking_id, $pwalk->provider_id);
                $pricePerWalk = $this->calculatePricePerWalk($booking, $pwalk->provider_id, $pets);

                $frequency = $this->getFrequency($booking->service_frequency);
                $walks     = $frequency * $booking->duration_days;

                $platformCharges = $booking->platform_charges / $walks;
                $discount        = $booking->discount_amount / $walks;

                $clientDebitAmount = $pricePerWalk - $discount;
                $spDebitAmount     = $pricePerWalk - $platformCharges - $discount;

                $this->wallet->creditRefundWallet($booking->user_id, $clientDebitAmount);
                $this->wallet->debitRefundAmount($booking->user_id, $clientDebitAmount, $pwalk->pet_id);

                $this->wallet->debitSpRefundAmount($pwalk->provider_id, $spDebitAmount, $pwalk->pet_id);

                $this->wallet->logTransaction($booking->user_id, 'credit', $clientDebitAmount, 'Walk booking payment refund', 'user_wallet_histories');

                $this->wallet->logTransaction($pwalk->provider_id, 'debit', $spDebitAmount, 'Cancelled Walking service amount', 'sp_wallet_histories');

                $type    = 'walk_rejected';
                $title   = 'Walking Rejected';
                $message = 'The client has rejected your walk for ' . ($pet->name ?? '') . '. Payment will not proceed.';

                $this->booking->createNotification([
                    'user_id'   => $pwalk->provider_id,
                    'user_type' => 'provider',
                    'type'      => $type,
                    'message'   => $message,
                ]);
                $this->pushNotify->notify($provider->device_token, $title, $message);
            }
        }

        log_message('info', 'Processing untracked walks.');

        $untrackedWalks = $this->booking->getUntrackedWalksToday();
        foreach ($untrackedWalks as $untrackedWalk) {
            $pets        = $this->booking->getBookingPets($untrackedWalk->id);
            $bookingPets = $this->booking->getPets($untrackedWalk->id);
            foreach ($bookingPets as $pet) {
                $remainingWalksForToday = $this->booking->getRemainingWalksForToday($untrackedWalk->id, $pet->pet_id);
                $booking                = $this->booking->getBookingforPriceCalculate($untrackedWalk->id, $untrackedWalk->provider_id);

                // Ensure we process per walk
                for ($i = 0; $i < $remainingWalksForToday; $i++) {
                    $pricePerWalk = $this->calculatePricePerWalk($booking, $untrackedWalk->provider_id, $pets);

                    $frequency = $this->getFrequency($booking->service_frequency);
                    $walks     = $frequency * $booking->duration_days;

                    // Calculate platform charges and discounts per walk
                    $platformCharges = ($booking->platform_charges / $walks);
                    $discount        = ($booking->discount_amount / $walks);

                    $clientDebitAmount = $pricePerWalk - $discount;
                    $spDebitAmount     = $pricePerWalk - $platformCharges - $discount;

                    // Process refund for each individual walk
                    $this->wallet->debitWallet($booking->user_id, $pet->pet_id, $clientDebitAmount);

                    $this->wallet->creditRefundWallet($booking->user_id, $clientDebitAmount);
                    $this->wallet->debitSpRefundAmount($untrackedWalk->provider_id, $spDebitAmount, $pet->pet_id);

                    // Log transactions for each refund
                    $this->wallet->logTransaction($booking->user_id, 'credit', $clientDebitAmount, 'Untracked walk refund', 'user_wallet_histories');
                    $this->wallet->logTransaction($untrackedWalk->provider_id, 'debit', $spDebitAmount, 'Untracked walk penalty', 'sp_wallet_histories');

                    // Send notifications for each walk refund
                    $provider = $this->provider->getProvider($untrackedWalk->provider_id);
                    $title    = 'Walking Service Not Completed';
                    $message  = 'Your scheduled walk was not tracked. Refund has been issued.';

                    $this->booking->createNotification([
                        'user_id'   => $booking->user_id,
                        'user_type' => 'user',
                        'type'      => 'walk_not_tracked',
                        'message'   => $message,
                    ]);

                    $this->pushNotify->notify($provider->device_token, $title, $message);
                }
                $this->booking->markProcess($untrackedWalk->id, $untrackedWalk->provider_id, $pet->pet_id);
            }
        }

        log_message('info', 'Payment automation process completed successfully.');

        return $this->response->setJSON(['status' => true, 'message' => 'success']);
    }


    public function calculatePricePerWalk($booking, $provider_id, $pets)
    {
        $frequency_per_day = $booking->service_frequency;
        $package_price     = $booking->package_price;
        $startDate         = new DateTime($booking->service_start_date);
        $endDate           = new DateTime($booking->service_end_date);
        $endDate->modify('+1 day');
        $addons     = $this->booking->getAddonsPrice($booking->id, $provider_id);
        $addonPrice = $addons->extra_amount;

        // Ensure $addonPrice is numeric
        if (is_object($addonPrice)) {
            $addonPrice = property_exists($addonPrice, 'price') ? (float) $addonPrice->price : 0.0;
        } elseif (!is_numeric($addonPrice)) {
            $addonPrice = 0.0;
        }

        // $totalDays     = $interval->days + 1; // +1 to include the start date
        $walk_duration = ($booking->walk_duration == '30 min walk') ? 1 : 2;

        if ($frequency_per_day == 'once a day') {
            $frequency_per_day = 1;
        } elseif ($frequency_per_day == 'twice a day') {
            $frequency_per_day = 2;
        } elseif ($frequency_per_day == 'thrice a day') {
            $frequency_per_day = 3;
        }
        if ($booking->service_days == 'weekdays') {
            $period    = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
            $totalDays = 0;

            foreach ($period as $date) {
                // Check if the day is not Sunday (6 is Sunday in PHP's w format)
                if ($date->format('w') != 0) {
                    $totalDays++;
                }
            }
        } else {
            $totalDays = $startDate->diff($endDate)->days;
        }

        $pricePerWalk = (($package_price * $frequency_per_day * $walk_duration * $totalDays) + $addonPrice)
            / ($frequency_per_day * $totalDays * $pets);

        return $pricePerWalk;
    }
    private function getFrequency($serviceFrequency)
    {
        switch ($serviceFrequency) {
            case 'once a day':
                return 1;
            case 'twice a day':
                return 2;
            case 'thrice a day':
                return 3;
            default:
                return 0;
        }
    }

    public function sendAlertNotify()
    {
        log_message('info', 'Starting sendAlertNotify function.');

        $exipiringBookings = $this->booking->getExpiringBookings();
        log_message('info', 'Expiring bookings retrieved: ' . count($exipiringBookings));

        $notifiedClients = [];
        $response        = '';

        foreach ($exipiringBookings as $exp) {
            log_message('info', 'Processing booking ID: ' . $exp->id . ' for user ID: ' . $exp->user_id);

            $totalQuotes = $this->booking->getTotalQuotes($exp->id);
            log_message('info', 'Total quotes for booking ID ' . $exp->id . ': ' . count($totalQuotes));

            $message = '';

            if (count($totalQuotes) <= 0 && $exp->device_token && !in_array($exp->user_id, $notifiedClients)) {
                $token   = $exp->device_token;
                $title   = "No quotes received for your pet";
                $message = 'Please Re-book the service.';

                log_message('info', 'Sending push notification to user ID: ' . $exp->user_id);
                $PushNotify = new PushNotify();
                $response   = $PushNotify->notify($token, $title, $message);
            } else if (count($totalQuotes) > 0 && $exp->device_token && !in_array($exp->user_id, $notifiedClients)) {
                $token   = $exp->device_token;
                $title   = "Your service start date passed";
                $message = 'Please Re-book the service.';

                log_message('info', 'Sending push notification to user ID: ' . $exp->user_id);
                $PushNotify = new PushNotify();
                $response   = $PushNotify->notify($token, $title, $message);
            }

            if (!in_array($exp->user_id, $notifiedClients)) {
                $notifiedClients[] = $exp->user_id;
            }

            log_message('info', 'Creating notification entry for user ID: ' . $exp->user_id);
            $this->booking->createNotification([
                'user_id'   => $exp->user_id,
                'user_type' => 'user',
                'type'      => 'booking_updated',
                'message'   => $message,
            ]);

            log_message('info', 'Updating booking status to "Cancelled" for booking ID: ' . $exp->id);
            $this->booking->updateBookingStatus($exp->id, 'Cancelled');
        }

        log_message('info', 'sendAlertNotify function completed successfully.');

        return $this->response->setJSON(['status' => true, 'message' => "success", 'response' => $response]);
    }
}
