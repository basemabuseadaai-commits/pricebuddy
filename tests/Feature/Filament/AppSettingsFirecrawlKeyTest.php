<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AppSettingsPage;
use App\Models\User;
use App\Services\FirecrawlService;
use App\Services\Helpers\SettingsHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

class AppSettingsFirecrawlKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SettingsHelper::setSetting('integrated_services', []);
        $this->flush();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function flush(): void
    {
        SettingsHelper::$settings = null;
        Cache::flush();
        Once::flush();
    }

    private function storedKey(): ?string
    {
        $this->flush();

        return data_get(FirecrawlService::settings(), 'api_key');
    }

    public function test_key_is_stored_encrypted(): void
    {
        Livewire::test(AppSettingsPage::class)
            ->fillForm([
                'integrated_services.firecrawl.enabled' => true,
                'integrated_services.firecrawl.api_key' => 'fc-secret-1',
                'integrated_services.firecrawl.proxy' => 'auto',
                'integrated_services.firecrawl.daily_limit' => 20,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $this->storedKey();
        $this->assertNotSame('fc-secret-1', $stored);
        $this->assertSame('fc-secret-1', Crypt::decryptString($stored));
        $this->assertTrue(FirecrawlService::new()->isEnabled());
    }

    public function test_saved_keys_are_never_sent_to_the_browser(): void
    {
        $firecrawlCipher = Crypt::encryptString('fc-secret-2');
        $aiCipher = Crypt::encryptString('gemini-secret');
        SettingsHelper::setSetting('integrated_services', [
            'firecrawl' => ['enabled' => true, 'api_key' => $firecrawlCipher, 'proxy' => 'auto', 'daily_limit' => 50],
            'ai' => ['enabled' => true, 'default_provider_id' => 'p1', 'providers' => [
                ['id' => 'p1', 'name' => 'Gemini', 'type' => 'gemini', 'model' => 'm', 'api_key' => $aiCipher,
                    'timeout_seconds' => 60, 'max_tokens' => 2000, 'temperature' => 0.2],
            ]],
        ]);
        $this->flush();

        $page = Livewire::test(AppSettingsPage::class)
            ->assertFormSet(['integrated_services.firecrawl.api_key' => null]);

        $html = $page->html();
        foreach (['fc-secret-2', $firecrawlCipher, 'gemini-secret', $aiCipher] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_blank_key_keeps_the_saved_key(): void
    {
        SettingsHelper::setSetting('integrated_services', [
            'firecrawl' => ['enabled' => true, 'api_key' => Crypt::encryptString('fc-keep'), 'proxy' => 'auto', 'daily_limit' => 50],
        ]);
        $this->flush();

        Livewire::test(AppSettingsPage::class)
            ->fillForm([
                'integrated_services.firecrawl.enabled' => true,
                'integrated_services.firecrawl.api_key' => '',
                'integrated_services.firecrawl.daily_limit' => 10,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('fc-keep', Crypt::decryptString($this->storedKey()));
    }

    public function test_saving_twice_keeps_the_ai_key(): void
    {
        SettingsHelper::setSetting('integrated_services', [
            'ai' => ['enabled' => true, 'default_provider_id' => 'p1', 'providers' => [
                ['id' => 'p1', 'name' => 'Gemini', 'type' => 'gemini', 'model' => 'm', 'api_key' => Crypt::encryptString('gemini-keep'),
                    'timeout_seconds' => 60, 'max_tokens' => 2000, 'temperature' => 0.2],
            ]],
        ]);
        $this->flush();

        // Save without touching anything: the AI key field is blank in the form.
        Livewire::test(AppSettingsPage::class)->call('save')->assertHasNoFormErrors();

        $this->flush();
        $stored = data_get(SettingsHelper::getSetting('integrated_services'), 'ai.providers.0.api_key');
        $this->assertSame('gemini-keep', Crypt::decryptString($stored));
    }
}
