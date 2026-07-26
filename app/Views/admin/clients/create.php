<?php // FILE: /app/Views/admin/clients/create.php — new client form
$this->extend('layouts/admin'); $this->set('title', 'New Client'); ?>

<div class="card" style="max-width:560px">
    <div class="card-head">Add New Client</div>
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/clients')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?= e(old('name')) ?>" required autofocus>
                <div class="form-hint">First and last name (separated by a space).</div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="form-group">
                <label>Mobile *</label>
                <input type="text" name="mobile" value="<?= e(old('mobile')) ?>" placeholder="9876543210" required>
            </div>
            <div class="flex gap-1 mt-3">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="<?= e(url('admin/clients')) ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

