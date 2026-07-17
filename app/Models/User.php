<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'phone',
    'role',
    'status',
    'slug',
    'specialization',
    'experience',
    'rating',
    'bio',
    'focus_areas',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'pincode',
    'phone_verified_at',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'rating' => 'decimal:1',
            'focus_areas' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole('admin') || $this->role === 'admin',
            'doctor' => $this->hasRole('doctor') || $this->role === 'doctor',
            default => false,
        };
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function assignedConsultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'doctor_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function customerMealPlans(): HasMany
    {
        return $this->hasMany(CustomerMealPlan::class);
    }

    public function cart(): HasOne { return $this->hasOne(Cart::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
