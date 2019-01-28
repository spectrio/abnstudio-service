<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Template extends Model
{
    // Table Name
    protected $table = 'templates';
    // Primary Key
    public $primaryKey = 'tid';
    // Timestamps
    public $timestamps = true;

    // Get template
    public function scopeGetTemplate($query, $id)
    {
      return $query->where('tid', '=', $id);
    }

    // Get templates by OEM
    public function scopeGetTemplatesByOem($query, $oem)
    {
      return $query
        ->where('oems', 'LIKE', '%'.$oem.'%')
        ->where('visible', '=', '1')
        ->orderBy('name','ASC');
    }

    // Get templates by name
    public function scopeSortTemplates($query)
    {
      return $query->orderBy('name','ASC');
    }

    // Job status
    public function scopeIdDescending($query)
    {
      return $query->orderBy('tid','DESC');
    }

    // Get incomplete jobs
    public function scopeIncompleteJobs($query)
    {
      return $query
        ->whereRaw('(job_id != "" AND job_id IS NOT NULL) AND (job_status != "COMPLETED" OR job_status IS NULL)')
        //->where('job_id', '!=', '')->whereNotNull('job_id')
        //->where('job_status', '!=', 'COMPLETED')->orWhereNull('job_status')
        ->select('tid', 'job_id', 'job_status', 'email');
    }

    // Update job status
    public function updateStatus($jobId,$jobStatus) {
      DB::table('templates')
        ->where('job_id', $jobId)
        ->update(['job_status' => $jobStatus['status'],'url' => $jobStatus['url'],'thumbnail' => $jobStatus['thumbnailUrl']]);
      return '{"success":"OK"}';
    }
}
