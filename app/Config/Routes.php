<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Home::index');
$routes->get('phpInfo', 'Home::phpInfo');


// users

$routes->group('api/user', static function ($routes) {

  //login
  $routes->post('login', 'api\AuthController::index');
  $routes->post('validateOtp', 'api\AuthController::validateOtp');

  //dashboard
  $routes->get('(:num)/dashboard', 'api\UserController::index/$1');
  //profile
  $routes->get('(:num)/profile', 'api\UserController::profile/$1');
  $routes->post('updateProfile', 'api\UserController::updateProfile');

  //active jobs
  $routes->get('(:num)/bookings', 'api\UserController::userBookings/$1');

  // requested jobs
  $routes->get('(:num)/requestBookings', 'api\UserController::userNewBookings/$1');

  //quotations
  $routes->get('(:num)/quotations', 'api\UserController::quotations/$1');
  $routes->post('quotations', 'api\QuotationController::getQuotations');

  $routes->get('completedWalks/(:any)', 'api\UserController::completedWalks/$1');

  // pets
  $routes->get('(:num)/pets', 'api\PetController::index/$1');
  $routes->post('addPet', 'api\PetController::create');
  $routes->get('pet/(:num)', 'api\PetController::show/$1');
  $routes->post('pet', 'api\PetController::update');
  $routes->delete('pet/(:num)', 'api\PetController::delete/$1');


  // address
  $routes->get('(:num)/address', 'api\UserController::address/$1');
  $routes->post('addAddress', 'api\UserController::addAddress');
  $routes->get('address/(:num)', 'api\UserController::show/$1');
  $routes->post('address', 'api\UserController::updateAddress');
  $routes->delete('(:num)/address/(:num)', 'api\UserController::delete/$1/$2');

  //wallet
  $routes->get('(:num)/wallet', 'api\WalletController::index/$1');
  $routes->post('addMoney', 'api\WalletController::addMoney');

  // bookings
  $routes->get('bookingView', 'api\BookingController::show');
  $routes->post('createBooking/(:any)', 'api\BookingController::createBooking/$1');
  $routes->post('deleteBooking/(:any)', 'api\BookingController::delete/$1');
  $routes->post('updateTimings/(:any)', 'api\BookingController::updateTimings/$1');
  $routes->post('updateAddress/(:any)', 'api\BookingController::updateAddress/$1');
  $routes->post('bookingSummary', 'api\BookingController::bookingSummary');
  $routes->post('confirmBooking', 'api\BookingController::confirmBooking');
  $routes->post('activeService', 'api\BookingController::activeService');
  $routes->post('walkApproval', 'api\BookingController::walkApproval');


  // provider profile
  $routes->post('providerProfile', 'api\ProviderController::ratingsPhotos');

  // filter
  $routes->post('filter', 'api\QuotationController::filter');

  // notifications
  $routes->post('(:num)/sendNotification', 'api\UserController::sendNotification/$1');
  $routes->get('(:num)/notifications', 'api\UserController::notifications/$1');

  $routes->post('serviceHistory', 'api\BookingController::serviceHistory');
  $routes->post('extendService', 'api\BookingController::extendService');
  $routes->post('directExtend', 'api\BookingController::directExtend');
  $routes->post('cancelService', 'api\BookingController::cancelService');
  $routes->post('reportWalker', 'api\BookingController::reportWalker');
  $routes->post('hireProvider', 'api\BookingController::hireProvider');
  $routes->post('repostBooking', 'api\BookingController::repostBooking');


  // reviews
  $routes->post('createReview', 'api\UserController::reviewToProvider');

  $routes->post('initiatePayment', 'api\BookingController::initiatePayment');
  $routes->get('checkPaymentStatus/(:any)', 'api\PaymentController::checkPaymentStatus/$1');
});


// service providers

