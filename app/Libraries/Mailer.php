<?php

namespace App\Libraries;

/**
 * SMTP settings + templated transactional email.
 *
 * Config is one JSON row in base_systemsetting (name='tt_email_config' —
 * value is a text column, so length is not a concern). Templates live in
 * ZKBioTime's base_emailtemplate keyed by an integer `event`.
 *
 * Every send is best-effort: a missing / broken SMTP config makes send()
 * return false, it never throws into the calling request.
 */
final class Mailer
{
    /** event codes used by this app's hooks */
    public const EV_REQUEST_SUBMITTED = 10;
    public const EV_REQUEST_APPROVED  = 11;
    public const EV_REQUEST_REJECTED  = 12;
    public const EV_CERT_EXPIRING     = 20;

    public const EVENTS = [
        self::EV_REQUEST_SUBMITTED => 'Request submitted',
        self::EV_REQUEST_APPROVED  => 'Request approved',
        self::EV_REQUEST_REJECTED  => 'Request rejected',
        self::EV_CERT_EXPIRING     => 'Certification expiring',
    ];

    private static ?array $cfg = null;

    public static function config(): array
    {
        if (self::$cfg === null) {
            self::$cfg = [];
            try {
                $row = db_connect()->table('base_systemsetting')
                    ->where('name', 'tt_email_config')
                    ->get()->getRowArray();
                if ($row) {
                    self::$cfg = json_decode($row['value'] ?: '{}', true) ?: [];
                }
            } catch (\Throwable $e) {
                // leave empty
            }
        }

        return self::$cfg;
    }

    public static function configured(): bool
    {
        $c = self::config();

        return ! empty($c['host']) && ! empty($c['from']);
    }

    public static function save(array $data): bool
    {
        $json = json_encode([
            'host'     => trim($data['host'] ?? ''),
            'port'     => (int) ($data['port'] ?? 587),
            'user'     => trim($data['user'] ?? ''),
            'pass'     => (string) ($data['pass'] ?? ''),
            'crypto'   => in_array($data['crypto'] ?? 'tls', ['tls', 'ssl', ''], true) ? ($data['crypto'] ?? 'tls') : 'tls',
            'from'     => trim($data['from'] ?? ''),
            'fromName' => trim($data['fromName'] ?? 'TimeTrack'),
        ]);

        try {
            $db  = db_connect();
            $has = $db->table('base_systemsetting')->where('name', 'tt_email_config')->countAllResults();
            $has
                ? $db->table('base_systemsetting')->where('name', 'tt_email_config')->update(['value' => $json, 'change_time' => date('Y-m-d H:i:sP')])
                : $db->table('base_systemsetting')->insert(['name' => 'tt_email_config', 'value' => $json, 'status' => 0, 'create_time' => date('Y-m-d H:i:sP')]);
            self::$cfg = null;

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Mailer::save ' . $e->getMessage());

            return false;
        }
    }

    /** @return array{0:bool,1:string} [ok, message] */
    public static function send(string $to, string $subject, string $body): array
    {
        $c = self::config();
        if (empty($c['host']) || empty($c['from']) || ! $to) {
            return [false, 'SMTP is not configured, or no recipient address.'];
        }

        try {
            $email = \Config\Services::email();
            $email->initialize([
                'protocol'   => 'smtp',
                'SMTPHost'   => $c['host'],
                'SMTPPort'   => (int) ($c['port'] ?: 587),
                'SMTPUser'   => $c['user'] ?? '',
                'SMTPPass'   => $c['pass'] ?? '',
                'SMTPCrypto' => $c['crypto'] ?? 'tls',
                'SMTPTimeout'=> 12,
                'mailType'   => 'html',
                'charset'    => 'UTF-8',
                'wordWrap'   => true,
            ]);
            $email->setFrom($c['from'], $c['fromName'] ?? 'TimeTrack');
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage(nl2br(esc($body)));

            if ($email->send(false)) {
                return [true, 'Sent.'];
            }

            return [false, strip_tags((string) $email->printDebugger(['headers']))];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /** Look up an enabled template for an event. */
    public static function template(int $event): ?array
    {
        try {
            return db_connect()->table('base_emailtemplate')
                ->where('event', $event)->where('enable', true)
                ->orderBy('id', 'DESC')
                ->get()->getRowArray() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Render {{placeholders}} in the enabled template for $event and send.
     * No-ops silently when SMTP or the template is missing.
     */
    public static function fire(int $event, ?string $to, array $vars = []): void
    {
        if (! $to || ! self::configured()) {
            return;
        }
        $tpl = self::template($event);
        if (! $tpl) {
            return;
        }
        $repl = [];
        foreach ($vars as $k => $v) {
            $repl['{{' . $k . '}}'] = (string) $v;
        }
        $subject = strtr($tpl['subject'] ?: (self::EVENTS[$event] ?? 'Notification'), $repl);
        $body    = strtr($tpl['template'], $repl);
        self::send($to, $subject, $body);
    }
}
