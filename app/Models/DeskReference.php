<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeskReference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'group_key', 'version', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
