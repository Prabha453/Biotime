<?php
/** @var string $title */
$active = $active ?? '';
$sub    = $sub ?? '';
$role   = session()->get('userRole') ?? 'user';
$uname  = session()->get('userName') ?? 'User';

$tab = $tab ?? '';   // several views pass a tab key; sub-menu items match it

// node types:
//   ['group', 'Label']
//   ['item',  activeKey, 'Label', 'bi-icon', href]
//   ['tree',  'Label', 'bi-icon', [coarseActiveKeys], [ ['Child', href, childKey], … ]]
// Ordered to match ZKBioTime's module menu: Home · Personnel · Device ·
// Attendance · Payroll · Access Control · Visitor · Meeting · EP · Mobile · System
$nav = [
    ['group', 'Home'],
    ['item', 'dashboard', 'Dashboard', 'grid-1x2', route_to('dashboard')],

    ['group', 'Personnel'],
    ['tree', 'Organization', 'diagram-3', ['org', 'companies', 'departments', 'positions', 'areas'], [
        ['Company', route_to('org.tab', 'companies'), 'companies'],
        ['Department', route_to('org'), 'departments'],
        ['Position', route_to('org.tab', 'positions'), 'positions'],
        ['Area', route_to('org.tab', 'areas'), 'areas'],
    ]],
    ['tree', 'Employee', 'people', ['employees', 'resign'], [
        ['Employee', route_to('employees'), 'employees'],
        ['Resign', route_to('resign'), 'resign'],
    ]],
    ['tree', 'Workflow', 'signpost-split', ['workflow'], [
        ['Workflow Role', route_to('workflow.tab', 'roles'), 'roles'],
        ['Workflow Builder', route_to('workflow'), 'flows'],
    ]],
    ['tree', 'Configurations', 'gear-wide-connected', ['cert.types', 'psnl.attr', 'psnl.config'], [
        ['Document', route_to('certifications.tab', 'types'), 'cert.types'],
        ['Custom Attribute', route_to('personnelextra.tab', 'attributes'), 'psnl.attr'],
        ['Configuration', route_to('personnelextra.tab', 'config'), 'psnl.config'],
    ]],

    ['group', 'Device'],
    ['tree', 'Device', 'hdd-network', ['devices', 'devlog.command'], [
        ['Device', route_to('devices'), 'devices'],
        ['Device Command', route_to('devlogs'), 'devlog.command'],
    ]],
    ['tree', 'Message', 'chat-dots', ['dev.msg.public', 'dev.msg.private'], [
        ['Public', route_to('devmsg'), 'dev.msg.public'],
        ['Private', route_to('devmsg.tab', 'private'), 'dev.msg.private'],
    ]],
    ['tree', 'Data', 'database', ['dev.data.employee', 'workcodes', 'devdata.biotmp', 'devdata.biophoto', 'punchlogs'], [
        ['Employee', route_to('devemp'), 'dev.data.employee'],
        ['Work Code', route_to('workcodes'), 'workcodes'],
        ['Bio-Template', route_to('devdata'), 'devdata.biotmp'],
        ['Bio-Photo', route_to('devdata.tab', 'biophoto'), 'devdata.biophoto'],
        ['Transaction', route_to('punchlogs'), 'punchlogs'],
    ]],
    ['tree', 'Log', 'journal-text', ['devlog.devlog', 'devlog.errorcmd', 'devlog.upload', 'devlog.cmdlog'], [
        ['Operation Log', route_to('devlogs.tab', 'devlog'), 'devlog.devlog'],
        ['Error Log', route_to('devlogs.tab', 'errorcmd'), 'devlog.errorcmd'],
        ['Upload Log', route_to('devlogs.tab', 'upload'), 'devlog.upload'],
        ['Command Log', route_to('devlogs.tab', 'cmdlog'), 'devlog.cmdlog'],
    ]],
    ['tree', 'Mobile App', 'phone', ['announcements', 'notifications'], [
        ['Announcement', route_to('mobile.tab', 'announcements'), 'announcements'],
        ['Notice', route_to('mobile.tab', 'notifications'), 'notifications'],
    ]],
    ['tree', 'Geo-fence', 'geo-alt', ['locations', 'assignments'], [
        ['Location Setup', route_to('mobile'), 'locations'],
        ['Employee Setup', route_to('mobile.tab', 'assignments'), 'assignments'],
    ]],
    ['tree', 'Configurations', 'gear-wide-connected', ['deviceconfig'], [
        ['Configurations', route_to('deviceconfig'), 'deviceconfig'],
    ]],

    ['group', 'Attendance'],
    ['item', 'att.workbench', 'Setting Guide', 'compass', route_to('att.workbench')],
    ['tree', 'Setup', 'sliders2', ['att.paycode', 'att.attcode', 'att.attgroup'], [
        ['Pay Code', route_to('attsetup.paycodes'), 'att.paycode'],
        ['Attendance Code', route_to('attsetup.attcodes'), 'att.attcode'],
        ['Attendance Group', route_to('attsetup.groups'), 'att.attgroup'],
    ]],
    ['tree', 'Rule', 'ui-checks-grid', ['attconfig'], [
        ['Global Rule', route_to('attconfig'), 'attconfig'],
    ]],
    ['tree', 'Shift', 'calendar-week', ['att.breaktime', 'att.timetable', 'att.shift'], [
        ['Break Time', route_to('attshift.breaks'), 'att.breaktime'],
        ['Timetable', route_to('attshift.timetables'), 'att.timetable'],
        ['Shift', route_to('attshift.shifts'), 'att.shift'],
    ]],
    ['tree', 'Schedule Assignment', 'diagram-3', ['schedules.personal', 'schedules.department', 'schedules.company', 'schedules.temporary'], [
        ['Employee Schedule', route_to('schedules.personal'), 'schedules.personal'],
        ['Department Schedule', route_to('schedules.department'), 'schedules.department'],
        ['Company Schedule', route_to('schedules.company'), 'schedules.company'],
        ['Temporary Schedule', route_to('schedules.temporary'), 'schedules.temporary'],
    ]],
    ['tree', 'Approvals', 'check2-square', ['requests'], [
        ['Manual Log', route_to('requests.tab', 'manual'), 'manual'],
        ['Leave', route_to('requests'), 'leave'],
        ['Overtime', route_to('requests.tab', 'overtime'), 'overtime'],
        ['Training', route_to('requests.tab', 'training'), 'training'],
        ['Adjust Schedule', route_to('requests.tab', 'schedule'), 'schedule'],
    ]],
    ['tree', 'Benefit Management', 'umbrella', ['leavegroups', 'holidays'], [
        ['Leave Group', route_to('leavegroups'), 'leavegroups'],
        ['Holiday', route_to('holidays'), 'holidays'],
    ]],
    ['tree', 'Transaction Report', 'clock-history', ['reports.transactions', 'punchcard', 'reports.firstLast', 'fio'], [
        ['Transaction', route_to('reports.transactions'), 'reports.transactions'],
        ['Time Card', route_to('reports.punchCard'), 'punchcard'],
        ['First & Last', route_to('reports.firstLast'), 'reports.firstLast'],
        ['First In Last Out', route_to('reports.firstInLastOut'), 'fio'],
    ]],
    ['tree', 'Daily Report', 'calendar-check', ['timecard', 'worked', 'reports.overtime', 'reports.late', 'early', 'reports.absent', 'reports.exception', 'scheduled_log', 'paired_punch', 'multi_work', 'multi_break'], [
        ['Total Time Card', route_to('reports.timeCard'), 'timecard'],
        ['Worked Hrs', route_to('reports.dailyWorked'), 'worked'],
        ['Overtime Report', route_to('reports.overtime'), 'reports.overtime'],
        ['Late In Report', route_to('reports.late'), 'reports.late'],
        ['Early Out Report', route_to('reports.earlyOut'), 'early'],
        ['Absent Report', route_to('reports.absent'), 'reports.absent'],
        ['Exception Report', route_to('reports.exception'), 'reports.exception'],
        ['Scheduled Log', route_to('reports.scheduledLog'), 'scheduled_log'],
        ['Paired Punch', route_to('reports.pairedPunch'), 'paired_punch'],
        ['Multiple Worked Hrs', route_to('reports.multipleWorkHours'), 'multi_work'],
        ['Multiple Break Time', route_to('reports.multipleBreakTime'), 'multi_break'],
    ]],
    ['tree', 'Monthly Report', 'calendar-month', ['reports.monthlyStatus', 'reports.monthlyCheckInOut', 'reports.monthlyWorkedHrs', 'reports.monthlyOvertime', 'reports.monthlyAbsence'], [
        ['Monthly Status', route_to('reports.monthlyStatus'), 'reports.monthlyStatus'],
        ['Monthly Check-in&out', route_to('reports.monthlyCheckInOut'), 'reports.monthlyCheckInOut'],
        ['Monthly Worked Hrs', route_to('reports.monthlyWorkedHrs'), 'reports.monthlyWorkedHrs'],
        ['Monthly Overtime', route_to('reports.monthlyOvertime'), 'reports.monthlyOvertime'],
        ['Monthly Absence', route_to('reports.monthlyAbsence'), 'reports.monthlyAbsence'],
    ]],
    ['tree', 'Summary Report', 'bar-chart', ['summary.employeeAttendance', 'summary.employeeOvertime', 'summary.employeeLeave', 'summary.departmentAttendance', 'summary.departmentOvertime', 'summary.groupAttendance', 'summary.groupOvertime', 'reports.leaveBalance'], [
        ['Employee Attendance', route_to('summary.employeeAttendance'), 'summary.employeeAttendance'],
        ['Employee Overtime', route_to('summary.employeeOvertime'), 'summary.employeeOvertime'],
        ['Employee Leave', route_to('summary.employeeLeave'), 'summary.employeeLeave'],
        ['Department Attendance', route_to('summary.departmentAttendance'), 'summary.departmentAttendance'],
        ['Department Overtime', route_to('summary.departmentOvertime'), 'summary.departmentOvertime'],
        ['Group Attendance', route_to('summary.groupAttendance'), 'summary.groupAttendance'],
        ['Group Overtime', route_to('summary.groupOvertime'), 'summary.groupOvertime'],
        ['Leave Balance Summary', route_to('reports.leaveBalance'), 'reports.leaveBalance'],
    ]],
    ['tree', 'Configurations', 'gear-wide-connected', ['att.calc', 'att.reportsetting'], [
        ['Calculation', route_to('attcalc'), 'att.calc'],
        ['Configuration', route_to('attconfig.tab', 'reporting'), 'att.reportsetting'],
    ]],

    ['group', 'Payroll'],
    ['tree', 'Employee Profile', 'person-vcard', ['payroll'], [
        ['Employee Profile', route_to('payroll.bank'), 'bank'],
    ]],
    ['tree', 'Formula', 'calculator', ['payrollconfig'], [
        ['Overtime Formula', route_to('payrollconfig.formula', 'overtime'), 'overtime'],
        ['Exception Formula', route_to('payrollconfig.formula', 'exception'), 'exception'],
        ['Leave Formula', route_to('payrollconfig.formula', 'leave'), 'leave'],
        ['Increment Formula', route_to('payrollconfig.formula', 'increase'), 'increase'],
        ['Deduction Formula', route_to('payrollconfig.formula', 'deduction'), 'deduction'],
    ]],
    ['tree', 'Extra Funds', 'plus-slash-minus', ['payroll'], [
        ['Extra Increase', route_to('payroll.increases'), 'increases'],
        ['Extra Deduction', route_to('payroll.deductions'), 'deductions'],
    ]],
    ['tree', 'Payroll Structure', 'diagram-3', ['payroll'], [
        ['Payroll Structure', route_to('payroll.structures'), 'salary'],
    ]],
    ['tree', 'Loan', 'cash-coin', ['payroll'], [
        ['Loan', route_to('payroll.loans'), 'loans'],
    ]],
    ['tree', 'Salary Advance', 'wallet2', ['payroll'], [
        ['Salary Advance', route_to('payroll.advances'), 'advances'],
    ]],
    ['tree', 'Reimbursement', 'receipt', ['payroll'], [
        ['Reimbursement', route_to('payroll.reimbursements'), 'reimbursements'],
    ]],
    ['tree', 'Calculation', 'calculator-fill', ['payroll'], [
        ['Salary Calculation', route_to('payroll'), 'payslips'],
    ]],
    ['tree', 'Report', 'file-earmark-bar-graph', ['payroll'], [
        ['Salary Structure', route_to('payroll.reports.tab', 'structure'), 'reports'],
        ['Increases', route_to('payroll.reports.tab', 'increases'), 'reports'],
        ['Deductions', route_to('payroll.reports.tab', 'deductions'), 'reports'],
        ['Salary Details', route_to('payroll.reports.tab', 'details'), 'reports'],
        ['Attendance Parameters', route_to('payroll.reports.tab', 'attendance'), 'reports'],
    ]],

    ['group', 'Access Control'],
    ['tree', 'Device', 'hdd-network', ['access'], [
        ['Device', route_to('access.doors'), 'doors'],
        ['Device Command', route_to('access.commands'), 'commands'],
    ]],
    ['tree', 'Access Control', 'shield-lock', ['access'], [
        ['Time Period', route_to('access.timezones'), 'timezones'],
        ['Holiday', route_to('access.holidays'), 'accholidays'],
        ['Groups', route_to('access.levels'), 'levels'],
        ['Combination', route_to('access.combinations'), 'combinations'],
        ['Privilege', route_to('access.privileges'), 'privileges'],
    ]],

    ['group', 'Visitor'],
    ['tree', 'Visitor', 'person-badge', ['visitors'], [
        ['Registration', route_to('visitors'), 'visitors'],
        ['Parameters', route_to('visitor.settings'), 'visitors'],
    ]],

    ['group', 'Meeting'],
    ['tree', 'Meeting', 'people-fill', ['meetings'], [
        ['Meeting Room', route_to('meetings'), 'meetings'],
        ['Meeting', route_to('meetings.bookings'), 'meetings'],
    ]],

    ['group', 'EP'],
    ['item', 'ep', 'Temperature Screening', 'thermometer-half', route_to('ep')],

    ['group', 'System'],
    ['tree', 'User', 'people-fill', ['admins', 'system', 'notifications'], [
        ['User Group', route_to('system'), 'system'],
        ['User', route_to('admins'), 'admins'],
        ['API Permissions', route_to('system.tab', 'api'), 'system'],
        ['Notification', route_to('notifications'), 'notifications'],
    ]],
    ['tree', 'Database', 'hdd-stack', ['system'], [
        ['Backup', route_to('system.tab', 'backups'), 'system'],
    ]],
    ['tree', 'Integration', 'share', ['system'], [
        ['Import / Export Tasks', route_to('system.tab', 'tasks'), 'system'],
    ]],
    ['tree', 'Log', 'journal-text', ['system.log'], [
        ['User Log', route_to('system.log.tab', 'activity'), 'system.log'],
        ['System Log', route_to('system.log'), 'system.log'],
    ]],
    ['tree', 'Configuration', 'gear-wide-connected', ['settings', 'email', 'system'], [
        ['System Setting', route_to('settings'), 'settings'],
        ['Email Template', route_to('email'), 'email'],
        ['Alert & Security Settings', route_to('system.tab', 'security'), 'system'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="nav-active" content="<?= esc($active, 'attr') ?>">
    <meta name="nav-tab" content="<?= esc($tab, 'attr') ?>">
    <meta name="turbo-cache-control" content="no-preview">
    <script>(function(){try{var t=localStorage.getItem('tt-theme');if(t){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
    <title><?= esc($title ?? 'Admin') ?> &middot; TimeTrack</title>
    <?= $this->include('partials/head_libs') ?>
    <link href="<?= base_url('assets/css/app.css') . '?v=' . @filemtime(FCPATH . 'assets/css/app.css') ?>" rel="stylesheet">
    <script src="<?= base_url('assets/js/tt.js') . '?v=' . @filemtime(FCPATH . 'assets/js/tt.js') ?>"></script>
    <script src="<?= base_url('assets/js/tt-grid.js') . '?v=' . @filemtime(FCPATH . 'assets/js/tt-grid.js') ?>"></script>
    <script src="<?= base_url('assets/vendor/turbo/turbo.umd.min.js') ?>"></script>
</head>
<body<?= session()->getFlashdata('reopen') ? ' data-reopen="' . esc(session()->getFlashdata('reopen'), 'attr') . '"' : '' ?>>
<div class="layout">
    <header class="topbar">
        <a href="<?= route_to('dashboard') ?>" class="header-brand">
            <span class="brand-dot"><i class="bi bi-fingerprint"></i></span>
            <span>TimeTrack</span>
        </a>
        <div class="topbar-inner">
            <button class="icon-btn d-xxl-none" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list"></i></button>
            <form class="header-search" method="get" action="<?= route_to('employees') ?>" role="search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search employees&hellip;" aria-label="Search employees">
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="icon-btn" data-bs-toggle="dropdown" title="Quick add" aria-label="Quick add"><i class="bi bi-plus-lg"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?= route_to('employee.new') ?>"><i class="bi bi-person-plus me-2"></i>New employee</a></li>
                        <li><a class="dropdown-item" href="<?= route_to('requests') ?>"><i class="bi bi-inbox me-2"></i>New request</a></li>
                        <li><a class="dropdown-item" href="<?= route_to('holidays') ?>"><i class="bi bi-calendar-event me-2"></i>Add holiday</a></li>
                        <li><a class="dropdown-item" href="<?= route_to('punchlogs') ?>"><i class="bi bi-fingerprint me-2"></i>Record punch</a></li>
                    </ul>
                </div>
                <button class="icon-btn d-none d-md-grid" id="fsToggle" type="button" title="Fullscreen" aria-label="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                <?php $notes = \App\Libraries\Notify::recent(8); $noteUnread = 0; foreach ($notes as $n) { $noteUnread += $n['unread'] ? 1 : 0; } ?>
                <div class="dropdown">
                    <button class="icon-btn position-relative" data-bs-toggle="dropdown" title="Notifications" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($noteUnread): ?><span class="note-dot"><?= $noteUnread > 9 ? '9+' : $noteUnread ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow note-menu">
                        <div class="note-head">
                            <strong>Notifications</strong>
                            <?php if ($noteUnread): ?>
                                <?= form_open(route_to('notifications.readall'), ['class' => 'd-inline']) ?>
                                    <button class="btn btn-link btn-sm p-0">Mark all read</button>
                                <?= form_close() ?>
                            <?php endif; ?>
                        </div>
                        <?php if (! $notes): ?>
                            <div class="note-empty">You're all caught up.</div>
                        <?php else: foreach ($notes as $n): ?>
                            <?= form_open(route_to('notification.read', $n['id']), ['class' => 'note-item' . ($n['unread'] ? ' is-unread' : '')]) ?>
                                <?php if ($n['link']): ?><input type="hidden" name="link" value="<?= esc($n['link'], 'attr') ?>"><?php endif; ?>
                                <button type="submit">
                                    <span class="note-text"><?= esc($n['content']) ?></span>
                                    <span class="note-time"><?= esc($n['send_time']) ?></span>
                                </button>
                            <?= form_close() ?>
                        <?php endforeach; endif; ?>
                        <a class="note-foot" href="<?= route_to('notifications') ?>">View all</a>
                    </div>
                </div>
                <button class="icon-btn" id="themeToggle" type="button" title="Toggle theme" aria-label="Toggle light / dark theme">
                    <i class="bi bi-moon-stars" data-icon-dark></i><i class="bi bi-sun d-none" data-icon-light></i>
                </button>
                <div class="dropdown">
                    <button class="user-chip dropdown-toggle" data-bs-toggle="dropdown">
                        <span class="avatar-sm"><?= esc(strtoupper(substr($uname, 0, 1))) ?></span>
                        <span class="uc-meta d-none d-sm-flex">
                            <strong><?= esc($uname) ?></strong>
                            <small><?= esc($role) ?></small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted"><?= esc(session()->get('userEmail')) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= route_to('profile') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li><a class="dropdown-item" href="<?= route_to('settings') ?>"><i class="bi bi-sliders me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= route_to('logout') ?>" data-turbo="false"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar" data-turbo-permanent>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $n): ?>
                <?php if ($n[0] === 'group'): ?>
                    <p class="nav-group"><?= esc($n[1]) ?></p>
                <?php elseif ($n[0] === 'item'): ?>
                    <a class="nav-item<?= $active === $n[1] ? ' is-active' : '' ?>" href="<?= $n[4] ?>" data-key="<?= esc($n[1], 'attr') ?>">
                        <i class="bi bi-<?= $n[3] ?>"></i><span><?= esc($n[2]) ?></span>
                    </a>
                <?php else:
                    [, $label, $icon, $matches, $children] = $n;
                    $childActive = false;
                    foreach ($children as $c) {
                        if ($active === $c[2] || ($tab !== '' && $tab === $c[2])) { $childActive = true; break; }
                    }
                    $open = $childActive || in_array($active, $matches, true);
                    ?>
                    <a class="nav-item nav-tree<?= $open ? ' is-open is-parent-active' : '' ?>" href="javascript:void(0)" role="button" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                        <i class="bi bi-<?= $icon ?>"></i><span><?= esc($label) ?></span><i class="bi bi-chevron-right nav-caret"></i>
                    </a>
                    <ul class="nav-sub">
                        <?php foreach ($children as $c): $ca = $active === $c[2] || ($tab !== '' && $tab === $c[2]); ?>
                            <li><a class="nav-item<?= $ca ? ' is-active' : '' ?>" href="<?= $c[1] ?>" data-key="<?= esc($c[2], 'attr') ?>"><span><?= esc($c[0]) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="content">

        <main class="page-body">
            <?php $vErrors = session()->getFlashdata('errors') ?? []; ?>
            <?php if ($vErrors): ?>
                <div class="alert alert-danger">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1 ps-3"><?php foreach ($vErrors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?= $this->renderSection('content') ?>
        </main>

        <footer class="app-foot">
            <span>&copy; <?= date('Y') ?> <a href="<?= route_to('dashboard') ?>">TimeTrack</a> &mdash; ZKTeco biometric attendance</span>
            <span>CodeIgniter <?= \CodeIgniter\CodeIgniter::CI_VERSION ?> &middot; PostgreSQL</span>
        </footer>
    </div>
</div>

<div class="toast-stack">
<?php foreach (['success' => 'ok', 'error' => 'err', 'warning' => 'warn'] as $k => $cls): ?>
    <?php if ($m = session()->getFlashdata($k)): ?>
        <div class="toast-msg <?= $cls ?>" style="--dur:4s">
            <i class="bi bi-<?= $cls === 'ok' ? 'check-circle-fill' : ($cls === 'err' ? 'x-circle-fill' : 'exclamation-triangle-fill') ?>"></i>
            <div class="toast-body">
                <p class="toast-title"><?= $cls === 'ok' ? 'Success' : ($cls === 'err' ? 'Error' : 'Warning') ?></p>
                <p class="toast-text"><?= esc($m) ?></p>
            </div>
            <button class="toast-close" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>
            <div class="toast-progress" style="animation-duration: var(--dur)"></div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>

<?= $this->renderSection('scripts') ?>
</body>
</html>
