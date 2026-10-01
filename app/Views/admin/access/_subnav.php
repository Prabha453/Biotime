<nav class="subnav">
    <?php foreach ($pages as $key => [$label, $routeName]): ?>
        <a href="<?= route_to($routeName) ?>" class="<?= $tab === $key ? 'is-active' : '' ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="ext-note"><i class="bi bi-info-circle"></i>
    These records sync to the ZKTeco terminal. Levels bind doors (areas) + a weekly time zone; a privilege links one employee to a level.
</div>
