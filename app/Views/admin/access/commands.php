<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <?= grid_toolbar(['delete' => route_to('access.command.bulk_delete')]) ?>
    <?= form_open(route_to('access.command.clear'), ['data-confirm' => 'Clear the entire pending command queue?', 'data-confirm-danger' => '']) ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.closest('form').submit()"><i class="bi bi-x-circle me-1"></i>Clear all</button>
    <?= form_close() ?>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr>
            <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
            <th>Serial number</th><th>Device name</th><th>Content</th><th>Commit time</th><th>Transfer time</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($commands as $c): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $c['id'] ?>"></td>
                <td class="mono small"><?= esc($c['sn'] ?? '—') ?></td>
                <td class="fw-semibold"><?= esc($c['device'] ?? '—') ?></td>
                <td class="small mono"><?= esc($c['content']) ?></td>
                <td class="mono small"><?= esc($c['commit_at'] ?? '—') ?></td>
                <td class="mono small"><?= esc($c['transfer_at'] ?? '—') ?></td>
                <td class="text-end">
                    <?= form_open(route_to('access.command.delete', $c['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this queued command?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $commands): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>No pending commands.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php if (isset($cmdPager)): ?><div class="px-3 pb-2"><?= $cmdPager->links('cmd') ?></div><?php endif; ?>
<?= $this->endSection() ?>
