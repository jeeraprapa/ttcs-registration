<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EventSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SessionController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-admin');

        return view('admin.sessions.index', ['sessions' => EventSession::withCount('registrations')->withSum('registrations', 'guest_count')->orderBy('local_date')->orderBy('local_start_time')->get()]);
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
