<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class LabCredential extends Authenticatable implements JWTSubject
{
    use Notifiable, SoftDeletes;

    protected $fillable = ['user_id', 'lab_id', 'username', 'password', 'expires_at', 'role', 'is_active'];

    protected $hidden = ['password'];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }
}
