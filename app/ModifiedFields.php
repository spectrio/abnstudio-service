<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ModifiedFields extends Model
{
  // Table Name
  protected $table = 'form_fields_modified';
  // Primary Key
  public $primaryKey = 'mfid';
  // Timestamps
  public $timestamps = true;

  public function scopeGetModifiedFields($query, $id)
  {
    return $query->where('mtid', '=', $id);
  }
}
