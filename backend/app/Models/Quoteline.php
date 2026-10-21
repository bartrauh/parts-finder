<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quoteline extends Model
{
    /** @use HasFactory<\Database\Factories\QuotelineFactory> */
    use HasFactory;

    protected $fillable = ['part_id', 'quantity', 'colour'];

    protected function casts(): array
    {
        return [
            'backorder' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }

    public function part(): BelongsTo {
        return $this->belongsTo(Part::class);
    }
}
