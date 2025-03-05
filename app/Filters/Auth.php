<?php

namespace App\Filters;

use App\Models\User;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class Auth implements FilterInterface
{

    public function before(RequestInterface $request, $arguments = null)
    {
        //
        $token = $request->getHeaderLine('Authorization');

        // If token is not provided or invalid format
        if (!$token || !preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            return Services::response()
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['status' => 401, 'success'=>'fail', 'message' => 'Unauthorized access - Invalid token format']);
        }

        $token = $matches[1];

        $userModel = new User();
        $user = $userModel->getToken($token);
        if (!$user) {
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
