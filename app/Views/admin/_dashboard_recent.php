<?php if ($recent === []): ?>
    <tr><td colspan="5" class="text-center text-muted py-4">No punches for this date and department.</td></tr>
<?php endif; ?>
<?php foreach ($recent as $r): ?>
    <tr>
        <td class="mono"><?= esc($r['tm']) ?></td>
        <td><?= esc($r['name'] ?? '—') ?></td>
        <td class="mono"><?= esc($r['emp_code']) ?></td>
        <td><?= esc($stateLabels[(int) $r['punch_state']] ?? 'Punch') ?></td>
        <td class="text-muted"><?= esc($r['device'] ?? '—') ?></td>
    </tr>
<?php endforeach; ?>
