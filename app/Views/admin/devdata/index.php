<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $on = static fn ($v) => $v === true || $v === 't' || (int) $v === 1; ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'left'         => '<button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete data-url="' . route_to('devdata.bulk_delete', $tab) . '" disabled><i class="bi bi-trash me-1"></i>Delete <span data-grid-count>0</span></button>',
    'filter'       => '#devdataFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'export'       => ['key' => $tab === 'biophoto' ? 'biophoto' : 'biodata'],
    'columns'      => true,
]) ?>
<?php if ($tab === 'biophoto'): ?>
<?= filter_builder('devdataFilter', [
    'emp_code'       => ['label' => 'Employee ID', 'type' => 'text'],
    'emp_name'       => ['label' => 'Employee name', 'type' => 'text'],
    'enroll_sn'      => ['label' => 'Enroll device', 'type' => 'text'],
    'approval_state' => ['label' => 'Approval', 'type' => 'select', 'options' => $approvalStates],
    'register_time'  => ['label' => 'Registered', 'type' => 'date'],
], ['button' => false]) ?>
<?php else: ?>
<?= filter_builder('devdataFilter', [
    'emp_code'  => ['label' => 'Employee ID', 'type' => 'text'],
    'emp_name'  => ['label' => 'Employee name', 'type' => 'text'],
    'bio_type'  => ['label' => 'Template type', 'type' => 'select', 'options' => $bioTypes],
    'enroll_sn' => ['label' => 'Enroll device', 'type' => 'text'],
], ['button' => false]) ?>
<?php endif; ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<?php if ($tab === 'biophoto'): ?>
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="emp">Employee</th><th data-col="sn">Enroll device</th>
        <th data-col="approval">Approval</th><th data-col="registered">Registered</th>
        <th data-col="approved">Approved</th><th data-col="remark">Remark</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td><span class="fw-semibold"><?= esc($r['emp_name'] ?: $r['emp_code']) ?></span> <span class="text-muted small mono"><?= esc($r['emp_code']) ?></span></td>
            <td class="mono small"><?= esc($r['enroll_sn'] ?: '—') ?></td>
            <td><span class="badge bg-label-<?= [0=>'warning',1=>'success',2=>'danger',3=>'info'][(int) $r['approval_state']] ?? 'secondary' ?>"><?= esc($approvalStates[(int) $r['approval_state']] ?? '—') ?></span></td>
            <td class="mono small text-muted"><?= esc($r['registered_at'] ?? '—') ?></td>
            <td class="mono small text-muted"><?= esc($r['approved_at'] ?? '—') ?></td>
            <td class="small text-muted"><?= esc($r['remark'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-person-badge"></i>No bio-photos uploaded yet.</div></td></tr><?php endif; ?>
    </tbody>
<?php else: ?>
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="emp">Employee</th><th data-col="type">Type</th><th data-col="idx" class="text-end">Index</th>
        <th data-col="ver">Algorithm</th><th data-col="sn">Enroll device</th>
        <th data-col="valid">Valid</th><th data-col="duress">Duress</th><th data-col="updated">Updated</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td><span class="fw-semibold"><?= esc($r['emp_name'] ?: $r['emp_code']) ?></span> <span class="text-muted small mono"><?= esc($r['emp_code']) ?></span></td>
            <td><?= esc($bioTypes[(int) $r['bio_type']] ?? ('Type ' . (int) $r['bio_type'])) ?></td>
            <td class="text-end mono"><?= (int) $r['bio_no'] ?></td>
            <td class="mono small"><?= esc($r['major_ver'] . '.' . $r['minor_ver']) ?></td>
            <td class="mono small"><?= esc($r['enroll_sn'] ?: '—') ?></td>
            <td><?= $on($r['valid']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' ?></td>
            <td><?= $on($r['duress']) ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' : '—' ?></td>
            <td class="mono small text-muted"><?= esc($r['updated_at'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="9"><div class="empty-state"><i class="bi bi-fingerprint"></i>No biometric templates uploaded yet.</div></td></tr><?php endif; ?>
    </tbody>
<?php endif; ?>
</table></div></div>
<?= pager_bar($pager) ?>
<?= $this->endSection() ?>
