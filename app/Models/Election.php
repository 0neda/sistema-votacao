<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ElectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int $created_by
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['title', 'description', 'created_by', 'completed_at'])]
class Election extends Model
{
    /** @use HasFactory<ElectionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('display_order');
    }

    /** @return HasMany<Round, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('number');
    }
}
