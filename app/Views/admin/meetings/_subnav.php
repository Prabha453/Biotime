<div class="subnav mb-3">
    <a class="<?= ($nav ?? '') === 'rooms' ? 'is-active' : '' ?>" href="<?= route_to('meetings') ?>">Rooms</a>
    <a class="<?= ($nav ?? '') === 'bookings' ? 'is-active' : '' ?>" href="<?= route_to('meetings.bookings') ?>">Bookings</a>
</div>
