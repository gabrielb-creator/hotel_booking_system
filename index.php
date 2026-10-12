<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

// Show a small selection of available rooms on the public landing page.
$homeCheckIn = date('Y-m-d');
$homeCheckOut = date('Y-m-d', strtotime('+1 day'));
$roomStmt = $pdo->prepare("SELECT id, name, room_type, capacity, price_per_night, short_description, image_url FROM rooms r WHERE status = 'available' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id=r.id AND b.status IN ('pending','confirmed') AND NOT (b.check_out <= ? OR b.check_in >= ?)) ORDER BY price_per_night ASC LIMIT 3");
$roomStmt->execute([$homeCheckIn, $homeCheckOut]);
$rooms = $roomStmt->fetchAll();
$page_title = 'LuxeStay Hotel | Book Your Stay';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-content">
        <span class="eyebrow">Welcome to LuxeStay</span>
        <h1>Your next getaway starts here.</h1>
        <p>Discover thoughtfully designed rooms, warm hospitality, and a simple way to plan your stay.</p>
        <form class="search-card" action="<?= e(url('rooms/index.php')) ?>" method="get">
            <div>
                <label for="check_in">Check-in</label>
                <input class="input" id="check_in" type="date" name="check_in" required min="<?= e(date('Y-m-d')) ?>">
            </div>
            <div>
                <label for="check_out">Check-out</label>
                <input class="input" id="check_out" type="date" name="check_out" required min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>">
            </div>
            <div>
                <label for="guests">Guests</label>
                <select class="select" id="guests" name="guests">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>><?= $i ?> <?= $i === 1 ? 'guest' : 'guests' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="search-submit"><label>&nbsp;</label><button class="btn btn-primary" type="submit">Find a room</button></div>
        </form>
    </div>
</section>

<section id="about">
    <div class="container">
        <div class="section-head">
            <div><span class="eyebrow" style="color:var(--brand)">A better way to stay</span><h2>Comfort made simple</h2></div>
            <p class="muted">Everything you need for a relaxing visit.</p>
        </div>
        <div class="grid features">
            <article class="card feature"><div class="feature-icon">⌂</div><h3>Comfortable rooms</h3><p class="muted">Choose from welcoming rooms and suites designed for rest and convenience.</p></article>
            <article class="card feature"><div class="feature-icon">✓</div><h3>Easy booking</h3><p class="muted">Check availability and arrange your stay through a straightforward booking process.</p></article>
            <article class="card feature"><div class="feature-icon">♡</div><h3>Guest-first service</h3><p class="muted">Keep track of your reservations and booking details from your own account.</p></article>
        </div>
    </div>
</section>

<section style="background:#fff">
    <div class="container">
        <div class="section-head">
            <div><span class="eyebrow" style="color:var(--brand)">Stay your way</span><h2>Explore our rooms</h2><p class="muted">Rooms available tonight. Search your exact dates to see future availability.</p></div>
            <a class="btn btn-outline" href="<?= e(url('rooms/index.php')) ?>">View all rooms</a>
        </div>
        <div class="grid cards-3">
            <?php if (!$rooms): ?>
                <div class="card empty" style="grid-column:1/-1">Rooms will appear here once they have been added to the system.</div>
            <?php endif; ?>
            <?php foreach ($rooms as $room): ?>
                <article class="room-card">
                    <img src="<?= e($room['image_url'] ?: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80') ?>" alt="<?= e($room['name']) ?>">
                    <div class="room-body">
                        <div class="room-top"><h3><?= e($room['name']) ?></h3><div class="price">₱<?= number_format((float)$room['price_per_night'], 0) ?><small>/night</small></div></div>
                        <p class="muted"><?= e($room['short_description']) ?></p>
                        <div class="chips"><span class="chip"><?= e($room['room_type']) ?></span><span class="chip">Up to <?= (int)$room['capacity'] ?> guests</span></div>
                        <a class="btn btn-primary" href="<?= e(url('rooms/view.php?id=' . (int)$room['id'])) ?>">View room</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section>
    <div class="container card" style="padding:clamp(25px,5vw,55px);display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap">
        <div><h2 style="margin:0 0 8px">Ready to plan your stay?</h2><p class="muted" style="margin:0">Find your room and make your next trip feel effortless.</p></div>
        <a class="btn btn-primary" href="<?= e(url('rooms/index.php')) ?>">Browse available rooms</a>
    </div>
</section>

<script>
(function () {
    const checkIn = document.getElementById('check_in');
    const checkOut = document.getElementById('check_out');
    if (!checkIn || !checkOut) return;
    checkIn.addEventListener('change', function () {
        if (!checkIn.value) return;
        const nextDay = new Date(checkIn.value + 'T00:00:00');
        nextDay.setDate(nextDay.getDate() + 1);
        const minOut = nextDay.getFullYear() + '-' + String(nextDay.getMonth() + 1).padStart(2, '0') + '-' + String(nextDay.getDate()).padStart(2, '0');
        checkOut.min = minOut;
        if (checkOut.value && checkOut.value < minOut) checkOut.value = minOut;
    });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
