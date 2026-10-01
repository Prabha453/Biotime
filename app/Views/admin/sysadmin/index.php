<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$act = match ($tab) {
    'roles'  => modal_btn('roleForm', 'New role'),
    'tasks'  => modal_btn('taskForm', 'New task'),
    'api'    => modal_btn('epForm', 'New endpoint'),
    'backups' => '<form method="post" action="' . route_to('system.backup.run') . '">' . csrf_field() . '<button class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i>Back up now</button></form>',
    default  => '',
};
?>
<?= page_head($title, $subtitle, $act) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('system.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'roles'): ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-header">Roles</div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Role</th><th>Tier</th><th>Modules</th><th class="text-end">Members</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): $ps = array_filter(explode(',', (string) $r['perms'])); ?>
          <tr>
            <td class="fw-semibold"><?= esc($r['name']) ?></td>
            <td><?= label(ucfirst($r['tier']), $r['tier'] === 'admin' ? 'danger' : ($r['tier'] === 'manager' ? 'warning' : 'secondary')) ?></td>
            <td class="small text-muted"><?= esc(implode(', ', array_map(fn ($c) => $modules[$c] ?? $c, $ps)) ?: '—') ?></td>
            <td class="text-end mono"><?= (int) $r['members'] ?></td>
            <td class="text-end" style="white-space:nowrap">
              <button class="btn btn-sm btn-outline-secondary js-role" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
              <?= form_open(route_to('system.role.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this role?', 'data-confirm-danger' => '']) ?>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              <?= form_close() ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (! $roles): ?><tr><td colspan="5" class="text-muted small py-3">No roles yet — the built-in admin / manager / viewer tiers still apply.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-header">Administrators &amp; their roles</div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Admin</th><th>Roles</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
          <tr>
            <td><span class="mono"><?= esc($a['username']) ?></span><?= ($a['is_superuser'] === true || $a['is_superuser'] === 't') ? ' ' . label('superuser', 'danger') : '' ?></td>
            <td class="small text-muted"><?= esc($a['groups'] ?? '—') ?></td>
            <td class="text-end">
              <?php if (! ($a['is_superuser'] === true || $a['is_superuser'] === 't')): ?>
              <button class="btn btn-sm btn-outline-secondary js-assign" data-uid="<?= $a['id'] ?>" data-groups='<?= esc(json_encode(array_filter(array_map("trim", explode(",", (string) $a["groups"])))), "attr") ?>'><i class="bi bi-person-gear"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <p class="ext-note mt-2"><i class="bi bi-info-circle"></i> Effective access = highest tier among a person's roles. Superusers are always full admin.</p>
  </div>
</div>

<div class="modal fade" id="roleForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <?= form_open(route_to('system.role.save')) ?>
  <input type="hidden" name="id" value="">
  <div class="modal-header"><h5 class="modal-title">Role</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-2">
      <div class="col"><label class="form-label tiny">Name *</label><input name="name" class="form-control" required maxlength="150"></div>
      <div class="col-4"><label class="form-label tiny">Access tier</label>
        <select name="tier" class="form-select"><option value="viewer">Viewer</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
    </div>
    <label class="form-label tiny">Modules this role may open</label>
    <div class="row">
      <?php foreach ($modules as $code => $lbl): ?>
        <div class="col-6"><label class="form-check"><input type="checkbox" class="form-check-input" name="modules[]" value="<?= $code ?>"> <span class="form-check-label tiny"><?= esc($lbl) ?></span></label></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  <?= form_close() ?>
</div></div></div>

<div class="modal fade" id="assignForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <?= form_open(route_to('system.role.assign')) ?>
  <input type="hidden" name="user_id" value="">
  <div class="modal-header"><h5 class="modal-title">Assign roles</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php foreach ($groupOpts as $g): ?>
      <label class="form-check"><input type="checkbox" class="form-check-input" name="group_id[]" value="<?= $g['id'] ?>" data-name="<?= esc($g['name'], 'attr') ?>"> <span class="form-check-label"><?= esc($g['name']) ?></span></label><br>
    <?php endforeach; ?>
    <?php if (! $groupOpts): ?><p class="text-muted small">Create a role first.</p><?php endif; ?>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  <?= form_close() ?>
</div></div></div>

<?php elseif ($tab === 'security'): ?>
<div class="card" style="max-width:640px"><div class="card-body">
  <?= form_open(route_to('system.security.save')) ?>
  <label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="single_login" value="1" <?= ! empty($row['single_login']) ? 'checked' : '' ?>> <span class="form-check-label">One active session per admin</span></label><br>
  <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="failed_locked" value="1" <?= ! empty($row['failed_locked']) ? 'checked' : '' ?>> <span class="form-check-label">Lock account after repeated bad passwords</span></label>
  <div class="row g-2 mb-3">
    <div class="col"><label class="form-label tiny">Lock after N failures</label><input name="lock_failed_count" type="number" class="form-control" value="<?= (int) ($row['lock_failed_count'] ?? 5) ?>"></div>
    <div class="col"><label class="form-label tiny">Lock for (min)</label><input name="lock_duration" type="number" class="form-control" value="<?= (int) ($row['lock_duration'] ?? 10) ?>"></div>
    <div class="col"><label class="form-label tiny">Session timeout (min)</label><input name="session_timeout" type="number" class="form-control" value="<?= (int) ($row['session_timeout'] ?? 60) ?>"></div>
  </div>
  <div class="mb-3"><label class="form-label tiny">Minimum password strength</label>
    <select name="password_level" class="form-select" style="max-width:220px">
      <option value="1" <?= (int) ($row['password_level'] ?? 2) === 1 ? 'selected' : '' ?>>Any</option>
      <option value="2" <?= (int) ($row['password_level'] ?? 2) === 2 ? 'selected' : '' ?>>Letters + digits</option>
      <option value="3" <?= (int) ($row['password_level'] ?? 2) === 3 ? 'selected' : '' ?>>Letters + digits + symbol</option>
    </select></div>
  <label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="enforce_pwd_expiration" value="1" <?= ! empty($row['enforce_pwd_expiration']) ? 'checked' : '' ?>> <span class="form-check-label">Force password change periodically</span></label>
  <div class="mb-3"><label class="form-label tiny">Password valid for (days)</label><input name="validity_period" type="number" class="form-control" style="max-width:140px" value="<?= (int) ($row['validity_period'] ?? 90) ?>"></div>
  <button class="btn btn-primary">Save policy</button>
  <?= form_close() ?>
</div></div>
<p class="ext-note mt-2"><i class="bi bi-info-circle"></i> TimeTrack's login already enforces the lock-after-failures rule (5 tries → 10 min). This screen sets the policy the value is read from.</p>

<?php elseif ($tab === 'integrations'): ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <div class="fw-semibold mb-3"><i class="bi bi-camera-video me-1"></i>Zoom</div>
      <?= form_open(route_to('system.zoom.save')) ?>
      <div class="mb-2"><label class="form-label tiny">Account e-mail</label><input name="zoom_user_email" class="form-control" value="<?= esc($zoom['zoom_user_email'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label tiny">JWT / Server-to-Server token</label><input name="jwt_token" class="form-control mono" value="<?= esc($zoom['jwt_token'] ?? '') ?>"></div>
      <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="zoom_enable" value="1" <?= ! empty($zoom['zoom_enable']) ? 'checked' : '' ?>> <span class="form-check-label">Enable Zoom meeting links</span></label><br>
      <button class="btn btn-primary btn-sm">Save Zoom</button>
      <?= form_close() ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card"><div class="card-header">Event alert routing</div>
      <?= form_open(route_to('system.events.save')) ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Event</th><th>Module</th><th class="text-center">E-mail</th><th class="text-center">App</th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td class="small"><?= esc($e['event']) ?></td>
            <td class="small text-muted"><?= esc(str_replace(['menu_', 'menu.', '_'], ['', '', ' '], $e['module'])) ?></td>
            <td class="text-center"><input type="checkbox" name="email[]" value="<?= $e['id'] ?>" <?= ($e['email_alert'] === true || $e['email_alert'] === 't') ? 'checked' : '' ?>></td>
            <td class="text-center"><input type="checkbox" name="app[]" value="<?= $e['id'] ?>" <?= ($e['app_alert'] === true || $e['app_alert'] === 't') ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-footer"><button class="btn btn-primary btn-sm">Save routing</button></div>
      <?= form_close() ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'tasks'): ?>
