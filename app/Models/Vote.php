<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $round_id
 * @property int $candidate_id
 * @property bool $is_simulated
 */
#[Fillable(['id', 'round_id', 'candidate_id', 'is_simulated'])]
class Vote extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (Vote $vote): void {
            $vote->id ??= bin2hex(random_bytes(32));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_simulated' => 'boolean'];
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
