<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('lvlNew', 'New access level')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Area / doors</th><th>Time zone</th><th>Verify mode</th><th>Holidays</th><th>Members</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
            <tr>
                <td class="mono"><?= (int) $g['group_no'] ?></td>
                <td class="fw-semibold"><?= esc($g['group_name']) ?></td>
                <td><?= esc($g['area_name'] ?? '—') ?></td>
                <td class="small">
                    <?php $tzn = array_filter([$g['timezone1_name'] ?? null, $g['timezone2_name'] ?? null, $g['timezone3_name'] ?? null]); ?>
                    <?= $tzn ? esc(implode(', ', $tzn)) : '<span class="text-danger">Not set</span>' ?>
                </td>
                <td class="small"><?= esc($vm[(int) $g['verify_mode']] ?? ('Mode ' . $g['verify_mode'])) ?></td>
                <td><?= (int) $g['is_include_holiday'] === 1 ? '<span class="pill pill-info">Included</span>' : '<span class="pill pill-muted">Excluded</span>' ?></td>
                <td class="mono"><?= (int) $g['members'] ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#lvlEdit<?= $g['id'] ?>"><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('access.level.delete', $g['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this access level?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
            <?= view('admin/access/_level_form', ['id' => 'lvlEdit' . $g['id'], 'row' => $g, 'areas' => $areas, 'vm' => $vm, 'timezones' => $timezones, 'nextGroupNo' => $nextGroupNo]) ?>
        <?php endforeach; ?>
        <?php if (! $groups): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-door-open"></i>No access levels defined.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= view('admin/access/_level_form', ['id' => 'lvlNew', 'row' => null, 'areas' => $areas, 'vm' => $vm, 'timezones' => $timezones, 'nextGroupNo' => $nextGroupNo]) ?>
<?= $this->endSection() ?>
