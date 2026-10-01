<?php
/** Shared From/To + Department + Group filter bar for the 7 Summary Report pages.
 * @var string $from @var string $to @var ?int $dept @var ?int $group
 * @var array $departments @var array $groups
 */
?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Group</label>
            <select name="group" class="form-select" data-native>
                <option value="">All groups</option>
                <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>" <?= $group === (int) $g['id'] ? 'selected' : '' ?>><?= esc($g['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Run</button>
        </div>
    </div>
</form>
