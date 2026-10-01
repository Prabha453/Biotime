<?php

namespace App\Libraries;

/**
 * In-app notification feed (accounts_usernotification).
 *
 * The panel authenticates against its own `users` table but ZKBioTime keys
 * notifications to `auth_user`; there is effectively one auth_user row (the
 * same one base_adminlog uses), so notifications are a single shared
 * "needs attention" stream visible to every signed-in admin.
 */
final class Notify
{
    /** category codes (mirrors ZKBioTime's loose int categories) */
    public const REQUEST = 1;
    public const APPROVAL = 2;
    public const DEVICE = 3;
    public const SYSTEM = 4;

    private static ?int $userId = null;

    /** Insert a notification. Never throws. */
    public static function push(string $content, int $category = self::SYSTEM, ?string $link = null): void
    {
        try {
            $db = db_connect();
            $db->table('accounts_usernotification')->insert([
                'category'  => $category,
                'event'     => 0,
                'content'   => $link ? $content . ' ||' . $link : $content,
                'send_time' => date('Y-m-d H:i:sP'),
                'status'    => 0,           // 0 = unread
                'user_id'   => self::userId($db),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Notify::push ' . $e->getMessage());
        }
    }

    public static function unreadCount(): int
    {
        try {
            return (int) db_connect()->table('accounts_usernotification')
                ->where('user_id', self::userId(db_connect()))
                ->where('status', 0)
                ->where("content NOT LIKE '{%'")   // hide ZKBioTime's legacy JSON device notices
                ->countAllResults();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array<int,array{id:int,content:string,link:?string,send_time:string,unread:bool}> */
    public static function recent(int $limit = 8): array
    {
        try {
            $rows = db_connect()->table('accounts_usernotification')
                ->select("id, content, to_char(send_time,'YYYY-MM-DD HH24:MI') AS send_time, status")
                ->where('user_id', self::userId(db_connect()))
                ->where("content NOT LIKE '{%'")
                ->orderBy('id', 'DESC')
                ->get($limit)->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static function ($r) {
            [$text, $link] = array_pad(explode(' ||', (string) $r['content'], 2), 2, null);

            return [
                'id'        => (int) $r['id'],
                'content'   => $text,
                'link'      => $link,
                'send_time' => $r['send_time'],
                'unread'    => (int) $r['status'] === 0,
            ];
        }, $rows);
    }

    public static function markRead(int $id): void
    {
        db_connect()->table('accounts_usernotification')
            ->where('id', $id)->where('user_id', self::userId(db_connect()))
            ->update(['status' => 1, 'read_time' => date('Y-m-d H:i:sP')]);
    }

    public static function markAll(): void
    {
        db_connect()->table('accounts_usernotification')
            ->where('user_id', self::userId(db_connect()))->where('status', 0)
            ->where("content NOT LIKE '{%'")
            ->update(['status' => 1, 'read_time' => date('Y-m-d H:i:sP')]);
    }

    private static function userId($db): int
    {
        if (self::$userId === null) {
            $row = $db->query('SELECT id FROM auth_user ORDER BY id LIMIT 1')->getRow();
            self::$userId = $row ? (int) $row->id : 1;
        }

        return self::$userId;
    }
}
