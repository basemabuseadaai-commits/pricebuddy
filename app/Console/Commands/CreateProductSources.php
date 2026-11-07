<?php

namespace App\Console\Commands;

use App\Models\ProductSource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateProductSources extends Command
{
    const COMMAND = 'buddy:create-product-sources';

    protected $signature = self::COMMAND.' {country : The country file in database/seeders/ProductSources}'.
        ' {--update : If a product source exists with the same name, update it to match defaults}';

    protected $description = 'Create product sources (store search pages) for a country';

    public function handle(): int
    {
        $file = database_path('seeders/ProductSources/'.$this->argument('country').'.php');

        if (! file_exists($file)) {
            $this->error('Country file not found: '.$file);

            return self::FAILURE;
        }

        $userId = User::query()->oldest()->first()?->getKey();

        if (! $userId) {
            $this->error('Create a user first.');

            return self::FAILURE;
        }

        foreach (require $file as $data) {
            $storeSlug = Arr::pull($data, 'store');
            $store = $storeSlug ? Store::where('slug', $storeSlug)->first() : null;

            if ($storeSlug && ! $store) {
                $this->warn('Skipping - '.$data['name'].' (store "'.$storeSlug.'" not found, run buddy:create-stores first)');

                continue;
            }

            $data['store_id'] = $store?->getKey();
            $data['user_id'] = $userId;

            $existing = ProductSource::where('slug', Str::slug($data['name']))->first();

            if ($existing && ! $this->option('update')) {
                $this->info('Skipping - '.$data['name']);
            } elseif ($existing) {
                $this->components->task('Updating - '.$data['name'], fn () => $existing->update($data));
            } else {
                $this->components->task('Creating - '.$data['name'], fn () => ProductSource::create($data));
            }
        }

        return self::SUCCESS;
    }
}
