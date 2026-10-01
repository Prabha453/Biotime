<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$areaOptions = '';
foreach ($areas as $a) {
    $areaOptions .= '<option value="' . $a['id'] . '">' . esc($a['area_name']) . '</option>';
}
?>
<?= page_head($title, $subtitle, modal_btn('ahNew', 'Map holiday')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Holiday</th><th>Dates</th><th>Area</th><th>Time zone applied</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($accHolidays as $h): ?>
            <tr>
                <td class="fw-semibold"><?= esc($h['holiday'] ?? '—') ?></td>
                <td class="mono small"><?= esc($h['start_date']) ?> → <?= esc($h['end_date']) ?></td>
                <td><?= esc($h['area_name'] ?? '—') ?></td>
                <td><?= esc($h['timezone_name'] ?? '—') ?></td>
                <td class="text-end">
                    <?= form_open(route_to('access.holiday.delete', $h['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this mapping?']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $accHolidays): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-x"></i>No access-holiday mappings.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<p class="ext-note mt-2"><i class="bi bi-info-circle"></i> On a mapped holiday the door follows the chosen time zone instead of the normal one.</p>
<div class="modal fade" id="ahNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('access.holiday.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Map holiday to door schedule</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Holiday *</label>
            <select name="holiday_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($holidayList as $h): ?><option value="<?= $h['id'] ?>"><?= esc($h['alias'] . ' (' . $h['start_date'] . ')') ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label tiny">Area *</label><select name="area_id" class="form-select" required><option value="">Select…</option><?= $areaOptions ?></select></div>
        <div class="mb-1"><label class="form-label tiny">Time zone *</label>
            <select name="timezone_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($timezones as $z): ?><option value="<?= $z['id'] ?>"><?= esc('#' . $z['timezone_no'] . ' ' . $z['timezone_name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
