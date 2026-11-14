<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $isZip = in_array($type, ['photos', 'documents'], true); ?>
<?= page_head($title, 'Bulk-load personnel data from a spreadsheet or archive', '', [
    ['Home', route_to('dashboard')], ['Employees', route_to('employees')], ['Import'],
]) ?>

<div class="subnav mb-3">
    <?php foreach ($types as $k => $label): ?>
        <a class="<?= $type === $k ? 'is-active' : '' ?>" href="<?= route_to('imports.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <?= form_open_multipart(route_to('imports.run', $type)) ?>
            <div class="mb-3">
                <label class="form-label"><?= $isZip ? 'ZIP archive' : 'Excel (.xlsx) or CSV file' ?><?= req() ?></label>
                <input type="file" name="file" class="form-control" required
                    accept="<?= $isZip ? '.zip' : '.xlsx,.csv,.txt,text/csv' ?>" aria-describedby="importFileHint">
                <div id="importFileHint" class="form-text">
                    <?= $isZip ? 'Supported format: ZIP archive (.zip).' : 'Supported formats: Excel (.xlsx), CSV (.csv), or comma-separated text (.txt). Use the template headers.' ?>
                </div>
                <?php if ($isZip): ?>
                    <div class="form-text">Every file must match an existing record. Files are checked before saving; errors cancel the whole import. Matching attachments are replaced. Maximum: 20 MB per file and 100 MB total after extraction.</div>
                <?php endif; ?>
                <?php if (! $isZip): ?>
                    <div class="form-text">All rows are validated before saving. If any row has an error, no records are saved. Dates must use YYYY-MM-DD.</div>
                <?php endif; ?>
            </div>
            <?php if (! $isZip): ?>
                <div class="mb-3">
                    <label class="form-label" for="importExisting">Existing Data</label>
                    <select name="existing" id="importExisting" class="form-select">
                        <option value="ignore">Ignore</option>
                        <option value="overwrite">Overwrite</option>
                    </select>
                </div>
            <?php endif; ?>
            <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Import</button>
            <?php if (! $isZip): ?>
                <a href="<?= route_to('imports.template', $type) ?>" class="btn btn-outline-secondary ms-1">
                    <i class="bi bi-download me-1"></i>Download template
                </a>
            <?php endif; ?>
            <?= form_close() ?>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h6 class="mb-2">Format</h6>
            <?php if ($isZip && $type === 'photos'): ?>
                <p class="small text-muted mb-1">A ZIP of image files (jpg / jpeg / png / gif), each named after the employee ID:</p>
                <pre class="small bg-body-tertiary p-2 rounded mono mb-0">1001.jpg
1002.png
E-204.jpg</pre>
            <?php elseif ($isZip): ?>
                <p class="small text-muted mb-1">A ZIP of documents (pdf / image / doc), named <span class="mono">&lt;emp&gt;_&lt;certCode&gt;.ext</span> — the cert code is optional (first certification is used):</p>
                <pre class="small bg-body-tertiary p-2 rounded mono mb-0">1001_FIRSTAID.pdf
1002.pdf</pre>
            <?php else: ?>
                <p class="small text-muted mb-1">Columns (first row = headers):</p>
                <pre class="small bg-body-tertiary p-2 rounded mono mb-0" style="white-space:pre-wrap"><?= esc($columns) ?></pre>
                <?php if (! empty($notes)): ?>
                    <ul class="small text-muted mt-2 mb-0 ps-3">
                        <?php foreach ($notes as $n): ?><li><?= esc($n) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
