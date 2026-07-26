<?php // FILE: /app/Views/admin/clients/create.php — new client form
$this->extend('layouts/admin'); $this->set('title', 'નવો ગ્રાહક'); ?>

<div class="card" style="max-width:560px">
    <div class="card-head">નવો ગ્રાહક ઉમેરો</div>
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/clients')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>પૂરું નામ *</label>
                <input type="text" name="name" value="<?= e(old('name')) ?>" required autofocus>
                <div class="form-hint">પ્રથમ + છેલ્લું નામ (space થી અલગ).</div>
            </div>
            <div class="form-group">
                <label>ઈમેલ *</label>
                <input type="email" name="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="form-group">
                <label>મોબાઈલ *</label>
                <input type="text" name="mobile" value="<?= e(old('mobile')) ?>" placeholder="9876543210" required>
            </div>
            <div class="flex gap-1 mt-3">
                <button type="submit" class="btn btn-primary">બનાવો</button>
                <a href="<?= e(url('admin/clients')) ?>" class="btn btn-outline">રદ કરો</a>
            </div>
        </form>
    </div>
</div>

