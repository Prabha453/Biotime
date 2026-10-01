<?php

namespace App\Models;

use CodeIgniter\Model;

class TransactionModel extends Model
{
    protected $table        = 'iclock_transaction';
    protected $primaryKey   = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** Punch log builder with employee + device names resolved. */
    public function feed()
    {
        return $this->select("iclock_transaction.id, iclock_transaction.emp_code,
                to_char(iclock_transaction.punch_time,'YYYY-MM-DD HH24:MI:SS') AS punch_time,
                iclock_transaction.punch_state, iclock_transaction.verify_type,
                iclock_transaction.source, iclock_transaction.upload_time, iclock_transaction.terminal_sn,
                NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name,
                e.id AS emp_id, term.alias AS device")
            ->join('personnel_employee e', 'e.id = iclock_transaction.emp_id', 'left')
            ->join('iclock_terminal term', 'term.id = iclock_transaction.terminal_id', 'left');
    }
}
