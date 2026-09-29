<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;

class StatusSubscriber extends Model
{
    protected $fillable = [
        'email',
        'verification_token',
        'unsubscribe_token',
        'verified_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
