<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Authenticates against ZKBioTime's own `auth_user` table (Django), the way
 * ZKBioTime does: username + password, password stored as Django's
 * `pbkdf2_sha256$<iterations>$<salt>$<base64 hash>`.
 *
 *   is_superuser            → role "admin"
 *   is_staff (not superuser)→ role "manager"
 *   otherwise               → role "viewer"
 *   is_active = false       → sign-in refused
 *
 * Lockout uses two nullable columns added by
 * 2026-09-06-000001_AddAuthUserLockout.
 */
class AuthUserModel extends Model
{
    protected $table          = 'auth_user';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $skipValidation = true;
    protected $allowedFields  = [
        'username', 'password', 'first_name', 'last_name', 'email', 'emp_pin',
        'is_staff', 'is_active', 'is_superuser', 'is_public',
        'last_login', 'login_count', 'login_ip', 'session_key', 'update_time',
        'failed_attempts', 'locked_until',
    ];

    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 10;

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', trim($username))->first();
    }

    /** Display name: "First Last", else username. */
    public static function displayName(array $u): string
    {
        $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));

        return $n !== '' ? $n : ($u['username'] ?? 'User');
    }

    public static function roleOf(array $u): string
    {
        if (in_array($u['is_superuser'] ?? false, [true, 't', '1', 1], true)) {
            return 'admin';
        }

        // System ▸ Roles: highest tier among the groups this admin belongs to
        $tier = self::groupTier((int) ($u['id'] ?? 0));
        if ($tier !== null) {
            return $tier;
        }

        if (in_array($u['is_staff'] ?? false, [true, 't', '1', 1], true)) {
            return 'manager';
        }

        return 'viewer';
    }

    /** null when the user is in no roles; else 'admin' | 'manager' | 'viewer'. */
    private static function groupTier(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        try {
            $db  = db_connect();
            $ids = $db->table('auth_user_groups')->select('group_id')->where('myuser_id', $userId)->get()->getResultArray();
            if ($ids === []) {
                return null;
            }
            $rank = ['viewer' => 1, 'manager' => 2, 'admin' => 3];
            $best = 0;
            foreach ($ids as $g) {
                $row  = $db->table('base_systemsetting')->select('value')->where('name', 'grouptier_' . (int) $g['group_id'])->get()->getRowArray();
                $t    = $row['value'] ?? 'viewer';
                $best = max($best, $rank[$t] ?? 1);
            }

            return array_search($best, $rank, true) ?: 'viewer';
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isActive(array $u): bool
    {
        return in_array($u['is_active'] ?? true, [true, 't', '1', 1], true);
    }

    /**
     * Verify a plaintext password against a Django password hash.
     * Supports the default pbkdf2_sha256 hasher (what ZKBioTime uses); a
     * bare bcrypt/argon hash falls through to PHP's password_verify.
     */
    public static function verifyDjangoPassword(string $plain, string $encoded): bool
    {
        if (strpos($encoded, 'pbkdf2_sha256$') === 0) {
            $parts = explode('$', $encoded, 4);
            if (count($parts) !== 4) {
                return false;
            }
            [, $iter, $salt, $hash] = $parts;
            $calc = base64_encode(hash_pbkdf2('sha256', $plain, $salt, (int) $iter, 0, true));

            return hash_equals($hash, $calc);
        }

        if (strpos($encoded, 'bcrypt') === 0) {                     // bcrypt$<hash> / bcrypt_sha256$<hash>
            $h = substr($encoded, strrpos($encoded, '$') + 1);

            return password_verify($plain, str_replace('$2b$', '$2y$', $h));
        }

        // last resort: a plain PHP hash
        return password_verify($plain, $encoded);
    }

    /** Encode a plaintext password in Django's default pbkdf2_sha256 format. */
    public static function makeDjangoPassword(string $plain, int $iterations = 390000): string
    {
        $salt = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 12);
        $hash = base64_encode(hash_pbkdf2('sha256', $plain, $salt, $iterations, 0, true));

        return "pbkdf2_sha256\${$iterations}\${$salt}\${$hash}";
    }

    /** Cache: does auth_user carry the app-added lockout columns? (an imported
     *  ZKBioTime DB won't until the AddAuthUserLockout migration runs). */
    private ?bool $lockoutCols = null;

    private function hasLockoutCols(): bool
    {
        return $this->lockoutCols ??= (
            $this->db->fieldExists('failed_attempts', $this->table)
            && $this->db->fieldExists('locked_until', $this->table)
        );
    }

    public function isLocked(array $u): bool
    {
        return $this->hasLockoutCols()
            && ! empty($u['locked_until']) && strtotime($u['locked_until']) > time();
    }

    /** base_securitypolicy — System ▸ Security Policy, falling back to the built-in defaults when unset. */
    private function lockoutPolicy(): array
    {
        $row = $this->db->table('base_securitypolicy')->orderBy('id')->get(1)->getRowArray();

        return [
            'enabled'  => $row === null || $row['failed_locked'] === null
                ? true
                : in_array($row['failed_locked'], [true, 't', '1', 1], true),
            'maxAttempts' => $row && (int) $row['lock_failed_count'] > 0 ? (int) $row['lock_failed_count'] : self::MAX_ATTEMPTS,
            'lockMinutes' => $row && (int) $row['lock_duration'] > 0 ? (int) $row['lock_duration'] : self::LOCK_MINUTES,
        ];
    }

    public function registerFailure(int $id): void
    {
        if (! $this->hasLockoutCols()) {
            return;
        }
        $pol = $this->lockoutPolicy();
        if (! $pol['enabled']) {
            return;
        }
        $u        = $this->find($id);
        $attempts = (int) ($u['failed_attempts'] ?? 0) + 1;
        $data     = ['failed_attempts' => $attempts];
        if ($attempts >= $pol['maxAttempts']) {
            $data['locked_until']    = date('Y-m-d H:i:sP', time() + $pol['lockMinutes'] * 60);
            $data['failed_attempts'] = 0;
        }
        $this->builder()->where('id', $id)->update($data);
    }

    public function touchLogin(int $id, string $ip, string $sessionKey): void
    {
        $u    = $this->find($id);
        $data = [
            'last_login'  => date('Y-m-d H:i:sP'),
            'login_count' => (int) ($u['login_count'] ?? 0) + 1,
            'login_ip'    => substr($ip, 0, 32),
            'session_key' => substr($sessionKey, 0, 32),
        ];
        if ($this->hasLockoutCols()) {
            $data['failed_attempts'] = 0;
            $data['locked_until']    = null;
        }
        $this->builder()->where('id', $id)->update($data);
    }
}
