<?php
/** Shared Month/Year/Department filter bar for the 5 Monthly Report pages.
 * @var int $year @var int $month @var ?int $dept @var array $departments
 */
$months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label">Month</label>
            <select name="m" class="form-select" data-native>
                <?php foreach ($months as $n => $lbl): ?><option value="<?= $n ?>" <?= $month === $n ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Year</label>
            <input type="number" name="y" value="<?= $year ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Generate</button>
        </div>
    </div>
</form>
