<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return response()->view('admin.login')->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:255']]);
        if (! Auth::guard('admin')->attempt(['email' => strtolower(trim($data['email'])), 'password' => $data['password'], 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }
        $request->session()->regenerate();
        $request->session()->put('admin_password_version', Auth::guard('admin')->user()->password_version);
        $intended = $request->session()->pull('url.intended');
        $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;
        if (is_string($path) && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $query = parse_url($intended, PHP_URL_QUERY);

            return redirect($path.($query ? '?'.$query : ''));
        }

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
