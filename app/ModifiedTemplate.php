<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class ModifiedTemplate extends Model
{
    // Table Name
    protected $table = 'templates_modified';
    // Primary Key
    public $primaryKey = 'mtid';
    // Timestamps
    public $timestamps = true;

    // Get template
    public function scopeGetModifiedTemplate($query, $id)
    {
      return $query->where('mtid', '=', $id);
    }

    // Get templates by user
    public function scopeGetModifiedTemplates($query, $mode, $id)
    {
      return $query->where($mode, '=', $id)->join('templates', 'templates_modified.tid', '=', 'templates.tid');
    }

    // Get templates using LIKE match
    public function scopeGetModifiedTemplatesLike($query, $mode, $id)
    {
      return $query->where($mode, 'LIKE', '%' . $id . '%')->join('templates', 'templates_modified.tid', '=', 'templates.tid');
    }

    // Get templates using FIND_IN_SET match
    public function scopeGetModifiedTemplatesFind($query, $mode, $id)
    {
      return $query->whereRaw("find_in_set('$id',$mode)")->join('templates', 'templates_modified.tid', '=', 'templates.tid');
    }

    // Get templates by name
    public function scopeSortTemplates($query)
    {
      return $query->orderBy('mtid','ASC');
    }

    // Job status
    public function scopeIdDescending($query)
    {
      return $query->orderBy('mtid','DESC');
    }

    // Incomplete jobs
    public function scopeIncompleteJobs($query)
    {
      return $query
        ->whereRaw('(job_id != "" AND job_id IS NOT NULL) AND (job_status != "COMPLETED" OR job_status IS NULL)')
        ->select('tid', 'job_id', 'job_status', 'email', 'mtid', 'publish', 'playlists', 'username', 'acct', 'acct_name');
    }

    // Get template XML
    public function getTemplate($tid) {
      $result = DB::table('templates')->select('template')->where('tid', '=', $tid)->get();
      $result = json_decode($result,1);
      $result = $result[0]['template'];
      return $result;
    }

    // Get template data
    public function getTemplateData($tid) {
      $result = DB::table('templates')->where('tid', '=', $tid)->get();
      $result = json_decode($result,1);
      $result = $result[0];
      return $result;
    }

    // Update job status
    public function updateStatus($jobId,$jobStatus) {
      DB::table('templates_modified')
        ->where('job_id', $jobId)
        ->update(['job_status' => $jobStatus['status'],'url_modified' => $jobStatus['url'],'thumbnail_modified' => $jobStatus['thumbnailUrl']]);
      return '{"success":"OK"}';
    }

}
