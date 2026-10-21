<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = ['totals', 'status'];
    protected function casts(): array
    {
        return [
            'totals' => 'array',
        ];
    }

    public function lines(): HasMany {
        return $this->hasMany(Quoteline::class);
    }
}
