<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $round_id
 * @property int $user_id
 * @property User $user
 */
#[Fillable(['round_id', 'user_id'])]
class Participation extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
