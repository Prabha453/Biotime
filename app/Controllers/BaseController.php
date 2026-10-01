<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    protected $helpers = ['form', 'url', 'text', 'ui', 'field', 'grid'];

    /** cached auth_user id used as the FK owner of base_adminlog rows */
    private static ?int $auditUserId = null;

    /**
     * Paginate a raw query builder. Returns [rows, pager]. Reads the page
     * number from `?page` (or `?page_{group}` for a named group so two
     * lists on one page paginate independently).
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     * @return array{0:array<int,array>,1:\CodeIgniter\Pager\Pager}
     */
    /** rows-per-page values the ?per selector (and pager_bar helper) allow */
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    protected function paged($builder, int $perPage = 20, string $group = 'default'): array
    {
        $pager = \Config\Services::pager();
        $param = $group === 'default' ? 'page' : 'page_' . $group;
        $page  = max(1, (int) ($this->request->getGet($param) ?: 1));

        // honour a ?per (or ?per_<group>) rows-per-page choice from pager_bar()
        $perParam = $group === 'default' ? 'per' : 'per_' . $group;
        $reqPer   = (int) ($this->request->getGet($perParam) ?: 0);
        if (in_array($reqPer, self::PER_PAGE_OPTIONS, true)) {
            $perPage = $reqPer;
        }

        $total = (clone $builder)->countAllResults();
        $rows  = $builder->get($perPage, ($page - 1) * $perPage)->getResultArray();

        $pager->makeLinks($page, $perPage, $total, 'tt', 0, $group);

        return [$rows, $pager];
    }

    /**
     * Paginate an already-built PHP array (calculator reports etc.) the same
     * way paged() does for a query builder — honours ?page / ?per (or the
     * ?page_{group} / ?per_{group} variants) so pager_bar() renders correctly.
     *
     * @param array<int,mixed> $all
     * @return array{0:array<int,mixed>,1:\CodeIgniter\Pager\Pager}
     */
    protected function pagedArray(array $all, int $perPage = 50, string $group = 'default'): array
    {
        $pager = \Config\Services::pager();
        $param = $group === 'default' ? 'page' : 'page_' . $group;
        $page  = max(1, (int) ($this->request->getGet($param) ?: 1));

        $perParam = $group === 'default' ? 'per' : 'per_' . $group;
        $reqPer   = (int) ($this->request->getGet($perParam) ?: 0);
        if (in_array($reqPer, self::PER_PAGE_OPTIONS, true)) {
            $perPage = $reqPer;
        }

        $total = count($all);
        $page  = min($page, max(1, (int) ceil($total / $perPage)));
        $rows  = array_slice($all, ($page - 1) * $perPage, $perPage);

        $pager->makeLinks($page, $perPage, $total, 'tt', 0, $group);

        return [$rows, $pager];
    }

    /**
     * ZKBioTime-style advanced Filter builder. Reads `?fb[N][field|op|val]`
     * rows from the request and ANDs them onto $builder, using $spec as the
     * whitelist:  ['label_or_key' => [<qualified column>, <type>], …]
     * type ∈ text | number | date | bool | select.
     *
     * @return int how many clauses were applied (for a "Filter (N)" badge)
     */
    protected function filterBuilder($builder, array $spec): int
    {
        $rows = $this->request->getGet('fb');
        if (! is_array($rows)) {
            return 0;
        }

        $applied = 0;
        foreach ($rows as $r) {
            $field = (string) ($r['field'] ?? '');
            $op    = (string) ($r['op'] ?? '');
            $val   = trim((string) ($r['val'] ?? ''));
            $val2  = trim((string) ($r['val2'] ?? ''));
            if (! isset($spec[$field]) || ($val === '' && $op !== 'empty' && $op !== 'not_empty')) {
                continue;
            }
            [$col, $type] = $spec[$field];

            switch ($op) {
                case 'contains':     $builder->like($col, $val, 'both', null, true); break;
                case 'not_contains': $builder->notLike($col, $val, 'both', null, true); break;
                case 'starts':       $builder->like($col, $val, 'after', null, true); break;
                case 'ends':         $builder->like($col, $val, 'before', null, true); break;
                case 'eq':           $builder->where($col, $val); break;
                case 'ne':           $builder->where("{$col} !=", $val); break;
                case 'gt':  case 'after':  $builder->where("{$col} >", $val); break;
                case 'gte':                $builder->where("{$col} >=", $val); break;
                case 'lt':  case 'before': $builder->where("{$col} <", $val); break;
                case 'lte':               $builder->where("{$col} <=", $val); break;
                case 'between':
                    if ($val !== '') { $builder->where("{$col} >=", $val); }
                    if ($val2 !== '') { $builder->where("{$col} <=", $val2); }
                    break;
                case 'empty':     $builder->groupStart()->where("{$col} IS NULL", null, false)->orWhere($col, '')->groupEnd(); break;
                case 'not_empty': $builder->where("{$col} IS NOT NULL", null, false)->where("{$col} !=", ''); break;
                default: continue 2;
            }
            $applied++;
        }

        return $applied;
    }

    /**
     * Run a DB write and turn any database error (FK violation, unique
     * clash, NOT NULL) into a friendly flash message + redirect back,
     * instead of a 500. Returns null on failure, the closure's value on success.
     */
    protected function dbGuard(\Closure $write, string $failMessage = 'That could not be saved — check the linked records still exist.')
    {
        try {
            return $write();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'dbGuard: ' . $e->getMessage());

            return null;
        } catch (\Throwable $e) {
            log_message('error', 'dbGuard: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Wrap a multi-table write in a transaction AND dbGuard. The closure
     * runs between transStart()/transComplete(); any exception or a failed
     * transStatus() rolls the whole thing back.
     *
     * @return bool  true when everything committed, false on any failure
     */
    protected function tx(\Closure $fn): bool
    {
        $db = db_connect();
        $ok = $this->dbGuard(function () use ($db, $fn) {
            $db->transStart();
            $fn($db);
            $db->transComplete();

            return $db->transStatus();
        });

        return $ok === true;
    }

    /**
     * Write an audit row to ZKBioTime's base_adminlog so every create /
     * update / delete / approval is traceable. Never throws — a logging
     * failure must not break the action it records.
     *
     * @param string      $action  short verb, e.g. "employee.update"
     * @param string      $target  what was acted on, e.g. an emp code or id
     * @param string|null $detail  optional free-text (field changes, counts)
     */
    protected function audit(string $action, string $target = '', ?string $detail = null): void
    {
        try {
            $db  = db_connect();
            $who = session()->get('userName') ?: 'system';
            $db->table('base_adminlog')->insert([
                'action'        => substr($action, 0, 50),
                'targets_repr'  => $target !== '' ? substr($target, 0, 4000) : null,
                'action_status' => 0,
                'description'   => trim($who . ($detail !== null ? ' — ' . $detail : '')),
                'ip_address'    => $this->request->getIPAddress(),
                'can_routable'  => false,
                'op_time'       => date('Y-m-d H:i:sP'),
                'user_id'       => $this->auditUserId($db),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'audit: ' . $e->getMessage());
        }
    }

    private function auditUserId($db): int
    {
        // The signed-in user is now a real auth_user row (ZKBioTime login).
        $sid = (int) (session()->get('userId') ?? 0);
        if ($sid > 0) {
            return $sid;
        }
        if (self::$auditUserId === null) {
            $row = $db->query('SELECT id FROM auth_user ORDER BY id LIMIT 1')->getRow();
            self::$auditUserId = $row ? (int) $row->id : 1;
        }

        return self::$auditUserId;
    }

    /** Current admin's role: admin | manager | viewer. */
    protected function role(): string
    {
        return (string) (session()->get('userRole') ?: 'viewer');
    }

    /**
     * Guard an action to one of the given roles. Returns a redirect
     * response when the current admin is not allowed, null when they are.
     */
    protected function requireRole(string ...$roles)
    {
        if (! in_array($this->role(), $roles, true)) {
            return redirect()->back()->with('error', 'You do not have permission to do that.');
        }

        return null;
    }

    /**
     * Return the first message whose probe SQL returns a row, else ''. Used by
     * every delete() to refuse a row that is still referenced elsewhere.
     *
     * @param array<string,string> $probes  message => "SELECT 1 FROM … LIMIT 1"
     */
    protected function firstBlocker($db, array $probes): string
    {
        foreach ($probes as $message => $sql) {
            if ($db->query($sql)->getFirstRow()) {
                return $message;
            }
        }

        return '';
    }

    /**
     * Insert-or-update one row inside a transaction. Returns the row id on
     * success, null on any DB failure. $onInsert is merged only for new rows
     * (defaults, create_time, create_user…).
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $onInsert
     */
    protected function saveRow(string $table, array $data, int $id, array $onInsert = []): ?int
    {
        $newId = $id;
        $ok    = $this->tx(static function ($db) use ($table, $data, &$newId, $onInsert): void {
            if ($newId > 0) {
                $db->table($table)->where('id', $newId)->update($data);
            } else {
                $db->table($table)->insert($data + $onInsert);
                $newId = (int) $db->insertID();
            }
        });

        return $ok ? $newId : null;
    }

    /**
     * Delete one row after checking blockers. Returns '' on success or the
     * blocker message. Never throws.
     *
     * @param array<string,string> $blockers
     */
    protected function deleteRow(string $table, int $id, array $blockers = []): string
    {
        $db  = db_connect();
        $blk = $this->firstBlocker($db, $blockers);
        if ($blk !== '') {
            return $blk;
        }
        $this->dbGuard(static fn () => $db->table($table)->where('id', $id)->delete());

        return '';
    }

    /** Standard "ZK create_time / create_user / status" stamp for new rows. */
    protected function stamp(): array
    {
        return [
            'status'      => 0,
            'create_time' => date('Y-m-d H:i:sP'),
            'create_user' => session()->get('userName'),
            'change_time' => date('Y-m-d H:i:sP'),
            'change_user' => session()->get('userName'),
        ];
    }

    // ---------------------------------------------------------------------
    //  AJAX / JSON responses — used by the Progressive-AJAX CRUD layer
    //  (public/assets/js/tt.js + tt-crud.js).
    // ---------------------------------------------------------------------

    /** True when the browser expects a JSON reply (fetch sets X-Requested-With). */
    protected function wantsJson(): bool
    {
        return $this->request->isAJAX();
    }

    /**
     * Uniform success payload. Pass `html` to have the caller swap a region,
     * `data` for anything else. The fresh CSRF hash rides along so the client
     * can keep posting.
     */
    protected function jsonOk(string $message = '', array $extra = [])
    {
        return $this->response->setJSON([
            'ok'      => true,
            'message' => $message,
            'csrf'    => csrf_hash(),
        ] + $extra);
    }

    /**
     * Uniform failure payload. `$errors` is a field => message map (feeds
     * inline .is-invalid); `$message` is the toast text.
     */
    protected function jsonFail(string $message, array $errors = [], int $status = 422)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => false,
            'message' => $message,
            'errors'  => (object) $errors,
            'csrf'    => csrf_hash(),
        ]);
    }

    /**
     * End a CRUD action. On AJAX → JSON (optionally carrying a re-rendered
     * list partial in `html`); otherwise the classic redirect + flash.
     *
     * @param string      $flashType 'success' | 'error'
     * @param string|null $listHtml  re-rendered results region for AJAX callers
     */
    protected function crudDone(string $redirectUrl, string $flashType, string $message, ?string $listHtml = null)
    {
        if ($this->wantsJson()) {
            return $flashType === 'success'
                ? $this->jsonOk($message, $listHtml !== null ? ['html' => $listHtml] : [])
                : $this->jsonFail($message);
        }

        return redirect()->to($redirectUrl)->with($flashType, $message);
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Align the DB session wall-clock with the biometric device timezone so
        // raw now() / ::date comparisons in dashboards & reports agree with the
        // AttendanceCalculator. The timezone itself is derived from this
        // database's own data (see AttPolicy::detectTimezoneOffset) — not a
        // static config value — so it self-adjusts per install.
        try {
            db_connect()->query("SET TIME ZONE '" . \App\Libraries\AttPolicy::load()->timezone . "'");
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
