<?php

namespace App\Models;

use App\Models\RolePermission;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model implements AuthenticatableContract
{
  use HasFactory, Authenticatable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'role_id',
    'first_name',
    'last_name',
    'image',
    'username',
    'email',
    'phone',
    'address',
    'details',
    'password',
    'status'
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = ['password'];

  protected $casts = [
    'two_factor_enabled_at' => 'datetime',
  ];

  public function role()
  {
    return $this->belongsTo(RolePermission::class, 'role_id', 'id');
  }

  public function hasPermission(string ...$permissions): bool
  {
    if (is_null($this->role_id)) {
      return true;
    }

    $rolePermissions = json_decode((string) $this->role?->permissions, true);
    if (!is_array($rolePermissions)) {
      return false;
    }

    return collect($permissions)->contains(
      fn ($permission) => in_array($permission, $rolePermissions, true)
    );
  }
}
