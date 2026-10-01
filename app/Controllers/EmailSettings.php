<?php

namespace App\Controllers;

use App\Libraries\Mailer;

/**
 * SMTP configuration + transactional email templates (base_emailtemplate).
 * Admin-only (gated in Routes).
 */
class EmailSettings extends BaseController
{
    public function index(): string
    {
        $db = db_connect();

        return view('admin/email/index', [
            'title'     => 'Email',
            'subtitle'  => 'Outgoing mail server and notification templates',
            'active'    => 'email',
            'cfg'       => Mailer::config(),
            'configured'=> Mailer::configured(),
            'events'    => Mailer::EVENTS,
            'templates' => $db->query(
                "SELECT id, event, subject, template, enable FROM base_emailtemplate
                  WHERE event IN (" . implode(',', array_keys(Mailer::EVENTS)) . ')
               ORDER BY event'
            )->getResultArray(),
        ]);
    }

    public function save()
    {
        $rules = ['host' => 'required', 'port' => 'required|is_natural_no_zero', 'from' => 'required|valid_email'];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $p  = $this->request;
        $ok = Mailer::save([
            'host'     => $p->getPost('host'),
            'port'     => $p->getPost('port'),
            'user'     => $p->getPost('user'),
            'pass'     => $p->getPost('pass'),
            'crypto'   => $p->getPost('crypto'),
            'from'     => $p->getPost('from'),
            'fromName' => $p->getPost('fromName'),
        ]);
        $this->audit('email.settings.update', $p->getPost('host'));

        return redirect()->to(route_to('email'))->with($ok ? 'success' : 'error',
            $ok ? 'Email settings saved.' : 'Could not save email settings.');
    }

    public function test()
    {
        $to = (string) $this->request->getPost('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Enter a valid test address.');
        }
        [$ok, $msg] = Mailer::send($to, 'TimeTrack test email',
            "This is a test message from TimeTrack.\nIf you received it, SMTP is working.");
        $this->audit('email.test', $to, $ok ? 'ok' : 'fail');

        return redirect()->to(route_to('email'))->with($ok ? 'success' : 'error',
            $ok ? "Test email sent to {$to}." : 'Send failed: ' . mb_strimwidth($msg, 0, 240, '…'));
    }

    public function saveTemplate()
    {
        $event = (int) $this->request->getPost('event');
        if (! isset(Mailer::EVENTS[$event])) {
            return redirect()->back()->with('error', 'Unknown event.');
        }
        if (! $this->validate(['subject' => 'permit_empty|max_length[100]', 'template' => 'required'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'tpl' . $event);
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'event'        => $event,
            'subject'      => $p->getPost('subject') ?: null,
            'template'     => $p->getPost('template'),
            'enable'       => (bool) $p->getPost('enable'),
            'category'     => 0, 'sub_category' => 0, 'receiver' => 0, 'status' => 0,
        ];

        $db = db_connect();
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('base_emailtemplate')->where('id', $id)->update($data)
            : $db->table('base_emailtemplate')->insert($data + ['create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Template could not be saved.');
        }
        $this->audit($id ? 'email.template.update' : 'email.template.create', Mailer::EVENTS[$event]);

        return redirect()->to(route_to('email'))->with('success', 'Template saved.');
    }

    public function deleteTemplate(int $id)
    {
        db_connect()->table('base_emailtemplate')->where('id', $id)->delete();
        $this->audit('email.template.delete', (string) $id);

        return redirect()->to(route_to('email'))->with('success', 'Template removed.');
    }
}
