<?php
// FILE: /app/Controllers/Admin/ClientController.php
// -------------------------------------------------------------------
// Admin — Clients (ગ્રાહકો). List + search, create, show (services,
// invoices, credit), edit, impersonate (super_admin only).
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BillingService;

class ClientController extends Controller
{
    /** Paginated client list with ?q= search on email / first_name. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = db()->table('clients')->where('tenant_id', $tenantId);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where('email', 'LIKE', $like)->orWhere('first_name', 'LIKE', $like);
        }
        $result = $query->orderBy('id', 'desc')->paginate($page, 15);

        // Attach a live service count to each row.
        foreach ($result['data'] as &$row) {
            $row['service_count'] = db()->table('services')
                ->where('client_id', (int) $row['id'])->count();
        }
        unset($row);

        return $this->view('admin.clients.index', [
            'clients' => $result['data'],
            'meta'    => $result,
            'q'       => $q,
        ]);
    }

    /** New-client form. */
    public function create(Request $request, string $id = ''): Response
    {
        return $this->view('admin.clients.create', []);
    }

    /** Persist a new client + its login user. */
    public function store(Request $request, string $id = ''): Response
    {
        $data = $this->validate($request, [
            'name'   => 'required|max:150',
            'email'  => 'required|email|max:191|unique:users,email',
            'mobile' => 'required|mobile',
        ]);

        $tenantId = auth()->tenantId() ?? 1;
        $name = trim((string) $data['name']);
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $first = $parts[0];
        $last = $parts[1] ?? '';
        $now = date('Y-m-d H:i:s');

        $clientId = db()->transaction(function () use ($data, $tenantId, $name, $first, $last, $now) {
            $userId = db()->table('users')->insert([
                'tenant_id'  => $tenantId,
                'role_id'    => 4, // client role
                'name'       => $name,
                'email'      => $data['email'],
                'mobile'     => $data['mobile'],
                'password'   => \App\Core\Crypt::hashPassword(random_password(14)),
                'type'       => 'client',
                'status'     => 'active',
                'language'   => (string) settings('general.default_language', 'gu'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return db()->table('clients')->insert([
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'client_code'    => 'CL-' . strtoupper(str_random(6)),
                'first_name'     => $first,
                'last_name'      => $last,
                'email'          => $data['email'],
                'phone'          => $data['mobile'],
                'status'         => 'active',
                'credit_balance' => 0,
                'country'        => 'India',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        });

        audit('client.create', 'Client', (int) $clientId, ['email' => $data['email']]);
        return redirect_route('admin/clients/' . $clientId, 'success', 'ગ્રાહક બની ગયો ✅');
    }

    /** Client detail — services, invoices, credit, impersonate + credit form. */
    public function show(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $client = db()->table('clients')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$client) {
            abort(404, 'ગ્રાહક મળ્યો નહીં');
        }

        $services = db()->table('services')
            ->leftJoin('products', 'products.id', '=', 'services.product_id')
            ->select([
                'services.id', 'services.domain', 'services.status',
                'services.recurring_amount', 'services.billing_cycle',
                'services.next_due_date', 'products.name as product_name',
            ])
            ->where('services.client_id', (int) $client['id'])
            ->orderBy('services.id', 'desc')->get();

        $invoices = db()->table('invoices')
            ->where('client_id', (int) $client['id'])
            ->orderBy('id', 'desc')->get();

        return $this->view('admin.clients.show', [
            'client'   => $client,
            'services' => $services,
            'invoices' => $invoices,
        ]);
    }

    /** Update client fields OR add account credit (same route). */
    public function update(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $client = db()->table('clients')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$client) {
            abort(404, 'ગ્રાહક મળ્યો નહીં');
        }
        $now = date('Y-m-d H:i:s');

        // --- Credit-add branch ---
        if ($request->filled('add_credit')) {
            $amount = round((float) $request->input('add_credit'), 2);
            if ($amount <= 0) {
                return back_with('error', 'ક્રેડિટ રકમ ધન હોવી જોઈએ');
            }
            db()->transaction(function () use ($client, $amount, $request, $now) {
                db()->table('credits')->insert([
                    'tenant_id'   => $client['tenant_id'],
                    'client_id'   => $client['id'],
                    'amount'      => $amount,
                    'type'        => 'add',
                    'description' => trim((string) $request->input('credit_note', 'Manual credit')),
                    'admin_id'    => auth()->id(),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                db()->table('clients')->where('id', (int) $client['id'])->update([
                    'credit_balance' => round((float) $client['credit_balance'] + $amount, 2),
                    'updated_at'     => $now,
                ]);
            });
            audit('client.credit_add', 'Client', (int) $client['id'], ['amount' => $amount]);
            return back_with('success', money($amount) . ' ક્રેડિટ ઉમેરાઈ ✅');
        }

        // --- Field-edit branch ---
        $data = $this->validate($request, [
            'first_name' => 'required|max:100',
            'last_name'  => 'nullable|max:100',
            'email'      => 'required|email|max:191',
            'phone'      => 'nullable|max:20',
            'company'    => 'nullable|max:200',
            'gstin'      => 'nullable|max:20',
            'state_code' => 'nullable|max:5',
            'status'     => 'required|in:active,inactive,suspended,closed',
        ]);

        db()->table('clients')->where('id', (int) $client['id'])->update([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'] ?? null,
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'company'    => $data['company'] ?? null,
            'gstin'      => $data['gstin'] ?? null,
            'state_code' => $data['state_code'] ?? null,
            'status'     => $data['status'],
            'updated_at' => $now,
        ]);
        audit('client.update', 'Client', (int) $client['id']);
        return back_with('success', 'ગ્રાહક અપડેટ થયો ✅');
    }

    /** Log in as this client (super_admin only). */
    public function impersonate(Request $request, string $id = ''): Response
    {
        $this->authorize(auth()->is('super_admin'), 403, 'ફક્ત super admin');

        $tenantId = auth()->tenantId() ?? 1;
        $client = db()->table('clients')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$client || empty($client['user_id'])) {
            return back_with('error', 'આ ગ્રાહકનું login એકાઉન્ટ મળ્યું નહીં');
        }

        audit('impersonate', 'Client', (int) $client['id'], ['user_id' => (int) $client['user_id']]);
        auth()->loginUsingId((int) $client['user_id']);
        return $this->redirect(url('client'));
    }
}
