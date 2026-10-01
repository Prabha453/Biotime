<?php /** @var string $tab */ ?>
<nav class="subnav">
    <a href="<?= route_to('payroll') ?>" class="<?= $tab === 'payslips' ? 'is-active' : '' ?>">Payslips</a>
    <a href="<?= route_to('payroll.structures') ?>" class="<?= $tab === 'salary' ? 'is-active' : '' ?>">Salary structures</a>
    <a href="<?= route_to('payroll.loans') ?>" class="<?= $tab === 'loans' ? 'is-active' : '' ?>">Loan</a>
    <a href="<?= route_to('payroll.advances') ?>" class="<?= $tab === 'advances' ? 'is-active' : '' ?>">Salary advance</a>
    <a href="<?= route_to('payroll.increases') ?>" class="<?= $tab === 'increases' ? 'is-active' : '' ?>">Extra increase</a>
    <a href="<?= route_to('payroll.deductions') ?>" class="<?= $tab === 'deductions' ? 'is-active' : '' ?>">Extra deduction</a>
    <a href="<?= route_to('payroll.reimbursements') ?>" class="<?= $tab === 'reimbursements' ? 'is-active' : '' ?>">Reimbursement</a>
    <a href="<?= route_to('payroll.bank') ?>" class="<?= $tab === 'bank' ? 'is-active' : '' ?>">Bank details</a>
    <a href="<?= route_to('payroll.reports') ?>" class="<?= $tab === 'reports' ? 'is-active' : '' ?>">Report</a>
</nav>
