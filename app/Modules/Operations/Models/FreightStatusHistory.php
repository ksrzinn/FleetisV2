<?php

namespace App\Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightStatusHistory extends Model
{
    protected $table = 'freight_status_history';

    public $timestamps = false;

    protected $fillable = [
        'freight_id', 'from_status', 'to_status', 'user_id', 'notes', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    /** @return BelongsTo<Freight, $this> */
    public function freight(): BelongsTo
    {
        return $this->belongsTo(Freight::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
