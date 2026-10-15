<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $event
 * @property string $target_type
 * @property int|null $target_id
 * @property string $result
 * @property string|null $ip_address
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property User|null $user
 */
#[Fillable(['user_id', 'event', 'target_type', 'target_id', 'result', 'ip_address', 'metadata'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param array{reason?: string, number?: int, justification?: string, outcome?: string, route?: string, previous_role?: string, role?: string, previous_active?: bool, is_active?: bool, quantity?: int} $metadata */
    public static function record(string $event, string $targetType, ?int $targetId = null, string $result = 'sucesso', array $metadata = [], bool $system = false): self
    {
        return self::create([
            'user_id' => $system ? null : auth()->id(), 'event' => $event,
            'target_type' => $targetType, 'target_id' => $targetId, 'result' => $result,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(), 'metadata' => $metadata,
        ]);
    }
}
