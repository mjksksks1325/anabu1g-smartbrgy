<?php

namespace App\Models;

use Database\Factories\PurokFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property bool $is_active
 */
class Purok extends Model
{
    /** @use HasFactory<PurokFactory> */
    use HasFactory;

    protected $fillable = ['name', 'color', 'is_active'];

    /** @return HasMany<Resident, $this> */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class, 'purok', 'name');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
