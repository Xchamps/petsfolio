<?php

namespace App\Filters;

use App\Models\Provider;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class SpAuth implements FilterInterface
{

  public function before(RequestInterface $request, $arguments = null)
  {
    //
    $token = $request->getHeaderLine('Authorization');

    // If token is not provided or invalid format
    if (!$token || !preg_match('/Bearer\s(\S+)/', $token, $matches)) {
      return Services::response()
        ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
        ->setJSON(['status' => 401, 'success'=>'fail','message' => 'Unauthorized access - Invalid token']);
      }

    $token = $matches[1];

    $providerModel = new Provider();
    $provider = $providerModel->getToken($token);
    if (!$provider) {
      return Services::response()
        ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
        ->setJSON(['status' => 401, 'success'=>'fail','message' => 'Unauthorized access - Invalid token']);
      }
  }


  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
  {
    //
  }
}
