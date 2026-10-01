<?php
/**
 * TimeTrack pagination template (aliased as `default_full` and `tt`).
 *
 * @var \CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(2);
if ($pager->getPageCount() <= 1) {
    return;
}
$cur   = $pager->getCurrentPageNumber();
$from  = ($cur - 1) * $pager->getPerPage() + 1;
$to    = min($cur * $pager->getPerPage(), $pager->getTotal());
$links = $pager->links();
$firstShown = $links[0]['title'] ?? 1;
$lastShown  = end($links)['title'] ?? $pager->getPageCount();
?>
<nav class="pager-wrap" aria-label="Pagination">
    <span class="range"><b><?= number_format($from) ?>&ndash;<?= number_format($to) ?></b> of <b><?= number_format($pager->getTotal()) ?></b></span>
    <ul class="pagination">
        <li class="page-item <?= $pager->hasPreviousPage() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->getPreviousPage() ?: '#' ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php if ((int) $firstShown > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getFirst() ?>">1</a></li>
            <?php if ((int) $firstShown > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($links as $link): ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>"><?= $link['title'] ?></a>
            </li>
        <?php endforeach; ?>
        <?php if ((int) $lastShown < $pager->getPageCount()): ?>
            <?php if ((int) $lastShown < $pager->getPageCount() - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getLast() ?>"><?= $pager->getPageCount() ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $pager->hasNextPage() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->getNextPage() ?: '#' ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
        </li>
    </ul>
</nav>
