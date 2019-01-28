<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FormFields extends Model
{
  // Table Name
  protected $table = 'form_fields';
  // Primary Key
  public $primaryKey = 'fid';
  // Timestamps
  public $timestamps = true;

  public function scopeGetFormFields($query, $id)
  {
    return $query->where('tid', '=', $id);
  }
}
