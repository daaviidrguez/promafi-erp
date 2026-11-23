<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** @var list<string>|null */
    protected ?array $permissionKeysCache = null;

    public function hasPermission(string $key): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('key', $key);
        }

        if ($this->permissionKeysCache === null) {
            $this->permissionKeysCache = $this->permissions()->pluck('key')->all();
        }

        return in_array($key, $this->permissionKeysCache, true);
    }
}
