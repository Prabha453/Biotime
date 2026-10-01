<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $emp !== null;
$e   = static fn (string $k, $d = '') => esc(old($k, $emp[$k] ?? $d));
$aRaw = static fn (string $k, $d = null) => old($k, $att[$k] ?? $d);
$pRaw = static fn (string $k, $d = null) => old($k, $pay[$k] ?? $d);
$truthy = static fn ($v) => in_array($v, [true, 't', '1', 1, 'on'], true);
// checkbox helper — session old() wins, else the stored row, else the default
$chk = static function (string $k, bool $default) use ($att, $truthy): bool {
    $old = old($k);
    if ($old !== null) { return $truthy($old); }
    return array_key_exists($k, $att ?? []) ? $truthy($att[$k]) : $default;
};
$sel = static fn ($a, $b) => (string) $a === (string) $b ? 'selected' : '';

// ZKBioTime enum values (personnel.Employee model — verbatim)
$empTypes   = [1 => 'Permanent', 2 => 'Temporary', 3 => 'Probation'];
$genders    = ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'];
$verifyMode = [
    -1 => 'Apply group mode', 0 => 'Any', 1 => 'Fingerprint only', 2 => 'User ID only', 3 => 'Password',
    4 => 'Card only', 5 => 'Fingerprint or password', 6 => 'Fingerprint or card', 7 => 'Password or card',
    8 => 'User ID and fingerprint', 9 => 'Fingerprint and password', 10 => 'Fingerprint and card',
    11 => 'Password and card', 12 => 'Fingerprint + password + card', 13 => 'User ID + fingerprint/password',
    14 => 'Fingerprint + card / User ID', 15 => 'Face only', 16 => 'Face and fingerprint', 17 => 'Face and password',
    18 => 'Face and card', 19 => 'Face + fingerprint + card', 20 => 'Face + fingerprint + password',
    21 => 'Finger vein', 22 => 'Finger vein and password', 23 => 'Finger vein and card',
    24 => 'Finger vein + password + card', 25 => 'Palm', 26 => 'Palm and card', 27 => 'Palm and face',
    28 => 'Palm and fingerprint', 29 => 'Palm + fingerprint + face',
];
$devPriv    = [0 => 'Employee', 2 => 'Registrar', 6 => 'System administrator', 10 => 'User defined', 14 => 'Super administrator'];
$appStatus  = [1 => 'Enable', 0 => 'Disable'];
$appRole    = [1 => 'Employee', 2 => 'Administrator'];
$payPeriod  = [1 => 'Monthly', 2 => 'Weekly', 3 => 'Daily', 4 => 'Hourly'];
$payType    = [1 => 'Cash', 2 => 'Bank transfer', 3 => 'Cheque'];
$empType0   = (int) old('emp_type', $emp['emp_type'] ?? 0);
?>
<?= page_head($title, $isEdit ? 'Employee #' . esc($emp['emp_code']) : 'Add someone to the biometric roster', '', [
    ['Home', route_to('dashboard')], ['Employees', route_to('employees')], [$isEdit ? 'Edit' : 'Add'],
]) ?>

<?= form_open_multipart(route_to('employee.save'), ['id' => 'empForm', 'data-turbo' => 'false']) ?>
<?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $emp['id'] ?>"><?php endif; ?>

