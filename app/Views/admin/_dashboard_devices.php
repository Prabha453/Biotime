<?php if ($devices === []): ?>
    <tr><td colspan="2" class="text-center text-muted py-4">No devices configured.</td></tr>
<?php endif; ?>
<?php foreach ($devices as $d): ?>
    <tr>
        <td>
            <span class="dot-status <?= $d['online'] === true || $d['online'] === 't' ? 'dot-online' : 'dot-offline' ?>"></span>
            <a href="<?= route_to('device.view', $d['id']) ?>" class="fw-semibold text-decoration-none ms-1"><?= esc($d['alias']) ?></a>
            <div class="small text-muted mono"><?= esc($d['ip_address']) ?> · <?= esc($d['sn']) ?></div>
        </td>
        <td class="text-end small text-muted">
            <?= number_format((int) $d['user_count']) ?> users<br>
            <?= number_format((int) $d['transaction_count']) ?> logs
        </td>
    </tr>
<?php endforeach; ?>
