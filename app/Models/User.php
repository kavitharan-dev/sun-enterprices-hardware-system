<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canManageStore(): bool
    {
        return $this->hasAnyRole(['admin', 'store_manager']);
    }

    public function canHandleSales(): bool
    {
        return $this->hasAnyRole(['admin', 'store_manager', 'cashier']);
    }

    public function canManageDailyAccounts(): bool
    {
        return $this->hasAnyRole(['admin', 'store_manager', 'cashier']);
    }

    public function canViewConstruction(): bool
    {
        return $this->hasAnyRole(['admin', 'site_manager']);
    }

    public function canManageProjects(): bool
    {
        return $this->hasRole('admin');
    }

    public function canManageWorkers(): bool
    {
        return $this->hasAnyRole(['admin', 'site_manager']);
    }

    public function canCreateMaterialRequests(): bool
    {
        return $this->hasAnyRole(['admin', 'site_manager']);
    }

    public function canReviewMaterialRequests(): bool
    {
        return $this->hasAnyRole(['admin', 'store_manager']);
    }

    public function canIssueMaterials(): bool
    {
        return $this->canReviewMaterialRequests();
    }

    public function canManageUsers(): bool
    {
        return $this->hasRole('admin');
    }

    public function canViewStoreReports(): bool
    {
        return $this->hasAnyRole(['admin', 'store_manager']);
    }

    public function canViewConstructionReports(): bool
    {
        return $this->hasAnyRole(['admin', 'site_manager']);
    }

    public function canViewReports(): bool
    {
        return $this->canViewStoreReports() || $this->canViewConstructionReports();
    }

    public function assignedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'site_manager_id');
    }
}
