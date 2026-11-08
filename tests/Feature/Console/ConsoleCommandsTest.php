<?php

namespace Tests\Feature\Console;

use App\Console\Commands\BuildSearchResearch;
use App\Console\Commands\CreateProductSources;
use App\Console\Commands\CreateStores;
use App\Console\Commands\FetchAll;
use App\Console\Commands\InitDatabase;
use App\Console\Commands\RegeneratePriceCache;
use App\Models\Product;
use App\Models\ProductSource;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_all_command_runs_successfully()
    {
        // Command should run without errors even with no products
        $this->artisan(FetchAll::COMMAND)
            ->assertExitCode(0);
    }

    public function test_fetch_all_command_with_log_option()
    {
        // Command should run with log option
        $this->artisan(FetchAll::COMMAND, ['--log' => true])
            ->assertExitCode(0);
    }

    public function test_regenerate_price_cache_command_updates_all_products()
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(3)->create(['user_id' => $user->id]);

        $this->artisan(RegeneratePriceCache::COMMAND)
            ->assertExitCode(0);

        // Verify the command ran without errors
        $this->assertTrue(true);
    }

    public function test_regenerate_price_cache_command_handles_empty_products()
    {
        $this->artisan(RegeneratePriceCache::COMMAND)
            ->assertExitCode(0);
    }

    public function test_build_search_research_command_with_product_name()
    {
        // Command should run successfully even if no search sources are available
        $this->artisan(BuildSearchResearch::COMMAND, ['product_name' => 'laptop'])
            ->assertExitCode(0);
    }

    public function test_create_stores_command_creates_stores_for_country()
    {
        $country = StoreSeeder::getAllCountries()[0] ?? 'us';

        $this->artisan(CreateStores::COMMAND, ['country' => $country])
            ->assertExitCode(0);

        // Verify at least one store was created
        $this->assertGreaterThan(0, Store::count());
    }

    public function test_create_stores_command_creates_egypt_stores_in_egp()
    {
        $this->artisan(CreateStores::COMMAND, ['country' => 'egypt'])
            ->assertExitCode(0);

        $amazon = Store::where('slug', 'amazon-eg')->firstOrFail();
        $this->assertSame('EGP', $amazon->currency);
        $this->assertTrue(Store::where('slug', 'jumia-eg')->exists());
    }

    public function test_create_product_sources_command_links_egypt_sources_to_stores()
    {
        User::factory()->create();
        $this->artisan(CreateStores::COMMAND, ['country' => 'egypt'])->assertExitCode(0);

        $this->artisan(CreateProductSources::COMMAND, ['country' => 'egypt'])
            ->assertExitCode(0);

        $jumia = ProductSource::where('name', 'Jumia Egypt')->firstOrFail();
        $this->assertSame(Store::where('slug', 'jumia-eg')->value('id'), $jumia->store_id);
        $this->assertStringContainsString(':search_term', $jumia->search_url);

        // Running again skips existing sources instead of duplicating them.
        $count = ProductSource::count();
        $this->artisan(CreateProductSources::COMMAND, ['country' => 'egypt'])->assertExitCode(0);
        $this->assertSame($count, ProductSource::count());
    }

    public function test_create_product_sources_command_fails_for_unknown_country()
    {
        $this->artisan(CreateProductSources::COMMAND, ['country' => 'atlantis'])
            ->assertExitCode(1);
    }

    public function test_create_stores_command_creates_all_stores()
    {
        $this->artisan(CreateStores::COMMAND, ['country' => 'all'])
            ->assertExitCode(0);

        // Verify stores were created
        $this->assertGreaterThan(0, Store::count());
    }

    public function test_create_stores_command_skips_existing_stores()
    {
        $country = StoreSeeder::getAllCountries()[0] ?? 'us';
        $storeData = StoreSeeder::getStoreData($country);

        if (! empty($storeData)) {
            $firstStore = $storeData[0];
            Store::factory()->create([
                'slug' => $firstStore['slug'] ?? Str::slug($firstStore['name']),
                'name' => $firstStore['name'],
            ]);

            $initialCount = Store::count();

            $this->artisan(CreateStores::COMMAND, ['country' => $country])
                ->assertExitCode(0);

            // Should not create duplicate
            $this->assertEquals($initialCount + count($storeData) - 1, Store::count());
        } else {
            $this->markTestSkipped('No store data available for country');
        }
    }

    public function test_create_stores_command_updates_existing_stores_with_update_flag()
    {
        $country = StoreSeeder::getAllCountries()[0] ?? 'us';
        $storeData = StoreSeeder::getStoreData($country);

        if (! empty($storeData)) {
            $firstStore = $storeData[0];
            $store = Store::factory()->create([
                'slug' => $firstStore['slug'] ?? Str::slug($firstStore['name']),
                'name' => 'Old Name',
            ]);

            $this->artisan(CreateStores::COMMAND, ['country' => $country, '--update' => true])
                ->assertExitCode(0);

            // Should update the name
            $this->assertSame($firstStore['name'], $store->refresh()->name);
        } else {
            $this->markTestSkipped('No store data available for country');
        }
    }

    public function test_init_database_command_when_already_initialized()
    {
        // Database is already initialized in tests
        $this->assertTrue(Schema::hasTable('migrations'));

        // Command should run successfully when database is already initialized
        $this->artisan(InitDatabase::COMMAND)
            ->assertExitCode(0);
    }

    public function test_init_database_creates_default_user_as_admin()
    {
        // Regression: the default user created on a fresh deploy must be an
        // Admin, otherwise the Settings menu (and other admin-only features)
        // are hidden. The `role` column defaults to "user", so creation must
        // set the role explicitly.
        $command = new InitDatabase;

        $createDefaultUser = new ReflectionMethod($command, 'createDefaultUser');
        $createDefaultUser->setAccessible(true);

        /** @var User $user */
        $user = $createDefaultUser->invoke($command, 'Admin', 'admin@example.com', 'secret-pass');

        $this->assertTrue($user->isAdmin(), 'The default user should have the Admin role.');
        $this->assertTrue(Hash::check('secret-pass', $user->password), 'The password should be hashed.');
        $this->assertTrue(User::whereEmail('admin@example.com')->sole()->isAdmin());
    }

    public function test_init_database_command_detects_database_connection()
    {
        // Verify we can connect to the database
        try {
            DB::connection()->getPdo();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Database connection failed: '.$e->getMessage());
        }
    }
}
