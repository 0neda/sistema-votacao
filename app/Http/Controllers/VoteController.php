<?php

namespace App\Http\Controllers;

use App\Actions\Voting;
use App\Http\Requests\StoreVoteRequest;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;

class VoteController extends Controller
{
    public function store(StoreVoteRequest $request, Round $round, Voting $voting): RedirectResponse
    {
        $voting->vote($request->user(), $round, $request->integer('candidate_id'));

        return to_route('home')->with('success', 'Seu voto foi registrado com sucesso.');
    }
}
