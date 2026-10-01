<?php

namespace App\Controllers;

/**
 * Device ▸ Log — the five ZKBioTime device-log lists, one per sidebar item:
 *
 *   command   iclock_terminalcommand      pending PUSH commands (the queue)
 *   cmdlog    iclock_terminalcommandlog   commands the device has ACKed
 *   devlog    iclock_terminallog          operations done at the terminal
 *   upload    iclock_terminaluploadlog    what the device pushed up, and errors
 *   errorcmd  iclock_errorcommandlog      commands the device rejected
 *
 * All read-only; each has Filter / Export, and a "Clear" that wipes rows
 * older than N days (or all).
 */
class DeviceLogs extends BaseController
{
    private const TABS = [
        'command'  => 'Device Command',
        'devlog'   => 'Operation Log',
        'errorcmd' => 'Error Log',
        'upload'   => 'Upload Log',
        'cmdlog'   => 'Command Log',
    ];

    /** iclock.TerminalCommand.return_value */
    public const RETURN_VALUES = [
        0 => 'Successful', -1 => 'Parameter incorrect', -2 => 'User photo transfer error',
        -3 => 'Read/write error', -9 => 'Bio-template transfer error', -10 => 'User does not exist',
        -11 => 'Illegal FP template format', -12 => 'Illegal FP template',
        -1001 => 'Capacity limit', -1002 => 'Device not supported', -1003 => 'Timeout',
        -1004 => 'Data exception', -1005 => 'Device busy', -1006 => 'Data too long',
        -1007 => 'Memory error', -1008 => 'Getting server data failed', -1100 => 'Invalid command',
    ];

    /** iclock.TerminalLog.action_name — the operations a device reports */
    public const DEV_ACTIONS = [
        0 => 'Power on', 1 => 'Power off', 2 => 'Verify failed', 3 => 'Alarm', 4 => 'Enter menu',
        5 => 'Modify setting', 6 => 'Enroll fingerprint', 7 => 'Enroll password', 8 => 'Enroll card',
        9 => 'Delete user', 10 => 'Delete fingerprint', 11 => 'Delete password', 12 => 'Delete card',
        13 => 'Clean data', 21 => 'Set time', 22 => 'Restore setting', 29 => 'Unlock',
        30 => 'Enroll new user', 31 => 'Update fingerprint', 32 => 'Duress alarm',
        35 => 'Delete att photo', 36 => 'Update other user', 68 => 'Register user photo',
        69 => 'Update user photo', 70 => 'Update user name', 71 => 'Update user privilege',
        100 => 'Update palm', 101 => 'Delete palm',
    ];

    public function index(string $tab = 'command'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'command';
        }
        $db = db_connect();
        $b  = $this->tabQuery($db, $tab);

        $fbApplied = $this->filterBuilder($b, $this->spec($tab));
        [$rows, $pager] = $this->paged($b, 40);

