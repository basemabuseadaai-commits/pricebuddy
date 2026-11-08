<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StoreResource\Pages\EditStore;
use App\Filament\Resources\StoreResource\Pages\ListStores;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stores are shared. Any user can see them; only an admin can create, edit or
 * delete one, because store settings change price reading for everyone.
 */
class StorePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_can_view_but_only_an_admin_can_create_or_edit(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('viewAny', Store::class));
        $this->assertTrue($user->can('view', $store));
        $this->assertFalse($user->can('create', Store::class));
        $this->assertFalse($user->can('update', $store));
        $this->assertTrue($admin->can('create', Store::class));
        $this->assertTrue($admin->can('update', $store));
    }

    public function test_a_non_admin_cannot_open_the_store_create_or_edit_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create();

        $this->get('/admin/stores/create')->assertForbidden();
        $this->get("/admin/stores/{$store->id}/edit")->assertForbidden();
    }

    public function test_only_an_admin_can_delete_a_store(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->can('delete', $store));
        $this->assertFalse($user->can('deleteAny', Store::class));
        $this->assertTrue($admin->can('delete', $store));
        $this->assertTrue($admin->can('deleteAny', Store::class));
    }

    public function test_edit_page_shows_delete_to_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $store = Store::factory()->create();

        Livewire::test(EditStore::class, ['record' => $store->getKey()])
            ->assertActionVisible(DeleteAction::class);
    }

    public function test_list_page_hides_bulk_delete_from_a_non_admin(): void
    {
        $this->actingAs(User::factory()->create());
        Store::factory()->create();

        Livewire::test(ListStores::class)
            ->assertTableBulkActionHidden(DeleteBulkAction::class);
    }
}
