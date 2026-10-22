<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    protected $table = 'verified_logins';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['remember_token'];

    public function getNameAttribute(): string
    {
        return html_entity_decode($this->display_name ?? $this->username, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function groups(): array
    {
        return DB::table('security_groups_users as membership')
            ->join('security_groups as groups', 'groups.id', '=', 'membership.group_id')
            ->where('membership.user_id', $this->badge_id)->pluck('groups.name')->all();
    }

    public function isAdministrator(): bool
    {
        return in_array('I.S. Staff', $this->groups(), true);
    }
}
