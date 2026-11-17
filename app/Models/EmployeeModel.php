<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table         = 'personnel_employee';
    protected $primaryKey    = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields  = [];

    /** Base builder with department / position / area joined + display name. */
    public function withMeta()
    {
        return $this->select("personnel_employee.id, personnel_employee.emp_code,
                NULLIF(TRIM(CONCAT(personnel_employee.first_name,' ',personnel_employee.last_name)),'') AS name,
                personnel_employee.first_name, personnel_employee.last_name, personnel_employee.photo,
                personnel_employee.hire_date, personnel_employee.gender, personnel_employee.mobile,
                personnel_employee.email, personnel_employee.department_id, personnel_employee.position_id,
                personnel_employee.emp_code_digit, personnel_employee.emp_type, personnel_employee.national,
                personnel_employee.is_active,
                d.dept_name, p.position_name,
                (SELECT STRING_AGG(DISTINCT a.area_name, ', ' ORDER BY a.area_name)
                   FROM personnel_employee_area ea
                   JOIN personnel_area a ON a.id = ea.area_id
                  WHERE ea.employee_id = personnel_employee.id) AS area_name,
                (SELECT resign_date FROM personnel_resign r WHERE r.employee_id = personnel_employee.id) AS resign_date")
            ->join('personnel_department d', 'd.id = personnel_employee.department_id', 'left')
            ->join('personnel_position p', 'p.id = personnel_employee.position_id', 'left');
    }

    public function departments(): array
    {
        return $this->db->table('personnel_department')
            ->select('id, dept_name')->orderBy('dept_name')->get()->getResultArray();
    }

    public function positions(): array
    {
        return $this->db->table('personnel_position')
            ->select('id, position_name')->orderBy('position_name')->get()->getResultArray();
    }

    public function companies(): array
    {
        return $this->db->table('personnel_company')
            ->select('id, company_name')->orderBy('is_default', 'DESC')->orderBy('company_name')->get()->getResultArray();
    }
}
