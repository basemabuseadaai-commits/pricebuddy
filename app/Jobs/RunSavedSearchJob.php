<?php

namespace App\Jobs;

use App\Models\SavedSearch;
use App\Services\SavedSearchRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RunSavedSearchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public $tries = 1;

    /** Shops read with a browser take about 10 seconds a page. */
    public $timeout = 1200;

    public int $uniqueFor = 1800;

    public function __construct(public int $savedSearchId) {}

    public function uniqueId(): string
    {
        return (string) $this->savedSearchId;
    }

    public function handle(SavedSearchRunner $runner): void
    {
        $search = SavedSearch::find($this->savedSearchId);

        if ($search) {
            $runner->run($search);
        }
    }
}
