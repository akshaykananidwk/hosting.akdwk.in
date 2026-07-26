<?php // FILE: /app/Views/admin/clients/show.php — client detail
$this->extend('layouts/admin');
$this->set('title', 'Client: ' . trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')));
$cBadge = ['active' => 'success', 'inactive' => 'muted', 'suspended' => 'warning', 'closed' => 'danger'];
$sBadge = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'terminated' => 'muted', 'cancelled' => 'muted', 'fraud' => 'danger'];
$iBadge = ['paid' => 'success', 'unpaid' => 'warning', 'overdue' => 'danger', 'draft' => 'muted', 'cancelled' => 'muted', 'refunded' => 'info'];
$post = url('admin/clients/' . $client['id']);
?>

<div class="grid cols-4 mb-3">
    <div class="stat"><div class="label">Credit Balance</div><div class="value sm"><?= e(money($client['credit_balance'] ?? 0)) ?></div></div>
    <div class="stat"><div class="label">Services</div><div class="value sm"><?= count($services) ?></div></div>
    <div class="stat"><div class="label">Invoices</div><div class="value sm"><?= count($invoices) ?></div></div>
    <div class="stat"><div class="label">Status</div><div class="value sm"><span class="badge badge-<?= $cBadge[$client['status']] ?? 'muted' ?>"><?= e($client['status']) ?></span></div></div>
</div>

<div class="grid cols-2">
    <!-- Edit client -->
    <div class="card">
        <div class="card-head">
            <span>Client Details</span>
            <span class="mono small muted"><?= e($client['client_code'] ?? '') ?></span>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e($post) ?>">
                <?= csrf_field() ?>
                <div class="grid cols-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?= e($client['first_name'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($client['last_name'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($client['email'] ?? '') ?>" required></div>
                <div class="grid cols-2">
                    <div class="form-group"><label>Mobile</label><input type="text" name="phone" value="<?= e($client['phone'] ?? '') ?>"></div>
                    <div class="form-group"><label>Company</label><input type="text" name="company" value="<?= e($client['company'] ?? '') ?>"></div>
                </div>
                <div class="grid cols-2">
                    <div class="form-group"><label>GSTIN</label><input type="text" name="gstin" value="<?= e($client['gstin'] ?? '') ?>"></div>
                    <div class="form-group"><label>State Code</label><input type="text" name="state_code" value="<?= e($client['state_code'] ?? '') ?>"></div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active', 'inactive', 'suspended', 'closed'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($client['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>

    <div>
        <!-- Add credit -->
        <div class="card">
            <div class="card-head">Add Credit</div>
            <div class="card-body">
                <form method="post" action="<?= e($post) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" min="0.01" name="add_credit" placeholder="0.00" required></div>
                    <div class="form-group"><label>Note</label><input type="text" name="credit_note" placeholder="Manual credit"></div>
                    <button type="submit" class="btn btn-success">Credit Add</button>
                </form>
            </div>
        </div>
        <!-- Impersonate -->
        <div class="card">
            <div class="card-head">Impersonate</div>
            <div class="card-body">
                <p class="muted small mb-2">Sign in as this client (super admin only).</p>
                <form method="post" action="<?= e(url('admin/clients/' . $client['id'] . '/impersonate')) ?>" data-confirm="Sign in as this client?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline">↪︎ Login as client</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Services -->
<div class="card">
    <div class="card-head">Services</div>
    <div class="card-body table-wrap">
        <table class="table">
            <thead><tr><th>Domain</th><th>Plan</th><th>Cycle</th><th>Recurring</th><th>Next Due</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($services)): ?><tr><td colspan="6" class="text-center muted">No services</td></tr><?php endif; ?>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/services/' . $s['id'])) ?>"><?= e($s['domain']) ?></a></td>
                        <td><?= e($s['product_name'] ?? '—') ?></td>
                        <td class="small"><?= e($s['billing_cycle']) ?></td>
                        <td><?= e(money($s['recurring_amount'])) ?></td>
                        <td class="small"><?= e($s['next_due_date'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= $sBadge[$s['status']] ?? 'muted' ?>"><?= e($s['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Invoices -->
<div class="card">
    <div class="card-head">Invoices</div>
    <div class="card-body table-wrap">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Issue</th><th>Due</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($invoices)): ?><tr><td colspan="6" class="text-center muted">No invoices</td></tr><?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td class="mono"><?= e($inv['invoice_number']) ?></td>
                        <td class="small"><?= e($inv['issue_date']) ?></td>
                        <td class="small"><?= e($inv['due_date']) ?></td>
                        <td><?= e(money($inv['total'])) ?></td>
                        <td><span class="badge badge-<?= $iBadge[$inv['status']] ?? 'muted' ?>"><?= e($inv['status']) ?></span></td>
                        <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= e(url('admin/invoices/' . $inv['id'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

