<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RegistrationUrlViewTest extends TestCase
{
    public function test_registration_filters_use_month_day_and_time_instead_of_url_or_session_id(): void
    {
        $this->actingAs(new AdminUser(['name' => 'Test Admin']), 'admin');
        $this->view('admin.registrations.index', [
            'registrations' => new LengthAwarePaginator([], 0, 20),
            'dates' => collect(), 'months' => collect(), 'times' => collect(),
            'filters' => [], 'referralSources' => [], 'errors' => new ViewErrorBag,
        ])->assertSee('All months')->assertSee('All days')->assertSee('All time sessions')
            ->assertDontSee('name="registration_url"', false)->assertDontSee('name="session_id"', false);
    }
}
