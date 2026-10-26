<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function index(): View
    {
        $admins = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('name')
            ->paginate(15);

        return view('admin.accounts.index', compact('admins'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $admin = new User;
        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->password = Hash::make($data['password']);
        $admin->role = User::ROLE_ADMIN;
        $admin->save();

        return redirect()->route('admin.accounts.index')->with('status', 'Akun admin berhasil ditambahkan.');
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        $account->name = $data['name'];
        $account->email = $data['email'];
        if (($data['password'] ?? '') !== '') {
            $account->password = Hash::make($data['password']);
        }
        $account->save();

        return redirect()->route('admin.accounts.index')->with('status', 'Akun admin berhasil diperbarui.');
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 404);

        if ($account->is($request->user('web'))) {
            return back()->withErrors(['account' => 'Kamu tidak dapat menghapus akun admin yang sedang digunakan.']);
        }

        $adminCount = User::query()->where('role', User::ROLE_ADMIN)->count();
        if ($adminCount <= 1) {
            return back()->withErrors(['account' => 'Akun admin terakhir tidak dapat dihapus.']);
        }

        $account->delete();

        return redirect()->route('admin.accounts.index')->with('status', 'Akun admin berhasil dihapus beserta riwayat chat miliknya.');
    }
}
