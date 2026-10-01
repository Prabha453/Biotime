<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Auth::index');
$routes->get('login', 'Auth::index', ['as' => 'login']);
$routes->post('login', 'Auth::attemptLogin');
$routes->get('logout', 'Auth::logout', ['as' => 'logout']);

$routes->group('admin', ['filter' => ['auth', 'role']], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index');
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'dashboard']);
    $routes->get('dashboard/live', 'Dashboard::live', ['as' => 'dashboard.live']);

    // ---- Employees ----
    $routes->get('employees', 'Employees::index', ['as' => 'employees']);
    $routes->get('employees/new', 'Employees::form', ['as' => 'employee.new']);
    $routes->post('employees/bulk', 'Employees::bulk', ['as' => 'employee.bulk']);
    $routes->get('employees/(:num)', 'Employees::view/$1', ['as' => 'employee.view']);
    $routes->get('employees/(:num)/edit', 'Employees::form/$1', ['as' => 'employee.edit']);
    $routes->post('employees/save', 'Employees::save', ['as' => 'employee.save']);
    $routes->post('employees/(:num)/change-code', 'Employees::changeCode/$1', ['as' => 'employee.change_code']);
    $routes->post('employees/(:num)/resign', 'Employees::resign/$1', ['as' => 'employee.resign']);
    $routes->post('employees/(:num)/reinstate', 'Employees::reinstate/$1', ['as' => 'employee.reinstate']);

    // ---- Personnel bulk import (CSV + ZIP) ----
    $routes->get('history/feed', 'History::feed', ['as' => 'history.feed']);
    $routes->get('exports/(:segment)', 'Exports::run/$1', ['as' => 'exports.run']);

    $routes->get('imports', 'Imports::index', ['as' => 'imports']);
    $routes->get('imports/(:segment)/template', 'Imports::template/$1', ['as' => 'imports.template']);
    $routes->get('imports/(:segment)', 'Imports::index/$1', ['as' => 'imports.tab']);
    $routes->post('imports/(:segment)/run', 'Imports::run/$1', ['as' => 'imports.run']);

    // ---- Resign register ----
    $routes->get('resign', 'Resign::index', ['as' => 'resign']);
    $routes->post('resign/save', 'Resign::save', ['as' => 'resign.save']);
    $routes->post('resign/(:num)/reinstate', 'Resign::reinstate/$1', ['as' => 'resign.reinstate']);
    $routes->post('employees/(:num)/delete', 'Employees::delete/$1', ['as' => 'employee.delete']);

    // ---- Personnel setup (custom fields · resignation register) ----
    $routes->get('personnel-setup', 'PersonnelExtra::index', ['as' => 'personnelextra']);
    $routes->get('personnel-setup/(:segment)', 'PersonnelExtra::index/$1', ['as' => 'personnelextra.tab']);
    $routes->post('personnel-setup/attributes/save', 'PersonnelExtra::saveField', ['as' => 'personnelextra.field.save']);
    $routes->post('personnel-setup/attributes/(:num)/delete', 'PersonnelExtra::deleteField/$1', ['as' => 'personnelextra.field.delete']);
    $routes->post('personnel-setup/attributes/bulk-delete', 'PersonnelExtra::bulkDeleteField', ['as' => 'personnelextra.field.bulk_delete']);
    $routes->post('personnel-setup/config/save', 'PersonnelExtra::saveConfig', ['as' => 'personnelextra.config.save']);

    $routes->get('leave-balance', 'EmployeeLeaveBalance::index', ['as' => 'leavebalance']);
    $routes->post('leave-balance/save', 'EmployeeLeaveBalance::save', ['as' => 'leavebalance.save']);
    $routes->post('leave-balance/seed', 'EmployeeLeaveBalance::seed', ['as' => 'leavebalance.seed']);
    $routes->post('leave-balance/bulk-delete', 'EmployeeLeaveBalance::bulkDelete', ['as' => 'leavebalance.bulk_delete']);
    $routes->post('leave-balance/(:num)/delete', 'EmployeeLeaveBalance::delete/$1', ['as' => 'leavebalance.delete']);

    // ---- Device ▸ Configurations (iclock_devicemoduleconfig singleton) ----
    $routes->get('device-config', 'DeviceConfig::index', ['as' => 'deviceconfig']);
    $routes->post('device-config/save', 'DeviceConfig::saveConfig', ['as' => 'deviceconfig.config.save']);

    // ---- Device ▸ Message (public = per-device · private = per-employee) ----
    $routes->get('device-messages', 'DeviceMessages::index', ['as' => 'devmsg']);
    $routes->get('device-messages/(:segment)', 'DeviceMessages::index/$1', ['as' => 'devmsg.tab']);
    $routes->post('device-messages/(:segment)/send', 'DeviceMessages::send/$1', ['as' => 'devmsg.send']);
    $routes->post('device-messages/(:segment)/(:num)/delete', 'DeviceMessages::delete/$1/$2', ['as' => 'devmsg.delete']);

    // ---- Devices ----
    $routes->get('devices', 'Devices::index', ['as' => 'devices']);
    $routes->post('devices/save', 'Devices::save', ['as' => 'device.save']);
    $routes->post('devices/bulk-delete', 'Devices::bulkDelete', ['as' => 'device.bulk_delete']);
    $routes->post('devices/new-area', 'Devices::newArea', ['as' => 'device.new_area']);
    $routes->post('devices/command', 'Devices::command', ['as' => 'device.command']);
    $routes->post('devices/clear-pending', 'Devices::clearPending', ['as' => 'device.clear_pending']);
    $routes->get('devices/(:num)', 'Devices::view/$1', ['as' => 'device.view']);
    $routes->post('devices/(:num)/save', 'Devices::save/$1', ['as' => 'device.save_id']);
    $routes->post('devices/(:num)/command', 'Devices::queueCommand/$1', ['as' => 'device.command_one']);
    $routes->post('devices/(:num)/command/(:num)/cancel', 'Devices::cancelCommand/$1/$2', ['as' => 'device.command.cancel']);

    // ---- Device ▸ Log (command / command-log / device-log / upload / error) ----
    $routes->get('device-logs', 'DeviceLogs::index', ['as' => 'devlogs']);
    $routes->get('device-logs/(:segment)', 'DeviceLogs::index/$1', ['as' => 'devlogs.tab']);
    $routes->post('device-logs/(:segment)/clear', 'DeviceLogs::clear/$1', ['as' => 'devlogs.clear']);

    // ---- Device ▸ Data (biometric templates · bio-photo) ----
    $routes->get('device-data', 'DeviceData::index', ['as' => 'devdata']);
    $routes->get('device-data/(:segment)', 'DeviceData::index/$1', ['as' => 'devdata.tab']);
    $routes->post('device-data/(:segment)/bulk-delete', 'DeviceData::bulkDelete/$1', ['as' => 'devdata.bulk_delete']);

    // ---- Device ▸ Data ▸ Employee (device employee / biometric enrolment) ----
    $routes->get('device-employees', 'DeviceEmployee::index', ['as' => 'devemp']);
    $routes->post('device-employees/area-transfer', 'DeviceEmployee::areaTransfer', ['as' => 'devemp.area_transfer']);
    $routes->post('device-employees/transfer', 'DeviceEmployee::transfer', ['as' => 'devemp.transfer']);
    $routes->post('device-employees/(:num)/edit', 'DeviceEmployee::edit/$1', ['as' => 'devemp.edit']);

    // ---- Punch logs / corrections ----
    $routes->get('punch-logs', 'PunchLogs::index', ['as' => 'punchlogs']);
    $routes->post('punch-logs/add', 'PunchLogs::add', ['as' => 'punch.add']);
    $routes->post('punch-logs/(:num)/delete', 'PunchLogs::delete/$1', ['as' => 'punch.delete']);

    // ---- Attendance ----
    $routes->get('attendance/daily', 'Attendance::daily', ['as' => 'att.daily']);
    $routes->get('attendance/daily/export', 'Attendance::dailyExport', ['as' => 'att.daily.export']);
    $routes->get('attendance/monthly', 'Attendance::monthly', ['as' => 'att.monthly']);
    $routes->get('attendance/monthly/export', 'Attendance::monthlyExport', ['as' => 'att.monthly.export']);
    $routes->get('attendance/timesheet/(:num)', 'Attendance::timesheet/$1', ['as' => 'att.timesheet']);

    // ---- Schedules (one real page per type, per ZKBioTime's own menu) ----
    $routes->get('schedules', 'Schedules::personal', ['as' => 'schedules']);
    $routes->get('schedules/personal', 'Schedules::personal', ['as' => 'schedules.personal']);
    $routes->get('schedules/department', 'Schedules::department', ['as' => 'schedules.department']);
    $routes->get('schedules/company', 'Schedules::company', ['as' => 'schedules.company']);
    $routes->get('schedules/temporary', 'Schedules::temporary', ['as' => 'schedules.temporary']);
    $routes->post('schedules/assign', 'Schedules::assign', ['as' => 'schedule.assign']);
    $routes->post('schedules/(:num)/delete', 'Schedules::delete/$1', ['as' => 'schedule.delete']);
    $routes->post('schedules/dept', 'Schedules::deptAssign', ['as' => 'schedule.dept']);
    $routes->post('schedules/dept/(:num)/delete', 'Schedules::deptDelete/$1', ['as' => 'schedule.dept.delete']);
    $routes->post('schedules/company', 'Schedules::companyAssign', ['as' => 'schedule.company']);
    $routes->post('schedules/company/(:num)/delete', 'Schedules::companyDelete/$1', ['as' => 'schedule.company.delete']);
    $routes->post('schedules/temp', 'Schedules::tempAssign', ['as' => 'schedule.temp']);
    $routes->post('schedules/temp/(:num)/delete', 'Schedules::tempDelete/$1', ['as' => 'schedule.temp.delete']);

    // ---- Attendance ▸ Shift  (Break Time · Timetable · Shift) ----
    $routes->get('att/break-time', 'AttShift::breaks', ['as' => 'attshift.breaks']);
    $routes->post('att/break-time/save', 'AttShift::saveBreak', ['as' => 'attshift.breaks.save']);
    $routes->post('att/break-time/bulk-delete', 'AttShift::bulkDeleteBreak', ['as' => 'attshift.breaks.bulk_delete']);
    $routes->post('att/break-time/(:num)/delete', 'AttShift::deleteBreak/$1', ['as' => 'attshift.breaks.delete']);

    $routes->get('att/timetable', 'AttShift::timetables', ['as' => 'attshift.timetables']);
    $routes->post('att/timetable/save', 'AttShift::saveTimetable', ['as' => 'attshift.timetables.save']);
    $routes->post('att/timetable/bulk-delete', 'AttShift::bulkDeleteTimetable', ['as' => 'attshift.timetables.bulk_delete']);
    $routes->post('att/timetable/(:num)/delete', 'AttShift::deleteTimetable/$1', ['as' => 'attshift.timetables.delete']);

    $routes->get('att/shift', 'AttShift::shifts', ['as' => 'attshift.shifts']);
    $routes->post('att/shift/save', 'AttShift::saveShift', ['as' => 'attshift.shifts.save']);
    $routes->post('att/shift/bulk-delete', 'AttShift::bulkDeleteShift', ['as' => 'attshift.shifts.bulk_delete']);
    $routes->post('att/shift/(:num)/delete', 'AttShift::deleteShift/$1', ['as' => 'attshift.shifts.delete']);

    // legacy shifts route → new Timetable page
    $routes->get('shifts', static fn () => redirect()->to('/admin/att/timetable'), ['as' => 'shifts']);

    // ---- Attendance ▸ Setting Guide + Setup ----
    $routes->get('att/workbench', 'AttSetup::workbench', ['as' => 'att.workbench']);
    $routes->get('att/pay-code', 'AttSetup::paycodes', ['as' => 'attsetup.paycodes']);
    $routes->post('att/pay-code/save', 'AttSetup::savePaycode', ['as' => 'attsetup.paycodes.save']);
    $routes->post('att/pay-code/(:num)/delete', 'AttSetup::deletePaycode/$1', ['as' => 'attsetup.paycodes.delete']);
    $routes->get('att/attendance-code', 'AttSetup::attcodes', ['as' => 'attsetup.attcodes']);
    $routes->post('att/attendance-code/save', 'AttSetup::saveAttcode', ['as' => 'attsetup.attcodes.save']);
    $routes->post('att/attendance-code/(:num)/delete', 'AttSetup::deleteAttcode/$1', ['as' => 'attsetup.attcodes.delete']);
    $routes->get('att/group', 'AttSetup::groups', ['as' => 'attsetup.groups']);
    $routes->post('att/group/save', 'AttSetup::saveGroup', ['as' => 'attsetup.groups.save']);
    $routes->post('att/group/(:num)/delete', 'AttSetup::deleteGroup/$1', ['as' => 'attsetup.groups.delete']);

    // ---- Attendance ▸ Configurations ▸ Attendance Calculation ----
    $routes->get('att/calculate', 'AttCalc::index', ['as' => 'attcalc']);
    $routes->post('att/calculate/run', 'AttCalc::run', ['as' => 'attcalc.run']);

    // ---- Attendance ▸ Rule ▸ Global Rule  +  Configurations ▸ Report Setting ----
    $routes->get('att-config', 'AttConfig::index', ['as' => 'attconfig']);
    $routes->get('att-config/(:segment)', 'AttConfig::index/$1', ['as' => 'attconfig.tab']);
    $routes->post('att-config/global/save', 'AttConfig::saveGlobal', ['as' => 'attconfig.global.save']);
    $routes->post('att-config/reporting/save', 'AttConfig::saveReporting', ['as' => 'attconfig.reporting.save']);

    // ---- Holidays ----
    $routes->get('holidays', 'Holidays::index', ['as' => 'holidays']);
    $routes->post('holidays/save', 'Holidays::save', ['as' => 'holiday.save']);
    $routes->post('holidays/(:num)/delete', 'Holidays::delete/$1', ['as' => 'holiday.delete']);

    // ---- Requests / approvals (leave · manual punch · overtime · schedule change) ----
    $routes->get('requests', 'Requests::index', ['as' => 'requests']);
    $routes->get('requests/(:segment)', 'Requests::index/$1', ['as' => 'requests.tab']);
    $routes->post('requests/apply', 'Requests::apply', ['as' => 'request.apply']);
    $routes->post('requests/(:num)/decide', 'Requests::decide/$1', ['as' => 'request.decide']);
    $routes->post('requests/(:num)/delete', 'Requests::delete/$1', ['as' => 'request.delete']);
    // legacy leave URLs → Requests
    $routes->get('leaves', static fn () => redirect()->to('/admin/requests/leave'), ['as' => 'leaves']);

    // ---- Workflow (approval roles + multi-step approval flows) ----
    $routes->get('workflow', 'Workflow::index', ['as' => 'workflow']);
    $routes->get('workflow/flow/(:num)', 'Workflow::flow/$1', ['as' => 'workflow.flow']);
    $routes->get('workflow/(:segment)', 'Workflow::index/$1', ['as' => 'workflow.tab']);
    $routes->post('workflow/roles/save', 'Workflow::saveRole', ['as' => 'workflow.role.save']);
    $routes->post('workflow/roles/(:num)/delete', 'Workflow::deleteRole/$1', ['as' => 'workflow.role.delete']);
    $routes->post('workflow/flows/save', 'Workflow::saveFlow', ['as' => 'workflow.flow.save']);
    $routes->post('workflow/flows/(:num)/delete', 'Workflow::deleteFlow/$1', ['as' => 'workflow.flow.delete']);
    $routes->post('workflow/flow/(:num)/nodes/save', 'Workflow::saveNode/$1', ['as' => 'workflow.node.save']);
    $routes->post('workflow/flow/(:num)/nodes/(:num)/delete', 'Workflow::deleteNode/$1/$2', ['as' => 'workflow.node.delete']);

    // ---- Organisation (companies · departments · positions · areas) ----
    $routes->get('org', 'Org::index', ['as' => 'org']);
    $routes->get('org/(:segment)', 'Org::index/$1', ['as' => 'org.tab']);
    $routes->post('org/departments/bulk-delete', 'Org::bulkDelete/departments', ['as' => 'org.department.bulk_delete']);
    $routes->post('org/positions/bulk-delete', 'Org::bulkDelete/positions', ['as' => 'org.position.bulk_delete']);
    $routes->post('org/areas/bulk-delete', 'Org::bulkDelete/areas', ['as' => 'org.area.bulk_delete']);
    $routes->post('org/companies/bulk-delete', 'Org::bulkDelete/companies', ['as' => 'org.company.bulk_delete']);
    $routes->post('org/companies/save', 'Org::saveCompany', ['as' => 'org.company.save']);
    $routes->post('org/companies/(:num)/delete', 'Org::deleteCompany/$1', ['as' => 'org.company.delete']);
    $routes->post('org/departments/save', 'Org::saveDepartment', ['as' => 'org.department.save']);
    $routes->post('org/departments/(:num)/delete', 'Org::deleteDepartment/$1', ['as' => 'org.department.delete']);
    $routes->post('org/positions/save', 'Org::savePosition', ['as' => 'org.position.save']);
    $routes->post('org/positions/(:num)/delete', 'Org::deletePosition/$1', ['as' => 'org.position.delete']);
    $routes->post('org/areas/save', 'Org::saveArea', ['as' => 'org.area.save']);
    $routes->post('org/areas/(:num)/delete', 'Org::deleteArea/$1', ['as' => 'org.area.delete']);

    // ---- Certifications (types + per-employee records with expiry) ----
    $routes->get('certifications', 'Certifications::index', ['as' => 'certifications']);
    $routes->get('certifications/(:segment)', 'Certifications::index/$1', ['as' => 'certifications.tab']);
    $routes->post('certifications/save', 'Certifications::save', ['as' => 'cert.record.save']);
    $routes->post('certifications/(:num)/delete', 'Certifications::delete/$1', ['as' => 'cert.record.delete']);
    $routes->post('certifications/types/save', 'Certifications::saveType', ['as' => 'cert.type.save']);
    $routes->post('certifications/types/(:num)/delete', 'Certifications::deleteType/$1', ['as' => 'cert.type.delete']);

    // ---- Work codes ----
    $routes->get('work-codes', 'WorkCodes::index', ['as' => 'workcodes']);
    $routes->post('work-codes/save', 'WorkCodes::save', ['as' => 'workcode.save']);
    $routes->post('work-codes/(:num)/delete', 'WorkCodes::delete/$1', ['as' => 'workcode.delete']);

    // ---- Leave groups / entitlements ----
    $routes->get('leave-groups', 'LeaveGroups::index', ['as' => 'leavegroups']);
    $routes->post('leave-groups/save', 'LeaveGroups::saveGroup', ['as' => 'leavegroup.save']);
    $routes->post('leave-groups/(:num)/delete', 'LeaveGroups::deleteGroup/$1', ['as' => 'leavegroup.delete']);
    $routes->post('leave-groups/detail/save', 'LeaveGroups::saveDetail', ['as' => 'leavegroup.detail.save']);
    $routes->post('leave-groups/detail/(:num)/delete', 'LeaveGroups::deleteDetail/$1', ['as' => 'leavegroup.detail.delete']);
    $routes->post('leave-groups/assign', 'LeaveGroups::assign', ['as' => 'leavegroup.assign']);

    // ---- Reports ----
    $routes->group('reports', static function (RouteCollection $routes): void {
        $routes->get('/', 'Reports::index', ['as' => 'reports']);
        $routes->get('late', 'Reports::late', ['as' => 'reports.late']);
        $routes->get('early-out', 'Reports::earlyOut', ['as' => 'reports.earlyOut']);
        $routes->get('absent', 'Reports::absent', ['as' => 'reports.absent']);
        $routes->get('overtime', 'Reports::overtime', ['as' => 'reports.overtime']);
        $routes->get('daily-worked', 'Reports::dailyWorked', ['as' => 'reports.dailyWorked']);
        $routes->get('punch-card', 'Reports::punchCard', ['as' => 'reports.punchCard']);
        $routes->get('first-last', 'Reports::firstLast', ['as' => 'reports.firstLast']);
        $routes->get('first-in-last-out', 'Reports::firstInLastOut', ['as' => 'reports.firstInLastOut']);
        $routes->get('exception', 'Reports::exception', ['as' => 'reports.exception']);
        $routes->get('transactions', 'Reports::transactions', ['as' => 'reports.transactions']);
        $routes->get('time-card', 'Reports::timeCard', ['as' => 'reports.timeCard']);
        $routes->get('time-card/export', 'Reports::timeCardExport', ['as' => 'reports.timeCard.export']);
        $routes->get('summary', 'Reports::summary', ['as' => 'reports.summary']);
        $routes->get('leave-balance', 'Reports::leaveBalance', ['as' => 'reports.leaveBalance']);
        $routes->get('scheduled-log', 'Reports::scheduledLog', ['as' => 'reports.scheduledLog']);
        $routes->get('paired-punch', 'Reports::pairedPunch', ['as' => 'reports.pairedPunch']);
        $routes->get('multiple-work-hours', 'Reports::multipleWorkHours', ['as' => 'reports.multipleWorkHours']);
        $routes->get('multiple-break-time', 'Reports::multipleBreakTime', ['as' => 'reports.multipleBreakTime']);

        // ---- Monthly Report group (ZKBioTime's own menu: Status · Check-in&out · Worked Hrs · Overtime · Absence) ----
        $routes->get('monthly-status', 'MonthlyReports::status', ['as' => 'reports.monthlyStatus']);
        $routes->get('monthly-status/export', 'MonthlyReports::statusExport', ['as' => 'reports.monthlyStatus.export']);
        $routes->get('monthly-checkinout', 'MonthlyReports::checkInOut', ['as' => 'reports.monthlyCheckInOut']);
        $routes->get('monthly-checkinout/export', 'MonthlyReports::checkInOutExport', ['as' => 'reports.monthlyCheckInOut.export']);
        $routes->get('monthly-worked', 'MonthlyReports::workedHrs', ['as' => 'reports.monthlyWorkedHrs']);
        $routes->get('monthly-worked/export', 'MonthlyReports::workedHrsExport', ['as' => 'reports.monthlyWorkedHrs.export']);
        $routes->get('monthly-overtime', 'MonthlyReports::overtime', ['as' => 'reports.monthlyOvertime']);
        $routes->get('monthly-overtime/export', 'MonthlyReports::overtimeExport', ['as' => 'reports.monthlyOvertime.export']);
        $routes->get('monthly-absence', 'MonthlyReports::absence', ['as' => 'reports.monthlyAbsence']);
        $routes->get('monthly-absence/export', 'MonthlyReports::absenceExport', ['as' => 'reports.monthlyAbsence.export']);

        // ---- Summary Report group (ZKBioTime's own menu: Employee/Department/Group Attendance·Overtime + Employee Leave; Leave Balance already exists separately) ----
        $routes->get('employee-attendance', 'SummaryReports::employeeAttendance', ['as' => 'summary.employeeAttendance']);
        $routes->get('employee-attendance/export', 'SummaryReports::employeeAttendanceExport', ['as' => 'summary.employeeAttendance.export']);
        $routes->get('employee-overtime', 'SummaryReports::employeeOvertime', ['as' => 'summary.employeeOvertime']);
        $routes->get('employee-overtime/export', 'SummaryReports::employeeOvertimeExport', ['as' => 'summary.employeeOvertime.export']);
        $routes->get('employee-leave', 'SummaryReports::employeeLeave', ['as' => 'summary.employeeLeave']);
        $routes->get('employee-leave/export', 'SummaryReports::employeeLeaveExport', ['as' => 'summary.employeeLeave.export']);
        $routes->get('department-attendance', 'SummaryReports::departmentAttendance', ['as' => 'summary.departmentAttendance']);
        $routes->get('department-attendance/export', 'SummaryReports::departmentAttendanceExport', ['as' => 'summary.departmentAttendance.export']);
        $routes->get('department-overtime', 'SummaryReports::departmentOvertime', ['as' => 'summary.departmentOvertime']);
        $routes->get('department-overtime/export', 'SummaryReports::departmentOvertimeExport', ['as' => 'summary.departmentOvertime.export']);
        $routes->get('group-attendance', 'SummaryReports::groupAttendance', ['as' => 'summary.groupAttendance']);
        $routes->get('group-attendance/export', 'SummaryReports::groupAttendanceExport', ['as' => 'summary.groupAttendance.export']);
        $routes->get('group-overtime', 'SummaryReports::groupOvertime', ['as' => 'summary.groupOvertime']);
        $routes->get('group-overtime/export', 'SummaryReports::groupOvertimeExport', ['as' => 'summary.groupOvertime.export']);
    });

    // ---- Payroll ----
    $routes->group('payroll', static function (RouteCollection $routes): void {
        $routes->get('/', 'Payroll::index', ['as' => 'payroll']);
        $routes->post('generate', 'Payroll::generate', ['as' => 'payroll.generate']);
        $routes->get('export', 'Payroll::export', ['as' => 'payroll.export']);
        $routes->get('slip/(:num)', 'Payroll::slip/$1', ['as' => 'payroll.slip']);
        $routes->get('structures', 'Payroll::structures', ['as' => 'payroll.structures']);
        $routes->post('structures/save', 'Payroll::saveStructure', ['as' => 'payroll.structure.save']);
        $routes->post('structures/(:num)/delete', 'Payroll::deleteStructure/$1', ['as' => 'payroll.structure.delete']);
        $routes->get('loans', 'Payroll::loans', ['as' => 'payroll.loans']);
        $routes->post('loans/save', 'Payroll::saveLoan', ['as' => 'payroll.loan.save']);
        $routes->post('loans/(:num)/settle', 'Payroll::settleLoan/$1', ['as' => 'payroll.loan.settle']);
        $routes->post('loans/(:num)/delete', 'Payroll::deleteLoan/$1', ['as' => 'payroll.loan.delete']);
        $routes->get('advances', 'Payroll::advances', ['as' => 'payroll.advances']);
        $routes->post('advances/save', 'Payroll::saveAdvance', ['as' => 'payroll.advance.save']);
        $routes->post('advances/(:num)/delete', 'Payroll::deleteAdvance/$1', ['as' => 'payroll.advance.delete']);
        $routes->get('increases', 'Payroll::increases', ['as' => 'payroll.increases']);
        $routes->get('deductions', 'Payroll::deductions', ['as' => 'payroll.deductions']);
        $routes->get('reimbursements', 'Payroll::reimbursements', ['as' => 'payroll.reimbursements']);
        $routes->post('adjustments/type/save', 'Payroll::saveAdjustmentType', ['as' => 'payroll.adjustment.type.save']);
        $routes->post('adjustments/(:segment)/save', 'Payroll::saveAdjustment/$1', ['as' => 'payroll.adjustment.save']);
        $routes->post('adjustments/(:segment)/(:num)/delete', 'Payroll::deleteAdjustment/$1/$2', ['as' => 'payroll.adjustment.delete']);
        $routes->get('bank', 'Payroll::bank', ['as' => 'payroll.bank']);
        $routes->post('bank/(:num)/save', 'Payroll::saveBank/$1', ['as' => 'payroll.bank.save']);
        $routes->get('reports', 'Payroll::reports', ['as' => 'payroll.reports']);
        $routes->get('reports/(:segment)', 'Payroll::reports/$1', ['as' => 'payroll.reports.tab']);

        // ---- Payroll setup (formulas · tax · social security · exemptions · settings) ----
        $routes->get('config', 'PayrollConfig::index', ['as' => 'payrollconfig']);
        $routes->get('config/formula/(:segment)', 'PayrollConfig::formula/$1', ['as' => 'payrollconfig.formula']);
        $routes->get('config/(:segment)', 'PayrollConfig::index/$1', ['as' => 'payrollconfig.tab']);
        $routes->post('config/formulas/(:segment)/save', 'PayrollConfig::saveFormula/$1', ['as' => 'payrollconfig.formula.save']);
        $routes->post('config/formulas/(:segment)/(:num)/delete', 'PayrollConfig::deleteFormula/$1/$2', ['as' => 'payrollconfig.formula.delete']);
        $routes->post('config/slabs/(:segment)/save', 'PayrollConfig::saveSlabs/$1', ['as' => 'payrollconfig.slabs.save']);
        $routes->post('config/exemptions/save', 'PayrollConfig::saveExemption', ['as' => 'payrollconfig.exemption.save']);
        $routes->post('config/exemptions/(:num)/delete', 'PayrollConfig::deleteExemption/$1', ['as' => 'payrollconfig.exemption.delete']);
        $routes->post('config/settings/save', 'PayrollConfig::saveSettings', ['as' => 'payrollconfig.settings.save']);
    });

    // ---- Access control ----
    $routes->group('access', static function (RouteCollection $routes): void {
        $routes->get('/', 'AccessControl::index', ['as' => 'access']);
        $routes->get('levels', 'AccessControl::levels', ['as' => 'access.levels']);
        $routes->get('timezones', 'AccessControl::timezones', ['as' => 'access.timezones']);
        $routes->get('privileges', 'AccessControl::privileges', ['as' => 'access.privileges']);
        $routes->get('combinations', 'AccessControl::combinations', ['as' => 'access.combinations']);
        $routes->get('holidays', 'AccessControl::holidays', ['as' => 'access.holidays']);
        $routes->get('doors', 'AccessControl::doors', ['as' => 'access.doors']);
        $routes->get('commands', 'AccessControl::commands', ['as' => 'access.commands']);
        $routes->post('levels/save', 'AccessControl::saveLevel', ['as' => 'access.level.save']);
        $routes->post('levels/(:num)/delete', 'AccessControl::deleteLevel/$1', ['as' => 'access.level.delete']);
        $routes->post('timezones/save', 'AccessControl::saveTimezone', ['as' => 'access.tz.save']);
        $routes->post('timezones/(:num)/delete', 'AccessControl::deleteTimezone/$1', ['as' => 'access.tz.delete']);
        $routes->post('privileges/assign', 'AccessControl::assign', ['as' => 'access.priv.assign']);
        $routes->post('privileges/(:num)/revoke', 'AccessControl::revoke/$1', ['as' => 'access.priv.revoke']);
        $routes->post('combinations/save', 'AccessControl::saveCombination', ['as' => 'access.combo.save']);
        $routes->post('combinations/(:num)/delete', 'AccessControl::deleteCombination/$1', ['as' => 'access.combo.delete']);
        $routes->post('holidays/save', 'AccessControl::saveAccHoliday', ['as' => 'access.holiday.save']);
        $routes->post('holidays/(:num)/delete', 'AccessControl::deleteAccHoliday/$1', ['as' => 'access.holiday.delete']);
        $routes->post('doors/save', 'AccessControl::saveDoor', ['as' => 'access.door.save']);
        $routes->post('commands/(:num)/delete', 'AccessControl::deleteCommand/$1', ['as' => 'access.command.delete']);
        $routes->post('commands/bulk_delete', 'AccessControl::bulkDeleteCommand', ['as' => 'access.command.bulk_delete']);
        $routes->post('commands/clear', 'AccessControl::clearCommands', ['as' => 'access.command.clear']);
    });

    // ---- Visitors ----
    $routes->get('visitors', 'Visitors::index', ['as' => 'visitors']);
    $routes->post('visitors/register', 'Visitors::register', ['as' => 'visitor.register']);
    $routes->post('visitors/(:num)/checkout', 'Visitors::checkout/$1', ['as' => 'visitor.checkout']);
    $routes->post('visitors/(:num)/delete', 'Visitors::delete/$1', ['as' => 'visitor.delete']);
    $routes->post('visitors/reserve', 'Visitors::reserve', ['as' => 'visitor.reserve']);
    $routes->post('visitors/reservations/(:num)/decide', 'Visitors::reservationDecide/$1', ['as' => 'visitor.reservation.decide']);
    $routes->post('visitors/reservations/(:num)/convert', 'Visitors::convert/$1', ['as' => 'visitor.reservation.convert']);
    $routes->post('visitors/reservations/(:num)/delete', 'Visitors::reservationDelete/$1', ['as' => 'visitor.reservation.delete']);
    $routes->get('visitors/settings', 'Visitors::settings', ['as' => 'visitor.settings']);
    $routes->post('visitors/reasons/save', 'Visitors::saveReason', ['as' => 'visitor.reason.save']);
    $routes->post('visitors/reasons/(:num)/delete', 'Visitors::deleteReason/$1', ['as' => 'visitor.reason.delete']);
    $routes->post('visitors/config/save', 'Visitors::saveConfig', ['as' => 'visitor.config.save']);

    // ---- Meeting rooms + bookings ----
    $routes->get('meeting-rooms', 'MeetingRooms::index', ['as' => 'meetings']);
    $routes->post('meeting-rooms/save', 'MeetingRooms::save', ['as' => 'meeting.save']);
    $routes->post('meeting-rooms/(:num)/delete', 'MeetingRooms::delete/$1', ['as' => 'meeting.delete']);
    $routes->get('meetings/bookings', 'Meetings::index', ['as' => 'meetings.bookings']);
    $routes->get('meetings/bookings/(:num)', 'Meetings::view/$1', ['as' => 'meetings.view']);
    $routes->post('meetings/bookings/save', 'Meetings::save', ['as' => 'meetings.save']);
    $routes->post('meetings/bookings/(:num)/calculate', 'Meetings::calculate/$1', ['as' => 'meetings.calculate']);
    $routes->post('meetings/bookings/(:num)/manual-log', 'Meetings::manualLog/$1', ['as' => 'meetings.manuallog']);
    $routes->post('meetings/bookings/(:num)/delete', 'Meetings::delete/$1', ['as' => 'meetings.delete']);

    // ---- Mobile (GPS geofences · announcements · notifications · web punches) ----
    $routes->get('mobile', 'Mobile::index', ['as' => 'mobile']);
    $routes->get('mobile/(:segment)', 'Mobile::index/$1', ['as' => 'mobile.tab']);
    $routes->post('mobile/locations/save', 'Mobile::saveLocation', ['as' => 'mobile.location.save']);
    $routes->post('mobile/locations/(:num)/delete', 'Mobile::deleteLocation/$1', ['as' => 'mobile.location.delete']);
    $routes->post('mobile/assignments/save', 'Mobile::assign', ['as' => 'mobile.assign']);
    $routes->post('mobile/assignments/(:segment)/(:num)/delete', 'Mobile::deleteAssignment/$1/$2', ['as' => 'mobile.assign.delete']);
    $routes->post('mobile/announcements/save', 'Mobile::announce', ['as' => 'mobile.announce']);
    $routes->post('mobile/announcements/(:num)/delete', 'Mobile::deleteAnnouncement/$1', ['as' => 'mobile.announce.delete']);
    $routes->post('mobile/webpunch/(:num)/decide', 'Mobile::decideWebpunch/$1', ['as' => 'mobile.webpunch.decide']);

    // ---- Temperature screening (EP module) ----
    $routes->get('ep', 'EP::index', ['as' => 'ep']);
    $routes->get('ep/(:segment)', 'EP::index/$1', ['as' => 'ep.tab']);
    $routes->post('ep/setup/save', 'EP::saveSetup', ['as' => 'ep.setup.save']);

    // ---- Notifications ----
    $routes->get('notifications', 'Notifications::index', ['as' => 'notifications']);
    $routes->post('notifications/(:num)/read', 'Notifications::read/$1', ['as' => 'notification.read']);
    $routes->post('notifications/read-all', 'Notifications::readAll', ['as' => 'notifications.readall']);

    // ---- System log (read-only) ----
    $routes->get('system-log/(:segment)', 'SystemLog::index/$1', ['as' => 'system.log.tab']);
    $routes->get('system-log', 'SystemLog::index', ['as' => 'system.log']);

    // ---- Administrators (admin only) ----
    $routes->group('', ['filter' => 'role:admin'], static function (RouteCollection $routes): void {
        $routes->get('admins', 'Admins::index', ['as' => 'admins']);
        $routes->post('admins/save', 'Admins::save', ['as' => 'admin.save']);
        $routes->post('admins/(:num)/delete', 'Admins::delete/$1', ['as' => 'admin.delete']);
        $routes->post('admins/(:num)/toggle', 'Admins::toggle/$1', ['as' => 'admin.toggle']);

        // Settings edits the attendance policy — admin only
        $routes->post('settings/save', 'Settings::save', ['as' => 'settings.save']);

        // Email settings + templates — admin only
        $routes->get('email', 'EmailSettings::index', ['as' => 'email']);
        $routes->post('email/save', 'EmailSettings::save', ['as' => 'email.save']);
        $routes->post('email/test', 'EmailSettings::test', ['as' => 'email.test']);
        $routes->post('email/templates/save', 'EmailSettings::saveTemplate', ['as' => 'email.template.save']);
        $routes->post('email/templates/(:num)/delete', 'EmailSettings::deleteTemplate/$1', ['as' => 'email.template.delete']);

        // System administration — roles, security, integrations, tasks, backups, API
        $routes->get('system', 'System::index', ['as' => 'system']);
        $routes->get('system/(:segment)', 'System::index/$1', ['as' => 'system.tab']);
        $routes->post('system/roles/save', 'System::saveRole', ['as' => 'system.role.save']);
        $routes->post('system/roles/(:num)/delete', 'System::deleteRole/$1', ['as' => 'system.role.delete']);
        $routes->post('system/roles/assign', 'System::assignRole', ['as' => 'system.role.assign']);
        $routes->post('system/security/save', 'System::saveSecurity', ['as' => 'system.security.save']);
        $routes->post('system/zoom/save', 'System::saveZoom', ['as' => 'system.zoom.save']);
        $routes->post('system/events/save', 'System::saveEvents', ['as' => 'system.events.save']);
        $routes->post('system/tasks/(:segment)/save', 'System::saveTask/$1', ['as' => 'system.task.save']);
        $routes->post('system/tasks/(:segment)/(:num)/delete', 'System::deleteTask/$1/$2', ['as' => 'system.task.delete']);
        $routes->post('system/backup/run', 'System::runBackup', ['as' => 'system.backup.run']);
        $routes->post('system/api/endpoint/save', 'System::saveEndpoint', ['as' => 'system.api.endpoint.save']);
        $routes->post('system/api/endpoint/(:num)/delete', 'System::deleteEndpoint/$1', ['as' => 'system.api.endpoint.delete']);
        $routes->post('system/api/grant', 'System::grantApi', ['as' => 'system.api.grant']);
        $routes->post('system/api/(:num)/revoke', 'System::revokeApi/$1', ['as' => 'system.api.revoke']);
    });

    // ---- Account ----
    $routes->get('settings', 'Settings::index', ['as' => 'settings']);
    $routes->match(['get', 'post'], 'profile', 'Settings::profile', ['as' => 'profile']);
});