<?php $photoUrl = ! empty($emp['photo']) ? base_url('assets/photos/' . basename($emp['photo'])) : ''; ?>
<div class="card mb-3">
    <div class="card-header">Profile</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-9">
                <div class="row g-3">
                    <?php if (count($companies) > 1): ?>
                    <div class="col-md-4">
                        <label class="form-label">Company</label>
                        <select name="company_id" class="form-select">
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $sel($c['id'], old('company_id', $emp['company_id'] ?? ($companies[0]['id'] ?? ''))) ?>><?= esc($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="company_id" value="<?= $companies[0]['id'] ?? '' ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Employee ID<?= req() ?></label>
                        <input name="emp_code" class="form-control mono<?= err_class('emp_code') ?>" required maxlength="20"
                            <?= ! empty($numEmpCode) ? 'inputmode="numeric" pattern="[0-9]+"' : '' ?> value="<?= $e('emp_code') ?>">
                        <?= err_text('emp_code') ?>
                        <?php if (! empty($numEmpCode)): ?><p class="form-hint">Numeric codes only (Personnel ▸ Configurations).</p><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">First name</label>
                        <input name="first_name" class="form-control<?= err_class('first_name') ?>" maxlength="100" value="<?= $e('first_name') ?>">
                        <?= err_text('first_name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last name</label>
                        <input name="last_name" class="form-control" maxlength="100" value="<?= $e('last_name') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Department<?= req() ?></label>
                        <select name="department_id" class="form-select<?= err_class('department_id') ?>" required>
                            <option value="">— select —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $sel($d['id'], old('department_id', $emp['department_id'] ?? '')) ?>><?= esc($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err_text('department_id') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation</label>
                        <select name="position_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($positions as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $sel($d['id'], old('position_id', $emp['position_id'] ?? '')) ?>><?= esc($d['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Area<?= req() ?></label>
                        <select name="area_id" class="form-select<?= err_class('area_id') ?>" required>
                            <option value="">—</option>
                            <?php foreach ($areas as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $sel($d['id'], old('area_id', $areaId ?: '')) ?>><?= esc($d['area_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err_text('area_id') ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Employment type</label>
                        <select name="emp_type" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($empTypes as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('emp_type', $emp['emp_type'] ?? '')) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hired date</label>
                        <input type="date" name="hire_date" class="form-control" value="<?= $e('hire_date', date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Superior</label>
                        <select name="superior_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($superiors as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $sel($s['id'], old('superior_id', $emp['superior_id'] ?? '')) ?>>
                                    <?= esc($s['emp_code'] . ' · ' . ($s['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Workflow role(s)</label>
                        <?php $selRoles = array_map('intval', (array) old('flow_role_id', $flowRoleIds ?? [])); ?>
                        <select name="flow_role_id[]" class="form-select" multiple size="3">
                            <?php foreach (($flowRoles ?? []) as $fr): ?>
                                <option value="<?= $fr['id'] ?>" <?= in_array((int) $fr['id'], $selRoles, true) ? 'selected' : '' ?>><?= esc($fr['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Hold Ctrl / Cmd to pick more than one.</p>
                    </div>

                    <!-- shown only for Temporary / Probation (ZKBio Employment) -->
                    <div class="col-md-4 js-employment<?= $empType0 >= 2 ? '' : ' d-none' ?>">
                        <label class="form-label">Effective from</label>
                        <input type="date" name="effective_start" class="form-control" value="<?= esc(old('effective_start', $employment['start_date'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4 js-employment<?= $empType0 >= 2 ? '' : ' d-none' ?>">
                        <label class="form-label">Effective to</label>
                        <input type="date" name="effective_end" class="form-control" value="<?= esc(old('effective_end', $employment['end_date'] ?? '')) ?>">
                    </div>
                </div>
            </div>

            <?= field_image('photo', 'Photo', [
                'col'       => 'col-lg-3',
                'url'       => $photoUrl,
                'removable' => $isEdit,
                'hint'      => 'JPG or PNG, up to 4 MB.',
            ]) ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body pt-2">
        <?php
        $tabList = [
            'private' => 'Private Information', 'device' => 'Device Access', 'attendance' => 'Attendance',
            'access' => 'Access Control', 'app' => 'App Settings', 'payroll' => 'Payroll',
            'leave' => 'Leave Group', 'calendar' => 'Calendar',
        ];
        if (! empty($customAttrs)) {
            $tabList['custom'] = 'Custom Attribute';
        }
        ?>
        <ul class="nav nav-tabs" role="tablist">
            <?php foreach ($tabList as $k => $lbl): ?>
                <li class="nav-item"><button class="nav-link<?= $k === 'private' ? ' active' : '' ?>" type="button"
                    data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>"><?= $lbl ?></button></li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content">

            <!-- Private Information -->
            <div class="tab-pane fade show active" id="tab-private">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Local name</label><input name="nickname" class="form-control" value="<?= $e('nickname') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($genders as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('gender', $emp['gender'] ?? '')) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Birthday</label><input type="date" name="birthday" class="form-control" value="<?= $e('birthday') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="20" value="<?= $e('title') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Religion</label><input name="religion" class="form-control" maxlength="20" value="<?= $e('religion') ?>"></div>
                    <div class="col-md-4"><label class="form-label">National ID</label><input name="national" class="form-control" maxlength="50" value="<?= $e('national') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Contact tel</label><input name="contact_tel" class="form-control" value="<?= $e('contact_tel') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Office tel</label><input name="office_tel" class="form-control" value="<?= $e('office_tel') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= $e('mobile') ?>"></div>
                    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= $e('city') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Postcode</label><input name="postcode" class="form-control" value="<?= $e('postcode') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control<?= err_class('email') ?>" value="<?= $e('email') ?>"><?= err_text('email') ?></div>
                    <div class="col-md-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= $e('address') ?></textarea></div>
                </div>
            </div>

            <!-- Device Access -->
            <div class="tab-pane fade" id="tab-device">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Verify mode</label>
                        <select name="verify_mode" class="form-select">
                            <?php foreach ($verifyMode as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('verify_mode', $emp['verify_mode'] ?? 0)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Device privilege</label>
                        <select name="dev_privilege" class="form-select">
                            <?php foreach ($devPriv as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('dev_privilege', $emp['dev_privilege'] ?? 0)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Card no.</label><input name="card_no" class="form-control mono" value="<?= $e('card_no') ?>"></div>
                    <div class="col-md-4"><label class="form-label">PIN / device password</label><input name="device_password" class="form-control" value="<?= $e('device_password') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Enroll device (SN)</label><input name="enroll_sn" class="form-control mono" value="<?= $e('enroll_sn') ?>"></div>
                </div>
                <p class="form-hint mt-3"><i class="bi bi-info-circle me-1"></i>Fingerprint, palm and face templates are enrolled at the terminal and sync automatically.</p>
            </div>

            <!-- Attendance -->
            <div class="tab-pane fade" id="tab-attendance">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="switch-row"><div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="sw-active"
                                <?= ($old = old('is_active')) !== null ? ($truthy($old) ? 'checked' : '') : ($truthy($emp['is_active'] ?? false) ? 'checked' : '') ?>>
                        </div><label for="sw-active">Active</label></div>
                        <?php foreach ([
                            'enable_attendance' => ['Enable attendance', true], 'enable_schedule' => ['Enable schedule', true],
                            'enable_overtime' => ['Enable overtime', true], 'enable_holiday' => ['Enable holiday', true],
                            'enable_compensatory' => ['Enable compensatory', false],
                        ] as $k => [$lbl, $def]): ?>
                            <div class="switch-row"><div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="<?= $k ?>" value="1" id="sw-<?= $k ?>" <?= $chk($k, $def) ? 'checked' : '' ?>>
                            </div><label for="sw-<?= $k ?>"><?= $lbl ?></label></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Self-service password</label>
                        <input type="text" name="self_password" class="form-control" autocomplete="new-password"
                               placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Portal login password' ?>">
                        <p class="form-hint">Used for the employee self-service portal at <code>/portal</code>.</p>

                        <label class="form-label mt-2">Attendance group</label>
                        <select name="att_group_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($attGroups as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $sel($g['id'], old('att_group_id', $att['group_id'] ?? '')) ?>><?= esc($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (! $isEdit): ?>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-md-4">
                        <label class="form-label">Initial shift <span class="text-muted small">optional</span></label>
                        <select name="shift_id" class="form-select">
                            <option value="">Don't assign yet</option>
                            <?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['alias']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Schedule from</label><input type="date" name="sched_from" class="form-control" value="<?= date('Y-m-01') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Schedule to</label><input type="date" name="sched_to" class="form-control" value="<?= date('Y-12-31') ?>"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Access Control -->
            <div class="tab-pane fade" id="tab-access">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Access group</label>
                        <input name="acc_group" class="form-control mono" maxlength="5" value="<?= $e('acc_group') ?>">
                    </div>
                    <div class="col-md-4"><label class="form-label">Access timezone</label>
                        <input name="acc_timezone" class="form-control mono" maxlength="20" value="<?= $e('acc_timezone') ?>">
                    </div>
                </div>
                <p class="form-hint mt-2"><i class="bi bi-info-circle me-1"></i>Numeric group / timezone IDs pushed to the door controllers. Manage the levels in <strong>Access&nbsp;Control</strong>.</p>
            </div>

            <!-- App Settings -->
            <div class="tab-pane fade" id="tab-app">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">App status</label>
                        <select name="app_status" class="form-select">
                            <?php foreach ($appStatus as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('app_status', $emp['app_status'] ?? 0)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">App role</label>
                        <select name="app_role" class="form-select">
                            <?php foreach ($appRole as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('app_role', $emp['app_role'] ?? 1)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Payroll -->
            <div class="tab-pane fade" id="tab-payroll">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="switch-row"><div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="enable_payroll" value="1" id="sw-payroll"
                                <?= ($op = old('enable_payroll')) !== null ? ($truthy($op) ? 'checked' : '') : ($truthy($emp['enable_payroll'] ?? true) ? 'checked' : '') ?>>
                        </div><label for="sw-payroll">Include this employee in payroll runs</label></div>
                    </div>
                    <div class="col-md-4"><label class="form-label">Payment period</label>
                        <select name="payment_mode" class="form-select">
                            <?php foreach ($payPeriod as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('payment_mode', $pay['payment_mode'] ?? 1)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Payment type</label>
                        <select name="payment_type" class="form-select">
                            <?php foreach ($payType as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sel($k, old('payment_type', $pay['payment_type'] ?? 1)) ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Bank name</label><input name="bank_name" class="form-control" value="<?= esc(old('bank_name', $pay['bank_name'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Bank account</label><input name="bank_account" class="form-control mono" value="<?= esc(old('bank_account', $pay['bank_account'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Agent ID</label><input name="agent_id" class="form-control" value="<?= esc(old('agent_id', $pay['agent_id'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Agent account</label><input name="agent_account" class="form-control mono" value="<?= esc(old('agent_account', $pay['agent_account'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">National number</label><input name="national_num" class="form-control" maxlength="50" value="<?= esc(old('national_num', $pay['national_num'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Payroll number</label><input name="payroll_number" class="form-control" value="<?= esc(old('payroll_number', $pay['personnel_id'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Internal employee number</label><input name="internal_emp_num" class="form-control" maxlength="50" value="<?= esc(old('internal_emp_num', $pay['internal_emp_num'] ?? '')) ?>"></div>
                </div>
            </div>

            <!-- Leave Group -->
            <div class="tab-pane fade" id="tab-leave">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Leave group</label>
                        <select name="leave_group" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($leaveGroups as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $sel($d['id'], old('leave_group', $emp['leave_group'] ?? '')) ?>><?= esc($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <p class="form-hint mb-1"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>Changing the leave group resets this employee's leave-balance rules.</p>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="tab-pane fade" id="tab-calendar">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Holiday calendars</label>
                        <textarea name="calendars" class="form-control" rows="4" placeholder="One calendar name per line"><?= esc(old('calendars', implode("\n", $calendars ?? []))) ?></textarea>
                        <p class="form-hint">Named holiday calendars this employee follows (<code>personnel_employeecalendar</code>). Leave blank for the department default.</p>
                    </div>
                </div>
            </div>

            <?php if (! empty($customAttrs)): ?>
            <!-- Custom Attribute -->
            <div class="tab-pane fade" id="tab-custom">
                <div class="row g-3">
                    <?php foreach ($customAttrs as $ca): $cur = old('attr.' . $ca['id'], $extra[$ca['id']] ?? ''); ?>
                        <div class="col-md-4">
                            <label class="form-label"><?= esc($ca['attr_name']) ?></label>
                            <?php if ((int) $ca['attr_type'] === 2): $opts = array_filter(array_map('trim', explode(',', (string) $ca['attr_value']))); ?>
                                <select name="attr[<?= (int) $ca['id'] ?>]" class="form-select">
                                    <option value="">—</option>
                                    <?php foreach ($opts as $o): ?><option value="<?= esc($o, 'attr') ?>" <?= (string) $cur === $o ? 'selected' : '' ?>><?= esc($o) ?></option><?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input name="attr[<?= (int) $ca['id'] ?>]" class="form-control" value="<?= esc($cur) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= $isEdit ? 'Save changes' : 'Create employee' ?></button>
        <a href="<?= route_to('employees') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
<?= form_close() ?>

<?php if ($isEdit): ?>
<div class="card mt-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <div class="flex-grow-1"><strong>Employee code</strong><div class="small text-muted">Current: <span class="mono"><?= esc($emp['emp_code']) ?></span> — changing it updates the device PIN and all history.</div></div>
    <?= form_open(route_to('employee.change_code', $emp['id']), ['class' => 'd-flex gap-2', 'data-confirm' => 'Change this employee\'s code / device PIN?']) ?>
        <input type="text" name="new_code" class="form-control form-control-sm" style="width:10rem" placeholder="New code" maxlength="30" pattern="[A-Za-z0-9_-]{1,30}" required>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>Change code</button>
    <?= form_close() ?>
</div></div>

<div class="card mt-3 border-danger-subtle"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <div class="flex-grow-1"><strong>Danger zone</strong><div class="small text-muted">Resign keeps history; delete only works with zero punches.</div></div>
    <?= form_open(route_to('employee.resign', $emp['id']), ['class' => 'd-inline', 'data-confirm' => 'Mark as resigned?']) ?>
        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-person-dash me-1"></i>Resign</button>
    <?= form_close() ?>
    <?= form_open(route_to('employee.reinstate', $emp['id']), ['class' => 'd-inline']) ?>
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reinstate</button>
    <?= form_close() ?>
    <?= form_open(route_to('employee.delete', $emp['id']), ['class' => 'd-inline', 'data-confirm' => 'Permanently delete this employee?']) ?>
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
    <?= form_close() ?>
</div></div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// effective dates show only for Temporary / Probation
(function () {
    const et = document.querySelector('select[name="emp_type"]');
    if (!et) return;
    const sync = () => document.querySelectorAll('.js-employment')
        .forEach(el => el.classList.toggle('d-none', !(parseInt(et.value, 10) >= 2)));
    et.addEventListener('change', sync);
    sync();
})();
// (photo preview + drag-drop handled by TT.enhance → .tt-upload)
</script>
<?= $this->endSection() ?>
