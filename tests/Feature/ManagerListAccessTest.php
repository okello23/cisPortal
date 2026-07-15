<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerListAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_only_sees_allowed_manager_lists_on_dashboard(): void
    {
        $supervisor = User::query()->where('email', 'supervisor@cphl.go.ug')->firstOrFail();
        $supervisor->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $response = $this->actingAs($supervisor)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('A-LIS Key Update Reasons');
        $response->assertSee('Systems');
        $response->assertSee('Modules');
        $response->assertSee('Issue Types');
        $response->assertSee('Priority/Impact Levels');
        $response->assertSee('Ticket Statuses');
        $response->assertSee('Resolution Categories');
        $response->assertSee('Closure Reasons');
        $response->assertDontSee('Regions');
        $response->assertDontSee('Facilities');
        $response->assertDontSee('Departments');
        $response->assertDontSee('Designations');
    }

    public function test_supervisor_cannot_open_restricted_manager_list(): void
    {
        $supervisor = User::query()->where('email', 'supervisor@cphl.go.ug')->firstOrFail();
        $supervisor->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $response = $this->actingAs($supervisor)->get(route('lists.index', 'facilities'));

        $response->assertForbidden();
    }
}
