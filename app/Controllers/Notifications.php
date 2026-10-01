<?php

namespace App\Controllers;

use App\Libraries\Notify;

class Notifications extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $b  = $db->table('accounts_usernotification')
            ->select("id, content, category, status,
                      to_char(send_time,'YYYY-MM-DD HH24:MI') AS send_time,
                      to_char(read_time,'YYYY-MM-DD HH24:MI') AS read_time")
            ->where("content NOT LIKE '{%'")   // exclude ZKBioTime's legacy JSON device notices
            ->orderBy('id', 'DESC');
        [$rows, $pager] = $this->paged($b, 40);

        foreach ($rows as &$r) {
            [$text, $link] = array_pad(explode(' ||', (string) $r['content'], 2), 2, null);
            $r['content'] = $text;
            $r['link']    = $link;
        }
        unset($r);

        return view('admin/notifications/index', [
            'title'    => 'Notifications',
            'subtitle' => 'Requests, approvals and system events',
            'active'   => 'notifications',
            'rows'     => $rows,
            'pager'    => $pager,
            'labels'   => [Notify::REQUEST => 'Request', Notify::APPROVAL => 'Approval', Notify::DEVICE => 'Device', Notify::SYSTEM => 'System'],
        ]);
    }

    public function read(int $id)
    {
        Notify::markRead($id);

        $link = $this->request->getPost('link');

        return $link
            ? redirect()->to($link)
            : redirect()->back()->with('success', 'Marked as read.');
    }

    public function readAll()
    {
        Notify::markAll();

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
