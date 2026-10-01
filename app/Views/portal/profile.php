<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6">My details</h2>
            <div class="table-responsive">
            <table class="table table-sm mb-0 mt-2"><tbody>
                <tr><td class="text-muted">Name</td><td><?= esc($emp['name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Employee code</td><td class="mono"><?= esc($emp['emp_code']) ?></td></tr>
                <tr><td class="text-muted">Department</td><td><?= esc($emp['dept_name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Position</td><td><?= esc($emp['position_name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Email</td><td><?= esc($emp['email'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Mobile</td><td><?= esc($emp['mobile'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Hire date</td><td class="mono"><?= esc($emp['hire_date'] ?? '—') ?></td></tr>
            </tbody></table>
            </div>
            <p class="small text-muted mt-2 mb-0">To change your personal details, contact HR.</p>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6">Change password</h2>
            <form method="post" action="<?= route_to('portal.password') ?>" class="mt-2">
                <?= csrf_field() ?>
                <div class="mb-2"><label class="form-label small">Current password</label><input type="password" name="current" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">New password</label><input type="password" name="new" class="form-control" minlength="8" required></div>
                <div class="mb-3"><label class="form-label small">Confirm new password</label><input type="password" name="confirm" class="form-control" minlength="8" required></div>
                <button class="btn text-white" style="background:#F26522">Update password</button>
            </form>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
