<?php

namespace App\Controllers;

/**
 * Read-only window onto ZKBioTime's own operational tables: system log,
 * admin activity, push notifications, device command/upload logs,
 * scheduled tasks and system parameters. Nothing here is editable.
 */
class SystemLog extends BaseController
{
    private const PER_PAGE = 40;

    private const TABS = [
        'system'        => 'System Log',
        'activity'      => 'Admin Activity',
        'notifications' => 'Notifications',
        'devices'       => 'Device Logs',
        'tasks'         => 'Scheduled Tasks',
        'params'        => 'Parameters',
    ];

    public function index(string $tab = 'system'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'system';
        }
        $db   = db_connect();
        $page = max(1, (int) $this->request->getGet('page'));
        $off  = ($page - 1) * self::PER_PAGE;
        $lim  = self::PER_PAGE;

        $data = [
            'title'    => 'System Log',
            'subtitle' => self::TABS[$tab],
            'active'   => 'system.log',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'page'     => $page,
            'perPage'  => self::PER_PAGE,
            'rows'     => [],
            'total'    => 0,
        ];

        switch ($tab) {
            case 'system':
                $data['total'] = (int) $db->query('SELECT count(*) c FROM base_systemlog')->getRow()->c;
                $data['rows']  = $db->query(
                    "SELECT execute_time, operation, execute_status, description
                       FROM base_systemlog ORDER BY id DESC LIMIT ? OFFSET ?", [$lim, $off]
                )->getResultArray();
                break;

            case 'activity':
                $data['total'] = (int) $db->query('SELECT count(*) c FROM base_adminlog')->getRow()->c;
                $data['rows']  = $db->query(
                    "SELECT l.action, l.targets_repr, l.action_status, l.description, l.ip_address, l.op_time,
                            u.username
                       FROM base_adminlog l
                  LEFT JOIN auth_user u ON u.id = l.user_id
                   ORDER BY l.id DESC LIMIT ? OFFSET ?", [$lim, $off]
                )->getResultArray();
                break;

            case 'notifications':
                $data['total'] = (int) $db->query('SELECT count(*) c FROM accounts_usernotification')->getRow()->c;
                $data['rows']  = $db->query(
                    "SELECT category, event, content, send_time, read_time, status
                       FROM accounts_usernotification ORDER BY id DESC LIMIT ? OFFSET ?", [$lim, $off]
                )->getResultArray();
                break;

            case 'devices':
                $data['total'] = (int) $db->query('SELECT count(*) c FROM iclock_terminaluploadlog')->getRow()->c;
                $data['rows']  = $db->query(
                    "SELECT ul.event, ul.content, ul.upload_count, ul.error_count, ul.upload_time,
                            term.alias AS device
                       FROM iclock_terminaluploadlog ul
                  LEFT JOIN iclock_terminal term ON term.id = ul.terminal_id
                   ORDER BY ul.id DESC LIMIT ? OFFSET ?", [$lim, $off]
                )->getResultArray();
                $data['commands'] = $db->query(
                    "SELECT cl.content, cl.commit_time, cl.transfer_time, cl.return_time, cl.return_value,
                            term.alias AS device
                       FROM iclock_terminalcommandlog cl
                  LEFT JOIN iclock_terminal term ON term.id = cl.terminal_id
                   ORDER BY cl.id DESC LIMIT 20"
                )->getResultArray();
                break;

            case 'tasks':
                $data['rows'] = $db->query(
                    "SELECT t.name, t.task, t.enabled, t.last_run_at, t.total_run_count, t.one_off,
                            c.minute, c.hour, c.day_of_week, c.day_of_month, c.month_of_year,
                            i.every, i.period
                       FROM django_celery_beat_periodictask t
                  LEFT JOIN django_celery_beat_crontabschedule c ON c.id = t.crontab_id
                  LEFT JOIN django_celery_beat_intervalschedule i ON i.id = t.interval_id
                   ORDER BY t.enabled DESC, t.name"
                )->getResultArray();
                $data['total'] = count($data['rows']);
                break;

            case 'params':
                $data['rows'] = $db->query(
                    "SELECT para_name, para_type, para_value FROM base_sysparam
                      WHERE length(para_value) < 40 ORDER BY para_type, para_name"
                )->getResultArray();
                $data['total'] = count($data['rows']);
                break;
        }

        return view('admin/system/index', $data);
    }
}
