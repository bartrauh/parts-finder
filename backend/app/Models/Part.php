<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colours' => 'array',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['in_stock'];

    protected function inStock(): Attribute {
        return new Attribute(
            get: fn ($value, $attributes) => ($attributes->stock ?? 0) < 1
        );
    }
}