// =====================================================================
//  Employee self-service portal  (separate session namespace)
// =====================================================================
$routes->get('portal/login', 'StaffAuth::login', ['as' => 'portal.login']);
$routes->post('portal/login', 'StaffAuth::attempt');
$routes->get('portal/logout', 'StaffAuth::logout', ['as' => 'portal.logout']);

$routes->group('portal', ['filter' => 'staffauth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'StaffPortal::dashboard', ['as' => 'portal']);
    $routes->get('attendance', 'StaffPortal::attendance', ['as' => 'portal.attendance']);
    $routes->get('payslips', 'StaffPortal::payslips', ['as' => 'portal.payslips']);
    $routes->get('payslips/(:num)/(:num)', 'StaffPortal::payslip/$1/$2', ['as' => 'portal.payslip']);
    $routes->get('requests', 'StaffPortal::requests', ['as' => 'portal.requests']);
    $routes->post('requests/apply', 'StaffPortal::apply', ['as' => 'portal.requests.apply']);
    $routes->post('requests/(:num)/withdraw', 'StaffPortal::withdraw/$1', ['as' => 'portal.requests.withdraw']);
    $routes->get('profile', 'StaffPortal::profile', ['as' => 'portal.profile']);
    $routes->post('profile/password', 'StaffPortal::changePassword', ['as' => 'portal.password']);
});
