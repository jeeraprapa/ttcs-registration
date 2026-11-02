<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RegistrationsExport;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EventSession;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters] = $this->filteredQuery($request);

        $dates = EventSession::select('local_date')->distinct()->orderBy('local_date')->get()->pluck('local_date');

        return view('admin.registrations.index', [
            'registrations' => $query->latest('id')->paginate(20)->withQueryString(),
            'dates' => $dates, 'months' => $dates->unique(fn ($date) => $date->format('Y-m'))->values(),
            'times' => EventSession::select('local_start_time')->distinct()->orderBy('local_start_time')->pluck('local_start_time'),
            'filters' => $filters, 'referralSources' => config('registration.referral_sources'),
        ]);
    }

    public function export(Request $request)
    {
        [$query] = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query) {
            app(RegistrationsExport::class)->write($query);
        }, 'registrations-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function filteredQuery(Request $request): array
    {
        Gate::authorize('manage-admin');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'month' => ['nullable', 'date_format:Y-m'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('registered_from'), ['after_or_equal:registered_from'])],
            'referral_source' => ['nullable', Rule::in(config('registration.referral_sources'))],
            'attendance' => ['nullable', Rule::in(['booked', 'checked_in', 'cancelled'])],
        ]);
        $query = Registration::with('eventSession');
        if (($filters['attendance'] ?? '') === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        } elseif (($filters['attendance'] ?? '') === 'checked_in') {
            $query->whereNull('cancelled_at')->whereNotNull('checked_in_at');
        } elseif (($filters['attendance'] ?? '') === 'booked') {
            $query->whereNull('cancelled_at')->whereNull('checked_in_at');
        }
        if (! empty($filters['month'])) {
            $month = Carbon::parse($filters['month'].'-01');
            $query->whereBetween('session_date_snapshot', [$month->format('Y-m-d'), $month->copy()->endOfMonth()->format('Y-m-d')]);
        }
        if (! empty($filters['day'])) {
            $query->where('session_date_snapshot', $filters['day']);
        }
        if (! empty($filters['time'])) {
            $query->where('session_time_snapshot', $filters['time'].':00');
        }
        if (! empty($filters['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->where(function ($q) use ($term) {
                $q->whereRaw("email LIKE ? ESCAPE '!'", [$term.'%'])->orWhereRaw("first_name LIKE ? ESCAPE '!'", ['%'.$term.'%'])->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%'.$term.'%'])->orWhere('reference', $term);
            });
        }
        if (! empty($filters['registered_from'])) {
            $start = Carbon::parse($filters['registered_from'], config('registration.timezone'))->startOfDay()->utc();
            $query->where('created_at', '>=', $start->format('Y-m-d H:i:s'));
        }
        if (! empty($filters['registered_to'])) {
            $end = Carbon::parse($filters['registered_to'], config('registration.timezone'))->startOfDay()->addDay()->utc();
            $query->where('created_at', '<', $end->format('Y-m-d H:i:s'));
        }
        if (! empty($filters['referral_source'])) {
            $query->where('referral_source', $filters['referral_source']);
        }

        return [$query, $filters];
    }

    public function cancel(Request $request, Registration $registration): RedirectResponse
    {
        Gate::authorize('manage-admin');
        DB::transaction(function () use ($request, $registration): void {
            $session = EventSession::whereKey($registration->event_session_id)->lockForUpdate()->firstOrFail();
            $booking = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($booking->cancelled_at) {
                return;
            }
            $booking->update(['cancelled_at' => now(), 'cancelled_by' => $request->user('admin')->id, 'active_session_id' => null]);
            $session->update(['reserved_seats' => max(0, $session->reserved_seats - ($booking->seat_count ?? 0))]);
            $booking->confirmationEmails()->whereIn('status', ['pending', 'processing'])->update(['status' => 'cancelled', 'processing_token' => null, 'lease_until' => null]);
            AdminAuditLog::create(['admin_user_id' => $request->user('admin')->id, 'action' => 'registration.cancel',
                'subject_type' => 'registration', 'subject_id' => $booking->id, 'metadata' => [], 'created_at' => now()]);
        });

        return back()->with('status', 'Booking cancelled. The session is available again unless manually closed.');
    }

    public function show(Registration $registration)
    {
        Gate::authorize('manage-admin');
        $registration->load(['eventSession', 'consents.definition', 'confirmationEmails']);

        return view('admin.registrations.show', compact('registration'));
    }
}
