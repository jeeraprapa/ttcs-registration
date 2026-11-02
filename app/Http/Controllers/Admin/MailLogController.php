<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfirmationEmail;
use App\Support\MailDeliveryLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MailLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-admin');
        $filters = $request->validate([
            'recipient' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'type' => ['nullable', Rule::in(['confirmation', 'test'])],
            'status' => ['nullable', Rule::in(array_keys(MailDeliveryLogger::STATUSES))],
        ]);
        $query = DB::table('mail_delivery_logs as logs')
            ->leftJoin('confirmation_emails as confirmations', 'confirmations.id', '=', 'logs.confirmation_email_id')
            ->leftJoin('registrations', 'registrations.id', '=', 'confirmations.registration_id')
            ->select('logs.*', 'registrations.id as registration_id', 'registrations.reference', 'confirmations.status as confirmation_status', 'confirmations.next_attempt_at');
        foreach ($filters as $key => $value) {
            if (filled($value)) {
                $query->where('logs.'.$key, $value);
            }
        }

        return view('admin.mail-logs', [
            'logs' => $query->orderByDesc('logs.id')->paginate(20)->withQueryString(),
            'filters' => $filters, 'statuses' => MailDeliveryLogger::STATUSES,
            'pendingCount' => ConfirmationEmail::where('status', 'pending')->count(),
        ]);
    }
}
