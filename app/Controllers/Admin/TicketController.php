<?php
// FILE: /app/Controllers/Admin/TicketController.php
// -------------------------------------------------------------------
// MODULE 12 — Support Tickets (Admin/staff side).
// View all tickets, read the thread, reply, change status and add
// internal notes. Client-facing replies trigger a WhatsApp notification.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsAppService;

class TicketController extends Controller
{
    /** Allowed ticket statuses (matches the `tickets.status` ENUM). */
    protected const STATUSES = ['open', 'answered', 'customer_reply', 'on_hold', 'closed'];

    /**
     * All tickets — paginated, with client + department, optional ?status= filter.
     */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $status = trim((string) $request->query('status', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = db()->table('tickets')
            ->leftJoin('clients', 'tickets.client_id', '=', 'clients.id')
            ->leftJoin('ticket_departments', 'tickets.department_id', '=', 'ticket_departments.id')
            ->where('tickets.tenant_id', $tenantId)
            ->select([
                'tickets.id as id',
                'tickets.ticket_number as ticket_number',
                'tickets.subject as subject',
                'tickets.priority as priority',
                'tickets.status as status',
                'tickets.last_reply_at as last_reply_at',
                'tickets.created_at as created_at',
                'clients.first_name as client_first_name',
                'clients.last_name as client_last_name',
                'clients.company as client_company',
                'ticket_departments.name as department_name',
            ])
            ->orderBy('tickets.updated_at', 'DESC');

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $query->where('tickets.status', $status);
        }

        $tickets = $query->paginate($page, 20);

        return $this->view('admin.tickets.index', [
            'tickets' => $tickets,
            'status'  => $status,
        ]);
    }

    /**
     * Single ticket — full conversation (incl. internal notes) + reply form.
     */
    public function show(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $ticket = db()->table('tickets')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        $client = !empty($ticket['client_id'])
            ? db()->table('clients')->where('id', (int) $ticket['client_id'])->first()
            : null;
        $department = !empty($ticket['department_id'])
            ? db()->table('ticket_departments')->where('id', (int) $ticket['department_id'])->first()
            : null;

        // Staff see everything, including internal notes.
        $replies = db()->table('ticket_replies')
            ->where('ticket_id', (int) $ticket['id'])
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        return $this->view('admin.tickets.show', [
            'ticket'     => $ticket,
            'client'     => $client,
            'department' => $department,
            'replies'    => $replies,
            'statuses'   => self::STATUSES,
        ]);
    }

    /**
     * Post a staff reply (or internal note), change status, notify the client.
     */
    public function reply(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $ticket = db()->table('tickets')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        $data = $this->validate($request, ['message' => 'required']);

        $isInternal = $request->boolean('is_internal') ? 1 : 0;
        $now = date('Y-m-d H:i:s');

        db()->table('ticket_replies')->insert([
            'tenant_id'   => $tenantId,
            'ticket_id'   => (int) $ticket['id'],
            'user_id'     => auth()->id(),
            'author_type' => 'staff',
            'message'     => (string) $data['message'],
            'is_internal' => $isInternal,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // Status: honour an explicit select; otherwise a client-facing reply
        // moves the ticket to 'answered'. Internal notes leave status as-is.
        $chosen = trim((string) $request->input('status', ''));
        $update = ['updated_at' => $now];
        if ($chosen !== '' && in_array($chosen, self::STATUSES, true)) {
            $update['status'] = $chosen;
        } elseif (!$isInternal) {
            $update['status'] = 'answered';
        }
        if (!$isInternal) {
            $update['last_reply_at'] = $now;
        }
        db()->table('tickets')->where('id', (int) $ticket['id'])->update($update);

        audit('ticket.reply', 'ticket', (int) $ticket['id'], ['internal' => $isInternal]);

        // Notify the client on WhatsApp for public replies only.
        if (!$isInternal && !empty($ticket['client_id'])) {
            $client = db()->table('clients')->where('id', (int) $ticket['client_id'])->first();
            $phone = (string) ($client['phone'] ?? '');
            if ($client && $phone !== '') {
                $name = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? ''));
                (new WhatsAppService())->sendTemplate('ticket_replied', $phone, [
                    'client_name' => $name !== '' ? $name : (string) ($client['company'] ?? 'Client'),
                    'ticket_id'   => (string) $ticket['ticket_number'],
                    'panel_url'   => url('client/tickets/' . $ticket['id']),
                ]);
            }
        }

        return back_with('success', 'Reply sent.');
    }
}
