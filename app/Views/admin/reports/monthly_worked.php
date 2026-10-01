<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $q = http_build_query(['y' => $year, 'm' => $month, 'dept' => $dept]); ?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . export_dropdown(route_to('reports.monthlyWorkedHrs.export') . '?' . $q)) ?>

<?= view('admin/reports/_monthly_filterbar', ['year' => $year, 'month' => $month, 'dept' => $dept, 'departments' => $departments]) ?>

<?= view('admin/reports/_monthly_grid', [
    'rows' => $rows, 'dim' => $dim, 'summaryCodes' => $summaryCodes,
    'cellOf' => $cellOf, 'labelOf' => $labelOf, 'valOf' => $valOf, 'pager' => $pager ?? null,
]) ?>
<?= $this->endSection() ?>
