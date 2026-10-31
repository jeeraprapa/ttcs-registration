<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Support\RegistrationDomains;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RegistrationUrlViewTest extends TestCase
{
    public function test_url_filter_renders_when_cached_config_and_view_variable_are_missing_or_null(): void
    {
        config(['registration.registration_urls' => null]);
        $this->actingAs(new AdminUser(['name' => 'Test Admin']), 'admin');
        $data = [
            'registrations' => new LengthAwarePaginator([], 0, 20),
            'sessions' => collect(), 'filters' => [], 'referralSources' => [],
            'errors' => new ViewErrorBag,
        ];
        foreach ([$data, [...$data, 'registrationUrls' => null]] as $viewData) {
            $view = $this->view('admin.registrations.index', $viewData)->assertSee('All registration URLs');
            foreach (RegistrationDomains::REGISTRATION_URLS as $url) {
                $view->assertSee($url);
            }
        }
    }
}
