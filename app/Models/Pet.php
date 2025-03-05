<?php

namespace App\Models;

use CodeIgniter\Model;

class Pet extends Model
{
  protected $db;

  protected $pets;
  public function __construct()
  {
    parent::__construct();
    $this->db = \Config\Database::connect();
    $this->pets = 'user_pets';
  }
  public function getPets($user_id)
  {
    $query = $this->db->table($this->pets)
      ->select('*')
      ->where('user_id', $user_id)
      ->orderBy('id', 'DESC')
      ->get();
    return $query->getResult();
  }
  public function getPet($pet_id)
  {
    $query = $this->db->table($this->pets)
      ->select('*')
      ->where('id', $pet_id)
      ->get();
    return $query->getRow();
  }
  public function create($data)
  {
    $query = $this->db->table($this->pets)->insert($data);
    return $query;
  }
  public function updatePet($data)
  {
    return $builder = $this->db->table($this->pets)
      ->set('user_id', $data->user_id)
      ->set('name', $data->name)
      ->set('type', $data->type)
      ->set('breed', $data->breed)
      ->set('dob', $data->dob)
      ->set('gender', $data->gender)
      ->set('image', $data->image)
      ->set('aggressiveness_level', $data->aggressiveness_level)
      ->set('last_vaccination', $data->last_vaccination)
      ->set('dewormed_on', $data->dewormed_on)
      ->set('insured', $data->insured)
      ->set('vaccinated', $data->vaccinated)
      ->set('last_vet_visit', $data->last_vet_visit)
      ->set('visit_purpose', $data->visit_purpose)
      ->set('vet_name', $data->vet_name)
      ->set('vet_phone', $data->vet_phone)
      // ->set('vet_address', $data->vet_address)
      ->set('vaccinated_rabies', $data->vaccinated_rabies)
      ->set('last_rabies_vaccination', $data->last_rabies_vaccination)
      ->set('licensed', $data->licensed)
      ->set('updated_at', $data->updated_at)
      ->where('id', $data->pet_id)
      ->update();
  }
  public function deletePet($pet_id)
  {
    return $this->db->table($this->pets)->delete(['id' => $pet_id]);
  }
  public function checkBookingPet($pet_id)
  {
    return $this->db->table('booking_pets')
      ->select('booking_pets.id')
      ->join('walking_service_bookings', 'walking_service_bookings.id=booking_pets.booking_id', 'left')
      ->where('booking_pets.pet_id', $pet_id)
      ->whereIn('walking_service_bookings.status', ['New,Confirmed,Cancelled,Completed,onHold'])
      ->get()
      ->getRow();
  }
}
