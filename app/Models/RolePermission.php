<?php

namespace App\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
  use HasFactory;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['name', 'permissions', 'export_fields'];

  protected $casts = ['export_fields' => 'array'];

  public function adminInfo()
  {
    return $this->hasMany(Admin::class, 'role_id', 'id');
  }
}
