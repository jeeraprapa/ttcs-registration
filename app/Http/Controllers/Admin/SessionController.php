<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EventSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-admin');
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);
        $dates = EventSession::select('local_date')->distinct()->orderBy('local_date')->get()->pluck('local_date');
        $times = EventSession::select('local_start_time')->distinct()->orderBy('local_start_time')->pluck('local_start_time');
        $query = EventSession::withCount(['registrations' => fn ($query) => $query->whereNull('cancelled_at')])
            ->withSum(['registrations' => fn ($query) => $query->whereNull('cancelled_at')], 'guest_count');
        if (! empty($filters['month'])) {
            $month = Carbon::parse($filters['month'].'-01');
            $query->whereBetween('local_date', [$month->format('Y-m-d'), $month->copy()->endOfMonth()->format('Y-m-d')]);
        }
        if (! empty($filters['day'])) {
            $query->where('local_date', $filters['day']);
        }
        if (! empty($filters['time'])) {
            $query->where('local_start_time', $filters['time'].':00');
        }

        return view('admin.sessions.index', [
            'sessions' => $query->orderBy('local_date')->orderBy('local_start_time')->get(),
            'filters' => $filters, 'dates' => $dates, 'times' => $times,
            'months' => $dates->unique(fn ($date) => $date->format('Y-m'))->values(),
        ]);
    }

    public function edit(EventSession $session)
    {
        Gate::authorize('manage-admin');

        return view('admin.sessions.edit', ['eventSession' => $session, 'capacityEnabled' => false]);
    }

    public function update(Request $request, EventSession $session)
    {
        Gate::authorize('manage-admin');
        $data = $request->validate(['is_open' => ['required', 'boolean'], 'capacity' => ['prohibited']]);
        DB::transaction(function () use ($request, $session, $data) {
            $locked = EventSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $before = $locked->is_open;
            $locked->update(['is_open' => (bool) $data['is_open']]);
            AdminAuditLog::create(['admin_user_id' => $request->user('admin')->id, 'action' => 'session.update',
                'subject_type' => 'event_session', 'subject_id' => $locked->id, 'metadata' => ['before' => ['is_open' => $before], 'after' => ['is_open' => $locked->is_open]], 'created_at' => now()]);
        });

        return redirect()->route('admin.sessions.index')->with('status', 'Session availability updated.');
    }
}
