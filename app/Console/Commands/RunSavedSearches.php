<?php

namespace App\Console\Commands;

use App\Jobs\RunSavedSearchJob;
use App\Models\SavedSearch;
use App\Services\SavedSearchRunner;
use Illuminate\Console\Command;

class RunSavedSearches extends Command
{
    const COMMAND = 'buddy:run-saved-searches';

    protected $signature = self::COMMAND.' {--id=* : Run these saved searches even if not due} {--sync : Run now instead of queueing}';

    protected $description = 'Run saved searches that are due, adding the cheapest matching results to their products';

    public function handle(SavedSearchRunner $runner): int
    {
        $ids = array_filter((array) $this->option('id'));

        $searches = $ids
            ? SavedSearch::query()->whereIn('id', $ids)->get()
            : SavedSearch::query()->due()->get();

        foreach ($searches as $search) {
            if ($this->option('sync')) {
                $this->line('Running: '.$search->productTitle());
                $this->line(json_encode($runner->run($search), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                RunSavedSearchJob::dispatch($search->getKey());
                $this->line('Queued: '.$search->productTitle());
            }
        }

        return self::SUCCESS;
    }
}
