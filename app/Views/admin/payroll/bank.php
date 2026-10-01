<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$modes = [1 => 'Bank transfer', 2 => 'Cash', 3 => 'Cheque'];
?>
<?= page_head($title, $subtitle) ?>
<?= view('admin/payroll/_nav', ['tab' => 'bank']) ?>

<div class="card">
    <div class="card-header d-none d-lg-flex" style="gap:1rem">
        <span style="flex:0 0 200px">Employee</span>
        <span style="flex:0 0 150px">Payment mode</span>
        <span style="flex:1">Bank</span>
        <span style="flex:1">Account number</span>
        <span style="flex:0 0 120px">Reference</span>
        <span style="flex:0 0 110px">Agent ID</span>
        <span style="flex:0 0 130px">Agent account</span>
        <span style="flex:0 0 40px"></span>
    </div>
    <div class="card-body py-1">
        <?php if (! $rows): ?><div class="empty-state"><i class="bi bi-bank"></i>No employee payroll profiles.</div><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?= form_open(route_to('payroll.bank.save', $r['employee_id']), ['class' => 'd-flex flex-wrap align-items-center gap-2 py-2 border-bottom']) ?>
                <div style="flex:0 0 200px" class="lh-sm">
                    <div class="fw-semibold small"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?></div>
                    <span class="mono text-muted" style="font-size:.72rem"><?= esc($r['emp_code']) ?></span>
                </div>
                <select name="payment_mode" class="form-select form-select-sm" style="flex:0 0 150px">
                    <?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>" <?= (int) $r['payment_mode'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select>
                <input type="hidden" name="payment_type" value="<?= esc($r['payment_type'] ?: 1) ?>">
                <input name="bank_name" class="form-control form-control-sm" style="flex:1;min-width:130px" value="<?= esc($r['bank_name']) ?>" placeholder="Bank name">
                <input name="bank_account" class="form-control form-control-sm mono" style="flex:1;min-width:150px" value="<?= esc($r['bank_account']) ?>" placeholder="IBAN / account">
                <input name="personnel_id" class="form-control form-control-sm" style="flex:0 0 120px" value="<?= esc($r['personnel_id']) ?>" placeholder="ref">
                <input name="agent_id" class="form-control form-control-sm" style="flex:0 0 110px" value="<?= esc($r['agent_id'] ?? '') ?>" placeholder="Agent ID">
                <input name="agent_account" class="form-control form-control-sm mono" style="flex:0 0 130px" value="<?= esc($r['agent_account'] ?? '') ?>" placeholder="Agent account">
                <button class="btn btn-sm btn-outline-secondary" style="flex:0 0 40px" title="Save"><i class="bi bi-check2"></i></button>
            <?= form_close() ?>
        <?php endforeach; ?>
    </div>
</div>
<p class="text-muted small mt-2">Each row saves independently. Writes to <code>payroll_emppayrollprofile</code>.</p>
<?= $this->endSection() ?>
