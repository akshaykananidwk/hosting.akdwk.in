<?php
// FILE: /app/Controllers/Client/TicketController.php
// -------------------------------------------------------------------
// MODULE 12 — Support Tickets (Client side).
// A client can only view/open their own tickets (client_id must match, else 403).
// New ticket, thread and replies. Public replies trigger WhatsApp.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsAppService;

class TicketController extends Controller
{
    /** Allowed priority values (matches the `tickets.priority` ENUM). */
    protected const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * The `clients` row for the logged-in user (or null).
     */
    protected function currentClient(): ?array
    {
        return db()->table('clients')->where('user_id', auth()->id())->first();
    }

    /**
     * This client's tickets + the "new ticket" form (departments list).
     */
    public function index(Request $request, string $id = ''): Response
    {
        $client = $this->currentClient();
        $this->authorize($client !== null);

        $tenantId = auth()->tenantId() ?? 1;

        $tickets = db()->table('tickets')
            ->leftJoin('ticket_departments', 'tickets.department_id', '=', 'ticket_departments.id')
            ->where('tickets.tenant_id', $tenantId)
            ->where('tickets.client_id', (int) $client['id'])
            ->select([
                'tickets.id as id',
                'tickets.ticket_number as ticket_number',
                'tickets.subject as subject',
                'tickets.priority as priority',
                'tickets.status as status',
                'tickets.last_reply_at as last_reply_at',
                'tickets.created_at as created_at',
                'ticket_departments.name as department_name',
            ])
            ->orderBy('tickets.updated_at', 'DESC')
            ->get();

        $departments = db()->table('ticket_departments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        return $this->view('client.tickets.index', [
            'tickets'     => $tickets,
            'departments' => $departments,
            'priorities'  => self::PRIORITIES,
            'client'      => $client,
        ]);
    }

    /**
     * Create a new ticket + its opening message; notify on WhatsApp.
     */
    public function store(Request $request, string $id = ''): Response
    {
        $client = $this->currentClient();
        $this->authorize($client !== null);

        $data = $this->validate($request, [
            'department_id' => 'required',
            'subject'       => 'required',
            'priority'      => 'required',
            'message'       => 'required',
        ]);

        $tenantId = auth()->tenantId() ?? 1;
        $now = date('Y-m-d H:i:s');

        $priority = in_array($data['priority'], self::PRIORITIES, true) ? $data['priority'] : 'medium';
        $departmentId = (int) $data['department_id'] ?: null;

        // Insert with a unique temporary number, then set TKT-<padded id>.
        $ticketId = db()->table('tickets')->insert([
            'tenant_id'     => $tenantId,
            'client_id'     => (int) $client['id'],
            'department_id' => $departmentId,
            'ticket_number' => substr('TMP-' . bin2hex(random_bytes(10)), 0, 30),
            'subject'       => (string) $data['subject'],
            'priority'      => $priority,
            'status'        => 'open',
            'last_reply_at' => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $ticketNumber = 'TKT-' . str_pad((string) $ticketId, 5, '0', STR_PAD_LEFT);
        db()->table('tickets')->where('id', $ticketId)->update([
            'ticket_number' => $ticketNumber,
            'updated_at'    => $now,
        ]);

        db()->table('ticket_replies')->insert([
            'tenant_id'   => $tenantId,
            'ticket_id'   => $ticketId,
            'user_id'     => auth()->id(),
            'author_type' => 'client',
            'message'     => (string) $data['message'],
            'is_internal' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        audit('ticket.open', 'ticket', $ticketId);

        $phone = (string) ($client['phone'] ?? '');
        if ($phone !== '') {
            $name = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? ''));
            (new WhatsAppService())->sendTemplate('ticket_opened', $phone, [
                'client_name' => $name !== '' ? $name : (string) ($client['company'] ?? 'Client'),
                'ticket_id'   => $ticketNumber,
                'panel_url'   => url('client/tickets/' . $ticketId),
            ]);
        }

        return redirect_route('client/tickets/' . $ticketId, 'success', 'Your ticket has been created.');
    }

    /**
     * View a single ticket — ONLY if it belongs to the current client.
     */
    public function show(Request $request, string $id = ''): Response
    {
        $client = $this->currentClient();
        $this->authorize($client !== null);

        $tenantId = auth()->tenantId() ?? 1;
        $ticket = db()->table('tickets')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        // Ownership enforcement.
        $this->authorize((int) $ticket['client_id'] === (int) $client['id']);

        $department = !empty($ticket['department_id'])
            ? db()->table('ticket_departments')->where('id', (int) $ticket['department_id'])->first()
            : null;

        // Clients never see internal staff notes.
        $replies = db()->table('ticket_replies')
            ->where('ticket_id', (int) $ticket['id'])
            ->where('is_internal', 0)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        return $this->view('client.tickets.show', [
            'ticket'     => $ticket,
            'department' => $department,
            'replies'    => $replies,
            'client'     => $client,
        ]);
    }

    /**
     * Post a client reply; ticket moves to 'customer_reply'.
     */
    public function reply(Request $request, string $id = ''): Response
    {
        $client = $this->currentClient();
        $this->authorize($client !== null);

        $tenantId = auth()->tenantId() ?? 1;
        $ticket = db()->table('tickets')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        // Ownership enforcement.
        $this->authorize((int) $ticket['client_id'] === (int) $client['id']);

        $data = $this->validate($request, ['message' => 'required']);
        $now = date('Y-m-d H:i:s');

        db()->table('ticket_replies')->insert([
            'tenant_id'   => $tenantId,
            'ticket_id'   => (int) $ticket['id'],
            'user_id'     => auth()->id(),
            'author_type' => 'client',
            'message'     => (string) $data['message'],
            'is_internal' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        db()->table('tickets')->where('id', (int) $ticket['id'])->update([
            'status'        => 'customer_reply',
            'last_reply_at' => $now,
            'updated_at'    => $now,
        ]);

        audit('ticket.reply', 'ticket', (int) $ticket['id']);

        return back_with('success', 'Your reply has been sent.');
    }
}
