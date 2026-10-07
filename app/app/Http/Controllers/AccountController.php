<?php

namespace App\Http\Controllers;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $staff = StaffProfile::with(['person', 'person.user.roles'])->where('status', 'active')->orderBy('id')->get();
        $parents = ParentProfile::with(['person', 'person.user.roles'])->where('status', 'active')->orderBy('id')->get();

        return view('accounts.index', compact('staff', 'parents'));
    }

    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate(['profile_type' => ['required', 'in:teacher,parent'], 'profile_id' => ['required', 'integer']]);
        $profile = $data['profile_type'] === 'teacher'
            ? StaffProfile::with('person.user')->where('status', 'active')->findOrFail($data['profile_id'])
            : ParentProfile::with('person.user')->where('status', 'active')->findOrFail($data['profile_id']);
        abort_if($profile->person?->user, 409, 'This profile already has a portal login.');
        $email = $profile->email ?? null;
        abort_unless($email, 422, 'Add an email address to this profile before creating its login.');
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['profile_id' => 'A portal account already uses this email. Update the profile email or resolve the duplicate before creating another login.']);
        }
        $role = Role::where('key', $data['profile_type'])->firstOrFail();
        $temporaryPassword = Str::password(20);
        $person = $profile->person;

        $user = DB::transaction(function () use ($profile, $person, $email, $temporaryPassword, $role, $request): User {
            $user = User::create([
                'name' => trim(collect([$person->first_name, $person->middle_name, $person->last_name])->filter()->join(' ')),
                'email' => $email,
                'password' => $temporaryPassword,
                'account_status' => 'active',
                'must_change_password' => true,
            ]);
            $person->user()->save($user);
            $user->roles()->attach($role->id, ['granted_by' => $request->user()->id, 'granted_at' => now()]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id, 'action' => 'account.created', 'subject_type' => User::class,
                'subject_id' => $user->id, 'after_values' => json_encode(['profile_type' => $role->key, 'person_id' => $person->id, 'must_change_password' => true]),
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now(),
            ]);

            return $user;
        });

        return redirect()->route('accounts.index')->with('new_login', ['name' => $user->name, 'email' => $email, 'password' => $temporaryPassword]);
    }

    public function changePasswordForm(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ]);
        $request->user()->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return redirect()->route('dashboard')->with('status', 'Password changed. You can now use the portal.');
    }
}
