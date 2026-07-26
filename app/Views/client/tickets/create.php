<?php /* FILE: /app/Views/client/tickets/create.php — new ticket form (client) */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'નવી ટિકિટ'); ?>
<?php $this->section('content'); ?>
<?php
    $depts = $departments ?? [];
    $prios = $priorities ?? ['low', 'medium', 'high', 'urgent'];
?>
<div class="flex items-center justify-between mb-2">
    <h2 class="page-title" style="margin:0">➕ નવી ટિકિટ</h2>
    <a href="<?= e(url('client/tickets')) ?>" class="btn btn-sm btn-outline">← બધી ટિકિટ</a>
</div>

<div class="card">
    <div class="card-head">તમારી સમસ્યા જણાવો</div>
    <div class="card-body">
        <form method="post" action="<?= e(url('client/tickets')) ?>">
            <?= csrf_field() ?>
            <div class="grid cols-2">
                <div class="form-group">
                    <label>વિભાગ (Department)</label>
                    <select name="department_id" required>
                        <option value="">— પસંદ કરો —</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= e($d['id']) ?>" <?= (string) old('department_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>પ્રાથમિકતા (Priority)</label>
                    <select name="priority" required>
                        <?php foreach ($prios as $p): ?>
                            <option value="<?= e($p) ?>" <?= (old('priority', 'medium')) === $p ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>વિષય (Subject)</label>
                <input type="text" name="subject" value="<?= e(old('subject')) ?>" required>
            </div>
            <div class="form-group">
                <label>સંદેશ (Message)</label>
                <textarea name="message" rows="6" required><?= e(old('message')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">ટિકિટ મોકલો</button>
        </form>
    </div>
</div>
<?php $this->end(); ?>
