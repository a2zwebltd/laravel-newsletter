<?php

namespace A2ZWeb\Newsletter\Tests;

use A2ZWeb\Newsletter\Concerns\ReceivesMailings;
use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanReceiveMailing
{
    use HasFactory;
    use Notifiable;
    use ReceivesMailings;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'marketing' => 'boolean',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
