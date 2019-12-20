<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class RenderLog extends Model
{
    // Table Name
    protected $table = 'renders';
    // Primary Key
    public $primaryKey = 'rid';
    // Timestamps
    public $timestamps = false;

    // Get template
    public function scopeGetRender($query, $id)
    {
      return $query->where('rid', '=', $id);
    }

    // Get renders by mtid
    public function scopeGetRendersByMtid($query, $mtid)
    {
      return $query
        ->where('mtid', '=', $mtid)
        ->orderBy('mtid','ASC');
    }

    // Get sorted renders
    public function scopeSortRenders($query)
    {
      return $query->orderBy('mtid','ASC');
    }

    // Job status
    public function scopeIdDescending($query)
    {
      return $query->orderBy('rid','DESC');
    }

    // Update job status
    public function updateRender($jobId, $jobStatus) {
      $row = DB::table('renders')->where('job', $jobId)->get()->toJson();
      $row = json_decode($row,1);
      if(isset($row[0])) {
        $row = $row[0];
        //print_r($row);die();

        $start = strtotime(str_replace('-','/',$row['start_time']));
        $now = strtotime('now');
        $now_date = date('Y-m-d H:i:s',$now);
        $total = ($now - $start);

        //$jobId = '2019_11_25_68460a43-f61a-4107-a073-16dfe7cc5748';

        DB::table('renders')
          ->where('job', $jobId)
          ->update(['end_time' => $now_date,'total_time' => $total,'status' => $jobStatus['status']]);
          //->update(['status' => 'COMPLETED']);
      }
      return '{"success":"OK"}';
    }
}