$routes->group('api/provider', static function ($routes) {

  //login
  $routes->post('login', 'api\AuthController::providerLogin');
  $routes->post('validateOtp', 'api\AuthController::providerValidateOtp');

  //profile
  $routes->get('(:num)/profile', 'api\ProviderController::index/$1');
  $routes->post('updateProfile', 'api\ProviderController::updateProfile');
  $routes->post('sendOtpToMail', 'api\ProviderController::sendOtpToMail');
  $routes->post('verifyOtpFromMail', 'api\ProviderController::verifyOtpFromMail');
  $routes->post('uploadCertificate', 'api\ProviderController::uploadCertificate');

  // bank
  $routes->get('(:num)/bank', 'api\ProviderController::bank/$1');
  $routes->post('addBank', 'api\ProviderController::addBank');

  //wallet
  $routes->get('(:num)/wallet', 'api\WalletController::ProviderWallet/$1');

  //quotations
  $routes->post('createQotation', 'api\QuotationController::create');
  $routes->post('updateQotation', 'api\QuotationController::update');
  $routes->post('deleteQuotation', 'api\QuotationController::delete');

  // service details & history
  $routes->post('addServiceDetails', 'api\ProviderController::addServiceDetails');
  $routes->get('(:num)/myQuotes/(:num)', 'api\ProviderController::myQuotes/$1/$2');
  $routes->get('(:num)/activeJobs/(:num)', 'api\ProviderController::activeJobs/$1/$2');
  $routes->get('(:num)/newJobs/(:num)', 'api\ProviderController::newJobs/$1/$2');
  $routes->get('(:num)/myWalks/(:num)', 'api\ProviderController::myWalks/$1/$2');

  //service uploads
  $routes->post('upload', 'api\ProviderController::upload');
  $routes->get('serviceImages/(:num)', 'api\ProviderController::serviceImages/$1');
  $routes->post('deleteImage', 'api\ProviderController::deleteImage');

  $routes->post('getBookingProviders', 'api\ProviderController::getBookingProviders');

  //extend & tracking
  $routes->post('manageWalk', 'api\ProviderController::manageWalk');
  $routes->get('extendRequest/(:num)', 'api\ProviderController::extendRequest/$1');
  $routes->post('manageRequest', 'api\ProviderController::manageRequest');
  $routes->post('manageRide', 'api\ProviderController::manageRide');
  $routes->post('startGrooming', 'api\ProviderController::startGrooming');

  //withdrawl
  $routes->post('withdrawAmount', 'api\ProviderController::withdrawAmount');
  $routes->post('initiateTransfer', 'api\PaymentController::initiateTransfer');

  $routes->get('(:num)/notifications', 'api\ProviderController::notifications/$1');
  $routes->get('(:num)/banks', 'api\ProviderController::banks/$1');
  $routes->post('terminateGrooming', 'api\ProviderController::terminateGrooming');

});

// common
$routes->group('api', static function ($routes) {

  //services
  $routes->get('services', 'api\ServiceController::index');

  //breeds
  $routes->get('breeds', 'api\ServiceController::breeds');

  //packages
  $routes->get('packages', 'api\ServiceController::packages');
  $routes->get('packages/(:num)', 'api\ServiceController::Servicepackages/$1');
  $routes->get('addons/(:num)', 'api\ServiceController::serviceAddons/$1');
  
  //quotations
  $routes->get('quotations', 'api\QuotationController::quotations');

  //truncate
  $routes->post('truncate', 'Home::truncateTables');

  //payments
  // $routes->post('payment/callback', 'api\BookingController::callback');
  $routes->post('payment/callback', 'api\PaymentController::phonePecallBack');

  // $routes->post('sendNotification', 'PushNotifications::sendNotification');
  $routes->get('offers', 'api\ServiceController::offers');

  // aadhaar service
  $routes->post('aadharVerify', 'api\ServiceController::aadharVerify');
  $routes->post('aadharOTPVerify', 'api\ServiceController::aadharOTPVerify');

  //bank
  $routes->post('BankVerify', 'api\ServiceController::BankVerify');

  //crons

  $routes->get('updateBookings', 'CronJobs::update');
  $routes->get('PaymentAutomate', 'CronJobs::PaymentAutomate');
  $routes->get('sendAlertNotify', 'CronJobs::sendAlertNotify');
  // $routes->get('sendUnquotedBookings', 'CronJobs::sendUnquotedBookings');
  $routes->get('updateBooking', 'CronJobs::updateBooking');



  //supprot
  $routes->post('support', 'api\ServiceController::support');

  //events
  $routes->get('events', 'api\ServiceController::events');

  $routes->post('requestAadhaarOTP', 'api\ServiceController::requestAadhaarOTP');


  $routes->post('manageGroomingTracking', 'api\ProviderController::manageAndApproveGroomingTracking');


});




$routes->get('weblogin', 'web\BookingController::index');

return $routes;
