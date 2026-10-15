<?php

namespace App\Http\Requests;

use App\Models\Election;

class StoreRoundRequest extends StoreElectionRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeSchedule();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $election = $this->route('election');
        abort_unless($election instanceof Election, 404);
        $previous = $election->rounds()->reorder()->orderByDesc('number')->first();
        $manual = $previous && $previous->number >= 2;

        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'candidate_ids' => $manual ? ['required', 'array', 'min:2', 'max:3'] : ['prohibited'],
            'candidate_ids.*' => ['integer', 'distinct'],
            'selection_justification' => $manual ? ['required', 'string', 'max:500'] : ['prohibited'],
        ];
    }
}
