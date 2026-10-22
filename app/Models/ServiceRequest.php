<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $table = 'requests';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_date' => 'datetime', 'completed_date' => 'datetime'];
    }

    public function submission()
    {
        return $this->hasOne(Submission::class, 'request_id');
    }

    public function signature()
    {
        return $this->hasOne(FormSignature::class, 'request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'badge_id');
    }
}
