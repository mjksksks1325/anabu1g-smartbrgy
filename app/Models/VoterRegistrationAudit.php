<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoterRegistrationAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'voter_registration_id', 'actor_id', 'action', 'changes',
        'ip_hash', 'user_agent_hash',
    ];

    protected $hidden = ['changes', 'ip_hash', 'user_agent_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'encrypted:array',
            'created_at' => 'datetime',
        ];
    }
}
