<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ACTIVE_ASSIGNMENT_SESSION_KEY = 'auth.active_assignment_id';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_assignments')
            ->withPivot(['id', 'operational_unit_id', 'is_active'])
            ->withTimestamps();
    }

    public function createdOperationalUnits(): HasMany
    {
        return $this->hasMany(OperationalUnit::class, 'created_by');
    }

    public function createdItems(): HasMany
    {
        return $this->hasMany(Item::class, 'created_by');
    }

    public function createdUnitItems(): HasMany
    {
        return $this->hasMany(UnitItem::class, 'created_by');
    }

    public function createdInventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class, 'created_by');
    }

    public function performedStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by');
    }

    public function createdStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'created_by');
    }

    public function respondedStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'responded_by');
    }

    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    public function paidTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'paid_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->activeRole();

        if (! $this->is_active || ! $role?->is_active) {
            return false;
        }

        if ($role->relationLoaded('permissions')) {
            return $role->permissions->contains('slug', $permission);
        }

        return $role->permissions()->where('slug', $permission)->exists();
    }

    public function activeAssignment(): ?UserRoleAssignment
    {
        $assignmentId = null;

        if (app()->bound('request') && request()->hasSession()) {
            $assignmentId = request()->session()->get(self::ACTIVE_ASSIGNMENT_SESSION_KEY);
        }

        if ($assignmentId) {
            return $this->assignments()
                ->with(['role.permissions', 'operationalUnit'])
                ->find($assignmentId);
        }

        $assignments = $this->usableAssignments();

        return $assignments->count() === 1 ? $assignments->first() : null;
    }

    public function activeRole(): ?Role
    {
        $assignment = $this->activeAssignment();

        return $assignment?->role ?? ($this->assignments()->exists() ? null : $this->role);
    }

    public function activeOperationalUnit(): ?OperationalUnit
    {
        $assignment = $this->activeAssignment();

        return $assignment?->operationalUnit ?? ($this->assignments()->exists() ? null : $this->operationalUnit);
    }

    public function activeOperationalUnitId(): ?int
    {
        $assignment = $this->activeAssignment();

        return $assignment?->operational_unit_id ?? ($this->assignments()->exists() ? null : $this->operational_unit_id);
    }

    /** @return Collection<int, UserRoleAssignment> */
    public function usableAssignments(): Collection
    {
        if (! $this->is_active) {
            return collect();
        }

        return $this->assignments()
            ->with(['role.permissions', 'operationalUnit'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserRoleAssignment $assignment): bool => $assignment->role?->is_active === true
                && ($assignment->role->slug !== 'admin'
                    || $assignment->operationalUnit?->is_active === true))
            ->values();
    }

    public function ensureLegacyAssignment(): void
    {
        if ($this->assignments()->exists() || ! $this->role_id) {
            return;
        }

        $this->assignments()->firstOrCreate([
            'role_id' => $this->role_id,
            'operational_unit_id' => $this->operational_unit_id,
        ], ['is_active' => true]);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

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
}
