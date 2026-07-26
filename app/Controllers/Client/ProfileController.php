<?php
// FILE: /app/Controllers/Client/ProfileController.php
// -------------------------------------------------------------------
// MODULE 10 — Client profile. Name/email/mobile/password update +
// 2FA note. users અને clients બંને rows sync થાય.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\Request;
use App\Core\Response;

class ProfileController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $user   = current_user() ?? [];
        $client = db()->table('clients')->where('user_id', auth()->id())->first() ?? [];

        return $this->view('client.profile.index', [
            'user'   => $user,
            'client' => $client,
        ]);
    }

    public function update(Request $request, string $id = ''): Response
    {
        $userId = (int) auth()->id();

        $data = $this->validate($request, [
            'name'   => 'required|min:2',
            'email'  => 'required|email',
            'mobile' => 'nullable|max:20',
        ]);

        $mobile = trim((string) ($request->input('mobile') ?? ''));

        $userUpdate = [
            'name'       => (string) $data['name'],
            'email'      => (string) $data['email'],
            'mobile'     => $mobile !== '' ? $mobile : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Optional password change — hash only when provided.
        if ($request->filled('password')) {
            $this->validate($request, ['password' => 'min:8']);
            $userUpdate['password'] = Crypt::hashPassword((string) $request->input('password'));
        }

        db()->table('users')->where('id', $userId)->update($userUpdate);

        // Keep the clients row in sync.
        $client = db()->table('clients')->where('user_id', $userId)->first();
        if ($client) {
            $parts = preg_split('/\s+/', trim((string) $data['name']), 2) ?: [];
            db()->table('clients')->where('id', (int) $client['id'])->update([
                'first_name' => $parts[0] ?? (string) $data['name'],
                'last_name'  => $parts[1] ?? '',
                'email'      => (string) $data['email'],
                'phone'      => $mobile !== '' ? $mobile : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        audit('client.profile.update', 'user', $userId);

        return back_with('success', 'પ્રોફાઇલ અપડેટ થઈ.');
    }
}
