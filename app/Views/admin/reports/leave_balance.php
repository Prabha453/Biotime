<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>') ?>

<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <input type="number" name="y" class="form-control" value="<?= (int) $year ?>" style="width:8rem">
    </div>
    <div class="col-auto"><button class="btn btn-primary">Show</button></div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= route_to('reports') ?>">Back</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr>
                <th data-col="employee">Employee</th><th data-col="group">Group</th>
                <?php foreach ($types as $t): ?><th data-col="pc_<?= esc($t['id'], 'attr') ?>" class="text-center"><?= esc($t['code']) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><span class="mono text-muted"><?= esc($r['emp']['emp_code']) ?></span> <?= esc($r['emp']['name'] ?? '') ?></td>
                    <td class="small text-muted"><?= esc($r['emp']['group_name'] ?? '—') ?></td>
                    <?php foreach ($types as $t): $c = $r['cells'][$t['id']]; ?>
                        <td class="text-center mono small">
                            <?php if ($c === null || $c['entitlement'] <= 0): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <span class="<?= $c['balance'] < 0 ? 'text-danger' : '' ?>">
                                    <?= rtrim(rtrim((string) $c['balance'], '0'), '.') ?>
                                </span>
                                <span class="text-muted">/ <?= (int) $c['entitlement'] ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<p class="ext-note mt-2">balance / entitlement · dash = no entitlement for that leave type in the employee's group.</p>
<?= $this->endSection() ?>
