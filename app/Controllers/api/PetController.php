<?php

namespace App\Controllers\api;

use App\Controllers\BaseController;
use App\Helpers\CommonHelper;
use App\Models\Pet;

class PetController extends BaseController
{
  protected $pet;
  public function __construct()
  {
    $this->pet = new Pet();
  }
  public function index($user_id)
  {
    $data = $this->pet->getPets($user_id);
    foreach ($data as &$pet) {
      $pet->age   = CommonHelper::ageCalculator($pet->dob);
      $pet->image = base_url() . 'public/uploads/pets/' . $pet->image;
      unset($pet->created_at);
      unset($pet->updated_at);

      if ($pet->last_vaccination || $pet->last_rabies_vaccination || $pet->dewormed_on || $pet->last_vet_visit) {
        if ($pet->last_vaccination == '0000-00-00') {
          $pet->last_vaccination = null;
        }
        if ($pet->last_rabies_vaccination == '0000-00-00') {
          $pet->last_rabies_vaccination = null;
        }
        if ($pet->dewormed_on == '0000-00-00') {
          $pet->dewormed_on = null;
        }
        if ($pet->last_vet_visit == '0000-00-00') {
          $pet->last_vet_visit = null;
        }
      }
    }
    return $this->response->setStatusCode(200)->setJSON(['status' => true, 'data' => $data]);
  }
  public function show($pet_id)
  {
    $data = $this->pet->getPet($pet_id);
    if ($data) {
      $data->age = CommonHelper::ageCalculator($data->dob);
      if ($data->image) {
        $data->image = base_url() . 'public/uploads/pets/' . $data->image;
      }
      if ($data->last_vaccination || $data->last_rabies_vaccination || $data->dewormed_on || $data->last_vet_visit) {
        if ($data->last_vaccination == '0000-00-00') {
          $data->last_vaccination = null;
        }
        if ($data->last_rabies_vaccination == '0000-00-00') {
          $data->last_rabies_vaccination = null;
        }
        if ($data->dewormed_on == '0000-00-00') {
          $data->dewormed_on = null;
        }
        if ($data->last_vet_visit == '0000-00-00') {
          $data->last_vet_visit = null;
        }
      }

      unset($data->created_at);
      unset($data->updated_at);
      return $this->response->setStatusCode(200)->setJSON([
        'status' => true,
        'data'   => $data
      ]);
    } else {
      return $this->response->setStatusCode(404)->setJSON([
        'status'  => false,
        'message' => 'Pet Not Found'
      ]);
    }
  }

  public function create()
  {

    $rules = [
      'type'                 => 'required',
      'name'                 => 'required',
      'breed'                => 'required',
      'aggressiveness_level' => 'required|in_list[normal,slightly,high]',
      'user_id'              => 'required|numeric',
      'gender'               => 'required',
      'image'                => 'required',
      'vaccinated'           => 'required|in_list[yes,no]',
      'vaccinated_rabies'    => 'required|in_list[yes,no]',
      // 'dewormed_on'          => 'required|valid_date[Y-m-d]',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(400)->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }

    $json             = $this->request->getJSON();
    $json->created_at = gmdate('Y-m-d H:i:s');

    if ($json->image) {
      $file     = base64_decode($json->image);
      $fileData = [
        'file'        => $file,
        'upload_path' => ROOTPATH . 'public/uploads/pets',
      ];

      $json->image = CommonHelper::upload($fileData);
    }
    $data = $this->pet->create($json);
    if ($data) {
      return $this->response->setStatusCode(201)->setJSON(['status' => true, 'message' => 'Pet Created Successfully']);
    } else {
      return $this->response->setStatusCode(500)->setJSON(['status' => false, 'message' => 'Failed to create pet']);
    }
  }

  public function update()
  {
    $rules = [
      'type'                 => 'required',
      'name'                 => 'required',
      'breed'                => 'required',
      'aggressiveness_level' => 'required|in_list[normal,slightly,high]',
      'user_id'              => 'required|numeric',
      'gender'               => 'required',
      // 'image'                => 'required',
      'vaccinated'           => 'required|in_list[yes,no]',
      'vaccinated_rabies'    => 'required|in_list[yes,no]',
      // 'dewormed_on'          => 'required|valid_date[Y-m-d]',
      'pet_id'               => 'required|numeric',
    ];

    if (!$this->validate($rules)) {
      return $this->response->setStatusCode(400)->setJSON(['status' => false, 'errors' => $this->validator->getErrors()]);
    }
    $json             = $this->request->getJSON();
    $json->updated_at = gmdate('Y-m-d H:i:s');
    if ($json->image) {
      $file        = base64_decode($json->image);
      $fileData    = [
        'file'        => $file,
        'upload_path' => ROOTPATH . 'public/uploads/pets',
      ];
      $json->image = CommonHelper::upload($fileData);
    } else {
      $currentPet  = $this->pet->getPet($json->pet_id);
      $json->image = $currentPet->image;
    }
    $data = $this->pet->updatePet($json);
    if ($data) {
      return $this->response->setStatusCode(201)->setJSON(['status' => true, 'message' => 'Pet Update Successfully']);
    } else {
      return $this->response->setStatusCode(500)->setJSON(['status' => false, 'message' => 'Failed to update pet']);
    }
  }
  public function delete($pet_id)
  {
    $check = $this->pet->checkBookingPet($pet_id);
    if ($check) {
      return $this->response->setStatusCode(500)->setJSON([
        'status'  => false,
        'message' => 'Not allowed to delete pet because it is booked',
      ]);
    }
    $data = $this->pet->deletePet($pet_id);
    if ($data) {
      return $this->response->setStatusCode(201)->setJSON([
        'status'  => true,
        'message' => 'Pet Delete Successfully'
      ]);
    } else {
      return $this->response->setStatusCode(500)->setJSON([
        'status'  => false,
        'message' => 'Failed to delete pet'
      ]);
    }
  }
}
