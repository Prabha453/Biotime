<?php

namespace App\Controllers;

/**
 * Per-record-type operation history — feeds the "History" button's modal on
 * every list page. Reads base_adminlog (the audit trail written by
 * BaseController::audit()) filtered by an action prefix.
 */
class History extends BaseController
{
    public function feed()
    {
        $prefix = trim((string) $this->request->getGet('prefix'));
        if ($prefix === '' || ! preg_match('/^[a-z0-9_.]+$/i', $prefix)) {
            return $this->response->setJSON([]);
        }

        $rows = db_connect()->query(
            "SELECT l.op_time, l.action, l.targets_repr, l.description,
                    COALESCE(NULLIF(TRIM(u.first_name || ' ' || u.last_name), ''), u.username) AS who
               FROM base_adminlog l
               LEFT JOIN auth_user u ON u.id = l.user_id
              WHERE l.action LIKE ?
              ORDER BY l.op_time DESC
              LIMIT 100",
            [$prefix . '%']
        )->getResultArray();

        return $this->response->setJSON(array_map(static fn ($r) => [
            'when'   => date('Y-m-d H:i', strtotime($r['op_time'])),
            'who'    => $r['who'] ?: preg_replace('/\s+—.*$/', '', (string) $r['description']) ?: 'system',
            'action' => $r['action'],
            'target' => $r['targets_repr'],
        ], $rows));
    }
}
