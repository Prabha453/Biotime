<?php /** @var array $kinds  @var string $tab */ ?>
<nav class="subnav">
    <?php foreach ($kinds as $k => [, , $lbl]): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('payrollconfig.formula', $k) ?>"><?= esc($lbl) ?></a>
    <?php endforeach; ?>
</nav>
