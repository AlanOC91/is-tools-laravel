<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\DirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, DirectoryService $directory)
    {
        $data = $request->validate(['username' => 'required|string|max:100', 'password' => 'required|string|max:1024', 'remember' => 'nullable|boolean']);
        try {
            $entry = $directory->authenticate($data['username'], $data['password']);
        } catch (RuntimeException $e) {
            report($e);

            return response()->view('auth.login', ['directoryError' => 'Directory sign-in is unavailable. Please contact I.S. Support.'], 503);
        }
        if (! $entry || empty($entry['samaccountname'][0])) {
            throw ValidationException::withMessages(['username' => 'The account name or password is incorrect.']);
        }
        $badge = strtolower($entry['samaccountname'][0]);
        $user = DB::transaction(function () use ($entry, $badge, $directory) {
            $user = User::whereRaw('LOWER(badge_id) = ?', [$badge])->first();
            $user ??= new User(['badge_id' => $badge, 'username' => $badge]);
            $user->display_name = $entry['displayname'][0] ?? $entry['cn'][0] ?? $badge;
            $email = $directory->email($entry);
            $user->email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
            $user->save();
            $names = [];
            for ($i = 0; $i < ($entry['memberof']['count'] ?? 0); $i++) {
                $dn = ldap_explode_dn($entry['memberof'][$i], 1);
                if ($dn && isset($dn[0])) {
                    $names[] = $dn[0];
                }
            }
            // Refresh directory permissions at login so revoked AD membership is respected.
            DB::table('security_groups_users')->where('user_id', $user->badge_id)->delete();
            foreach (array_unique($names) as $name) {
                $group = DB::table('security_groups')->where('name', $name)->value('id');
                $group ??= DB::table('security_groups')->insertGetId(['name' => $name]);
                DB::table('security_groups_users')->insert(['user_id' => $user->badge_id, 'group_id' => $group]);
            }
            DB::table('login_log')->insert(['badge_id' => $user->badge_id, 'time' => now()]);

            return $user;
        });
        Auth::guard()->setRememberDuration(60 * 24 * 30);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        Audit::record('login', $user->badge_id);
        if ($request->expectsJson()) {
            return response()->json(['success' => 'Signed in.', 'url' => route('home')]);
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request)
    {
        Audit::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