<?php foreach ([['import', 'Auto-import', $imports], ['export', 'Auto-export', $exports]] as [$kind, $lbl, $list]): ?>
<div class="card mb-3">
  <div class="card-header"><?= $lbl ?> tasks</div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Last run</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($list as $t): ?>
      <tr>
        <td class="mono"><?= esc($t['task_code']) ?></td>
        <td class="fw-semibold"><?= esc($t['task_name']) ?></td>
        <td><?= ($t['enable'] === true || $t['enable'] === 't') ? label('enabled', 'success') : label('paused', 'secondary') ?></td>
        <td class="mono small"><?= esc(substr((string) ($t['process_time'] ?? '—'), 0, 16)) ?></td>
        <td class="text-end" style="white-space:nowrap">
          <button class="btn btn-sm btn-outline-secondary js-task" data-kind="<?= $kind ?>" data-json='<?= esc(json_encode($t), 'attr') ?>'><i class="bi bi-pencil"></i></button>
          <?= form_open(route_to('system.task.delete', $kind, $t['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this task?', 'data-confirm-danger' => '']) ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          <?= form_close() ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (! $list): ?><tr><td colspan="5" class="text-muted small py-3">No <?= strtolower($lbl) ?> tasks.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="taskForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <?= form_open(route_to('system.task.save', 'import')) ?>
  <input type="hidden" name="id" value="">
  <div class="modal-header"><h5 class="modal-title">Scheduled task</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label tiny">Kind</label>
      <select id="taskKind" class="form-select"><option value="import">Auto-import</option><option value="export">Auto-export</option></select></div>
    <div class="row g-2 mb-2">
      <div class="col"><label class="form-label tiny">Code *</label><input name="task_code" class="form-control mono" required maxlength="30"></div>
      <div class="col"><label class="form-label tiny">Name *</label><input name="task_name" class="form-control" required maxlength="30"></div>
    </div>
    <div class="mb-2" id="taskCompanyWrap"><label class="form-label tiny">Company</label>
      <select name="company_id" class="form-select"><?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['company_name']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-2"><label class="form-label tiny">Parameters (JSON — path, schedule, format…)</label><textarea name="params" class="form-control mono" rows="3" placeholder='{"path":"\\\\server\\share\\att.csv","cron":"0 2 * * *"}'></textarea></div>
    <label class="form-check"><input type="checkbox" class="form-check-input" name="enable" value="1" checked> <span class="form-check-label tiny">Enabled</span></label>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  <?= form_close() ?>
</div></div></div>

<?php elseif ($tab === 'backups'): ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>File</th><th>Database</th><th>When</th><th>By</th><th>Status</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="mono small"><?= esc(basename((string) $r['backup_file'])) ?></td>
      <td class="mono"><?= esc($r['db_name']) ?></td>
      <td class="mono small"><?= esc(substr((string) $r['backup_time'], 0, 16)) ?></td>
      <td><?= esc($r['operator'] ?? 'system') ?></td>
      <td><?= (int) $r['backup_status'] === 0 ? label('logged', 'success') : label('failed', 'danger') ?></td>
      <td class="text-muted small"><?= esc($r['remark'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-database"></i>No backups logged yet.</div></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager, [10,15,25,50,100], 'bk') ?></div><?php endif; ?>
</div>
<p class="ext-note mt-2"><i class="bi bi-info-circle"></i> "Back up now" records the request; a scheduled <span class="mono">pg_dump</span> on the DB host writes the file (same as ZKBioTime).</p>

<?php else: /* api */ ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-header">API endpoints</div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><th>Method</th><th>Path</th><th>Module</th><th class="text-end">Grants</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($endpoints as $e): ?>
          <tr>
            <td class="fw-semibold"><?= esc($e['name']) ?><?= ($e['is_active'] === true || $e['is_active'] === 't') ? '' : ' ' . label('off', 'secondary') ?></td>
            <td class="mono"><?= esc($e['method']) ?></td>
            <td class="mono small"><?= esc($e['path']) ?></td>
            <td class="text-muted small"><?= esc($e['module']) ?></td>
            <td class="text-end mono"><?= (int) $e['grants'] ?></td>
            <td class="text-end" style="white-space:nowrap">
              <button class="btn btn-sm btn-outline-secondary js-ep" data-json='<?= esc(json_encode($e), 'attr') ?>'><i class="bi bi-pencil"></i></button>
              <?= form_open(route_to('system.api.endpoint.delete', $e['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this endpoint?', 'data-confirm-danger' => '']) ?>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              <?= form_close() ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (! $endpoints): ?><tr><td colspan="6" class="text-muted small py-3">No API endpoints registered.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-header">Access grants</div>
      <div class="card-body">
        <?= form_open(route_to('system.api.grant'), ['class' => 'row g-2 align-items-end mb-3']) ?>
          <div class="col"><label class="form-label tiny">Endpoint</label>
            <select name="endpoint_id" class="form-select form-select-sm"><?php foreach ($endpoints as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['method'] . ' ' . $e['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col"><label class="form-label tiny">User</label>
            <select name="user_id" class="form-select form-select-sm"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['username']) ?></option><?php endforeach; ?></select></div>
          <div class="col-auto"><button class="btn btn-sm btn-primary">Grant</button></div>
        <?= form_close() ?>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>User</th><th>Endpoint</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($grants as $g): ?>
            <tr>
              <td class="mono"><?= esc($g['username']) ?></td>
              <td class="small"><?= esc($g['method'] . ' ' . $g['endpoint']) ?></td>
              <td class="text-end"><?= form_open(route_to('system.api.revoke', $g['id']), ['class' => 'd-inline']) ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button><?= form_close() ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (! $grants): ?><tr><td colspan="3" class="text-muted small">No grants.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="epForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <?= form_open(route_to('system.api.endpoint.save')) ?>
  <input type="hidden" name="id" value="">
  <div class="modal-header"><h5 class="modal-title">API endpoint</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label tiny">Name *</label><input name="name" class="form-control" required maxlength="50"></div>
    <div class="row g-2 mb-2">
      <div class="col-4"><label class="form-label tiny">Method *</label>
        <select name="method" class="form-select"><option>GET</option><option>POST</option><option>PUT</option><option>DELETE</option></select></div>
      <div class="col"><label class="form-label tiny">Path *</label><input name="path" class="form-control mono" required placeholder="/api/transactions/"></div>
    </div>
    <div class="mb-2"><label class="form-label tiny">Module</label><input name="module" class="form-control" placeholder="attendance"></div>
    <div class="mb-2"><label class="form-label tiny">Description</label><input name="description" class="form-control"></div>
    <label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" checked> <span class="form-check-label tiny">Active</span></label>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  <?= form_close() ?>
</div></div></div>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function bindJson(cls, modalId, keys){
  document.querySelectorAll(cls).forEach(function(b){
    b.addEventListener('click', function(){
      var m = document.getElementById(modalId), form = m.querySelector('form'); form.reset();
      if (form.querySelector('[name=id]')) form.querySelector('[name=id]').value = '';
      var d = JSON.parse(b.dataset.json || '{}');
      keys.forEach(function(k){ var f = form.querySelector('[name="'+k+'"]'); if (!f) return;
        if (f.type === 'checkbox') f.checked = d[k] === 1 || d[k] === true || d[k] === 't';
        else f.value = d[k] == null ? '' : d[k];
      });
      if (d.perms !== undefined) {
        var have = (''+(d.perms||'')).split(',');
        form.querySelectorAll('[name="modules[]"]').forEach(function(c){ c.checked = have.indexOf(c.value) > -1; });
        var ts = document.querySelector('[data-tier="'+modalId+'"]');
      }
      new bootstrap.Modal(m).show();
    });
  });
}
bindJson('.js-role', 'roleForm', ['id','name','tier']);
bindJson('.js-ep', 'epForm', ['id','name','method','path','module','description','is_active']);

document.querySelectorAll('.js-task').forEach(function(b){
  b.addEventListener('click', function(){
    var m = document.getElementById('taskForm'), form = m.querySelector('form'); form.reset();
    var d = JSON.parse(b.dataset.json || '{}');
    form.action = form.action.replace(/\/(import|export)\/save$/, '/' + b.dataset.kind + '/save');
    document.getElementById('taskKind').value = b.dataset.kind;
    document.getElementById('taskCompanyWrap').style.display = b.dataset.kind === 'import' ? '' : 'none';
    ['id','task_code','task_name','params','company_id'].forEach(function(k){ var f = form.querySelector('[name="'+k+'"]'); if (f) f.value = d[k] == null ? '' : d[k]; });
    form.querySelector('[name=enable]').checked = d.enable === true || d.enable === 't';
    new bootstrap.Modal(m).show();
  });
});
document.getElementById('taskKind')?.addEventListener('change', function(){
  var f = document.querySelector('#taskForm form');
  f.action = f.action.replace(/\/(import|export)\/save$/, '/' + this.value + '/save');
  document.getElementById('taskCompanyWrap').style.display = this.value === 'import' ? '' : 'none';
});
document.querySelectorAll('.js-assign').forEach(function(b){
  b.addEventListener('click', function(){
    var m = document.getElementById('assignForm'), form = m.querySelector('form'); form.reset();
    form.querySelector('[name=user_id]').value = b.dataset.uid;
    var have = JSON.parse(b.dataset.groups || '[]');
    form.querySelectorAll('[name="group_id[]"]').forEach(function(c){ c.checked = have.indexOf(c.dataset.name) > -1; });
    new bootstrap.Modal(m).show();
  });
});
</script>
<?= $this->endSection() ?>
