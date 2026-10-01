<?php
/** Device list — swapped by [data-crud] after filter / page. */
$on  = static fn ($v) => $v === true || $v === 't' || $v === '1' || $v === 1;
$num = static fn ($v) => number_format((int) $v);
?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <span>Devices</span>
        <span class="text-muted small fw-normal"><?= $num(count($rows)) ?> of <?= $num($pager->getTotal()) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll" aria-label="Select all"></th>
                <th data-col="name">Device Name</th>
                <th data-col="sn">Serial Number</th>
                <th data-col="area">Area</th>
                <th data-col="ip">Device IP</th>
                <th data-col="state">State</th>
                <th data-col="last">Last Activity</th>
                <th data-col="users" class="text-end">User Qty.</th>
                <th data-col="fp" class="text-end">FP Qty.</th>
                <th data-col="face" class="text-end">Face Qty.</th>
                <th data-col="palm" class="text-end">Palm Qty.</th>
                <th data-col="trans" class="text-end">Transaction Qty.</th>
                <th data-col="cmd" class="text-end">Cmd</th>
                <th class="text-end">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $d): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $d['id'] ?>"></td>
                    <td>
                        <a href="<?= route_to('device.view', $d['id']) ?>" class="cell-title" data-turbo="true"><?= esc($d['alias']) ?></a>
                        <?php if ((int) $d['is_registration']): ?><span class="badge bg-label-info ms-1">Reg</span><?php endif; ?>
                        <?php if ((int) $d['is_access']): ?><span class="badge bg-label-secondary ms-1">Access</span><?php endif; ?>
                    </td>
                    <td class="mono small"><?= esc($d['sn']) ?></td>
                    <td class="text-muted"><?= esc($d['area_name'] ?? '—') ?></td>
                    <td class="mono small"><?= esc($d['ip_address']) ?></td>
                    <td>
                        <span class="dot-status <?= $on($d['online']) ? 'dot-online' : 'dot-offline' ?>"></span>
                        <span class="small <?= $on($d['online']) ? 'text-success' : 'text-muted' ?>"><?= $on($d['online']) ? 'Online' : 'Offline' ?></span>
                    </td>
                    <td class="mono small text-muted"><?= esc($d['last_seen'] ?? '—') ?></td>
                    <td class="text-end mono"><?= $num($d['user_count']) ?></td>
                    <td class="text-end mono"><?= $num($d['fp_count']) ?></td>
                    <td class="text-end mono"><?= $num($d['face_count']) ?></td>
                    <td class="text-end mono"><?= $num($d['palm_count']) ?></td>
                    <td class="text-end mono"><?= $num($d['transaction_count']) ?></td>
                    <td class="text-end mono">
                        <?php if ((int) $d['cmd_count']): ?>
                            <a href="<?= route_to('device.view', $d['id']) ?>" class="badge bg-label-warning" data-turbo="true"><?= (int) $d['cmd_count'] ?></a>
                        <?php else: ?>0<?php endif; ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap">
                        <button class="icon-ghost js-dev-edit" title="Edit" data-json='<?= esc(json_encode($d), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                        <a href="<?= route_to('device.view', $d['id']) ?>" class="icon-ghost" title="Detail" data-turbo="true"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="14"><div class="empty-state"><i class="bi bi-hdd-network"></i>No devices registered.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pager_bar($pager) ?>
