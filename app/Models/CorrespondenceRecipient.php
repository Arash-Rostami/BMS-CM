<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CorrespondenceRecipient extends Pivot
{
    protected $table = 'correspondence_recipients';

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
