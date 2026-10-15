<?php

use App\Actions\Voting;
use App\Models\Round;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'votacao_test') {
    exit(2);
}
$actor = User::findOrFail((int) $argv[2]);
auth()->setUser($actor);
$round = Round::findOrFail((int) $argv[3]);
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
DB::select('SELECT pg_advisory_lock_shared(731030)');
DB::select('SELECT pg_advisory_unlock_shared(731030)');
try {
    if ($argv[1] === 'close') {
        app(Voting::class)->closeRound($actor, $round);
    } else {
        app(Voting::class)->vote($actor, $round, (int) $argv[4]);
    }
    fwrite(STDOUT, "accepted\n");
} catch (ValidationException $exception) {
    fwrite(STDOUT, "rejected\n");
}
