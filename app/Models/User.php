<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Este é o lado "Um-para-Muitos". Através desse método, você poderá chamar `$user->maintenances` e o framework vai gerar o SQL `SELECT * FROM maintenances WHERE user_id = ?`.
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
