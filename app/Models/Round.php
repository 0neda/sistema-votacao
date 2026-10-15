<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $election_id
 * @property int $number
 * @property string $status
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $selection_justification
 */
#[Fillable(['election_id', 'number', 'status', 'scheduled_at', 'opened_at', 'closed_at', 'selection_justification'])]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return BelongsToMany<Candidate, $this> */
    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'round_candidates')->orderBy('display_order');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
