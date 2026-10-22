<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingAttendee extends Model
{
    protected $table = 'meeting_users';

    public $timestamps = false;

    protected $guarded = ['id'];
}