        return view('admin/devlogs/index', [
            'title'     => self::TABS[$tab],
            'subtitle'  => 'Device communication log',
            'active'    => 'devlog.' . $tab,
            'tab'       => $tab,
            'tabs'      => self::TABS,
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'devices'   => $db->query('SELECT id, alias FROM iclock_terminal ORDER BY alias')->getResultArray(),
            'returnValues' => self::RETURN_VALUES,
            'devActions'   => self::DEV_ACTIONS,
        ]);
    }

    private function tabQuery($db, string $tab)
    {
        switch ($tab) {
            case 'cmdlog':
                return $db->table('iclock_terminalcommandlog c')
                    ->select("c.id, c.content, c.return_value, t.alias AS device,
                              to_char(c.commit_time,'YYYY-MM-DD HH24:MI:SS') AS commit_at,
                              to_char(c.transfer_time,'YYYY-MM-DD HH24:MI:SS') AS transfer_at,
                              to_char(c.return_time,'YYYY-MM-DD HH24:MI:SS') AS return_at")
                    ->join('iclock_terminal t', 't.id = c.terminal_id', 'left')
                    ->orderBy('c.commit_time', 'DESC');

            case 'devlog':
                return $db->table('iclock_terminallog l')
                    ->select("l.id, l.admin, l.action_name, l.object, l.param1, l.param2,
                              t.alias AS device,
                              to_char(l.action_time,'YYYY-MM-DD HH24:MI:SS') AS action_at,
                              to_char(l.upload_time,'YYYY-MM-DD HH24:MI:SS') AS upload_at")
                    ->join('iclock_terminal t', 't.id = l.terminal_id', 'left')
                    ->orderBy('l.action_time', 'DESC');

            case 'upload':
                return $db->table('iclock_terminaluploadlog u')
                    ->select("u.id, u.event, u.content, u.upload_count, u.error_count,
                              t.alias AS device,
                              to_char(u.upload_time,'YYYY-MM-DD HH24:MI:SS') AS upload_at")
                    ->join('iclock_terminal t', 't.id = u.terminal_id', 'left')
                    ->orderBy('u.upload_time', 'DESC');

            case 'errorcmd':
                return $db->table('iclock_errorcommandlog e')
                    ->select("e.id, e.error_code, e.error_msg, e.cmd, e.data_origin,
                              t.alias AS device,
                              to_char(e.upload_time,'YYYY-MM-DD HH24:MI:SS') AS upload_at")
                    ->join('iclock_terminal t', 't.id = e.terminal_id', 'left')
                    ->orderBy('e.upload_time', 'DESC');

            default: // command (pending queue)
                return $db->table('iclock_terminalcommand c')
                    ->select("c.id, c.content, c.return_value, t.alias AS device,
                              to_char(c.commit_time,'YYYY-MM-DD HH24:MI:SS') AS commit_at,
                              to_char(c.transfer_time,'YYYY-MM-DD HH24:MI:SS') AS transfer_at")
                    ->join('iclock_terminal t', 't.id = c.terminal_id', 'left')
                    ->orderBy('c.commit_time', 'DESC');
        }
    }

    private function spec(string $tab): array
    {
        $common = ['device' => ['t.alias', 'text']];

        return match ($tab) {
            'devlog'   => $common + ['admin' => ['l.admin', 'text'], 'object' => ['l.object', 'text'], 'action_time' => ['l.action_time', 'date']],
            'upload'   => $common + ['event' => ['u.event', 'text'], 'content' => ['u.content', 'text'], 'upload_time' => ['u.upload_time', 'date']],
            'errorcmd' => $common + ['cmd' => ['e.cmd', 'text'], 'error_msg' => ['e.error_msg', 'text'], 'upload_time' => ['e.upload_time', 'date']],
            default    => $common + ['content' => ['c.content', 'text'], 'commit_time' => ['c.commit_time', 'date']],
        };
    }

    /** "Clear" — delete rows older than ?days (0 = all). */
    public function clear(string $tab)
    {
        if (! isset(self::TABS[$tab])) {
            return redirect()->back()->with('error', 'Unknown log.');
        }
        $table = [
            'command'  => ['iclock_terminalcommand', 'commit_time'],
            'cmdlog'   => ['iclock_terminalcommandlog', 'commit_time'],
            'devlog'   => ['iclock_terminallog', 'action_time'],
            'upload'   => ['iclock_terminaluploadlog', 'upload_time'],
            'errorcmd' => ['iclock_errorcommandlog', 'upload_time'],
        ][$tab];

        $days = max(0, (int) $this->request->getPost('days'));
        $db   = db_connect()->table($table[0]);
        if ($days > 0) {
            $db->where("{$table[1]} < now() - interval '{$days} days'", null, false);
        }
        $n = $db->delete();
        $this->audit('device.log.clear.' . $tab, ($days ?: 'all') . ' days');

        return redirect()->to(route_to('devlogs.tab', $tab))->with('success', 'Log cleared.');
    }
}
