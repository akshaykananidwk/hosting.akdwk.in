<?php $this->extend('layouts/admin'); $this->set('title', 'Server Add'); ?>
<?php // FILE: /app/Views/admin/servers/create.php — add aaPanel server form ?>
<?php /* Content renders at top level for this View engine. */ ?>

<div class="card" style="max-width:720px">
    <div class="card-head"><span>➕ New aaPanel Server</span></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/servers')) ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="<?= e(old('name')) ?>" required placeholder="dwk-server-1">
            </div>

            <div class="grid cols-2">
                <div class="form-group">
                    <label>Panel URL *</label>
                    <input type="text" name="panel_url" value="<?= e(old('panel_url')) ?>" required placeholder="https://1.2.3.4:8888">
                    <div class="form-hint">aaPanel full URL (including port).</div>
                </div>
                <div class="form-group">
                    <label>IP Address</label>
                    <input type="text" name="ip_address" value="<?= e(old('ip_address')) ?>" placeholder="1.2.3.4">
                </div>
            </div>

            <div class="form-group">
                <label>API Key (api_sk) *</label>
                <input type="text" name="api_key" value="<?= e(old('api_key')) ?>" required autocomplete="off">
                <div class="form-hint">Encrypt and stored securely.</div>
            </div>

            <div class="grid cols-2">
                <div class="form-group">
                    <label>Hostname</label>
                    <input type="text" name="hostname" value="<?= e(old('hostname')) ?>" placeholder="server1.akdwk.in">
                </div>
                <div class="form-group">
                    <label>Default PHP</label>
                    <input type="text" name="default_php" value="<?= e(old('default_php', '82')) ?>" placeholder="82">
                </div>
            </div>

            <div class="grid cols-2">
                <div class="form-group">
                    <label>Max Accounts</label>
                    <input type="number" name="max_accounts" value="<?= e(old('max_accounts', '150')) ?>" min="1">
                </div>
                <div class="form-group">
                    <label>Options</label>
                    <label class="small" style="font-weight:400"><input type="checkbox" name="auto_assign" value="1" checked style="width:auto"> Auto-assign New orders</label>
                    <label class="small" style="font-weight:400"><input type="checkbox" name="verify_ssl" value="1" style="width:auto"> SSL verify (panel if a valid certificate is present)</label>
                </div>
            </div>

            <div class="flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">Server Save</button>
                <a href="<?= e(url('admin/servers')) ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

