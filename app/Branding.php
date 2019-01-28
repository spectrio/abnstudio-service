<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Branding extends Model
{
  // Table Name
  protected $table = 'branding';
  // Primary Key
  public $primaryKey = 'id';
  // Timestamps
  public $timestamps = true;

  public function scopeGetAllBrands($query)
  {
    return $query->orderBy('oem','DESC');
  }

  public function scopeGetBranding($query, $oem)
  {
    $oem = strtoupper($oem);
    return $query->where('oem', '=', $oem);
  }

  public function scopeGetBrandingById($query, $id)
  {
    return $query->where('id', '=', $id);
  }
}
