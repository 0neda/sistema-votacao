<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $election_id
 * @property string $name
 * @property string $description
 * @property int $display_order
 * @property string|null $photo_path
 */
#[Fillable(['election_id', 'name', 'description', 'display_order', 'photo_path'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
