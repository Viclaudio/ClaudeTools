<?php
session_start();
require 'config.php';

// Get category from URL
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
if ($category) {
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE category = ? ORDER BY name ASC");
    $stmt->bind_param("s", $category);
} elseif ($search) {
    $searchTerm = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE name LIKE ? OR brand LIKE ? OR description LIKE ? ORDER BY name ASC");
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
} else {
    $stmt = $conn->prepare("SELECT * FROM equipment ORDER BY category, name ASC");
}

$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = $category ? $category : ($search ? "Search: $search" : 'All Equipment');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ClaudTools</title>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<header>
    <div class="marquee-banner">
        <div class="marquee-content">
            Welcome to ClaudeTools — Want the best to get the Job Done? Then ClaudeTools Got you covered!
        </div>
    </div>

    <div class="nav-1">
        <div>
            <a href="./index.html"><img src="./Assets/logo Yellow.png" alt="logo" width="60px" class="logo"></a>
        </div>
        <form action="catalog.php" method="GET">
            <div class="search-box">
                <input type="search" name="search" placeholder="Search equipment..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </div>
            <div class="account-dropdown">
                <button type="button" id="accountBtn">Account ▾</button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="./login.html" class="logout-btn">Logout</a>
                </div>
            </div>
        </form>
    </div>

    <nav class="main-nav">
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">☰</button>
        <ul id="navMenu">
            <li><a href="catalog.php">All Equipment</a></li>
            <li><a href="catalog.php?category=Cleaners">Cleaners</a></li>
            <li><a href="catalog.php?category=Concrete">Concrete</a></li>
            <li><a href="catalog.php?category=Cutting+%26+Sawing">Cutting & Sawing</a></li>
            <li><a href="catalog.php?category=Gardening">Gardening</a></li>
            <li><a href="catalog.php?category=Power+Tools">Power Tools</a></li>
            <li><a href="catalog.php?category=Ladders+%26+Steps">Ladders & Steps</a></li>
            <li><a href="catalog.php?category=Powered+Access">Powered Access</a></li>
            <li><a href="catalog.php?category=Diggers">Diggers</a></li>
            <li><a href="catalog.php?category=Rollers">Rollers</a></li>
        </ul>
    </nav>
</header>

<main class="catalog-page">
    <h2><?= htmlspecialchars($pageTitle) ?></h2>
    <p class="catalog-count"><?= count($items) ?> item(s) found</p>

    <?php if (empty($items)): ?>
        <p class="catalog-message">No equipment found. Try a different category or search.</p>
    <?php else: ?>
    <div class="catalog-grid">
        <?php foreach ($items as $item): ?>
        <div class="catalog-card">
            <img
                src="<?= htmlspecialchars($item['image_url']) ?>"
                alt="<?= htmlspecialchars($item['name']) ?>"
                onerror="this.src='assets/images/equipment/placeholder.jpg'"
            />
            <div class="catalog-info">
                <span class="catalog-category"><?= htmlspecialchars($item['category']) ?></span>
                <h3><?= htmlspecialchars($item['name']) ?></h3>
                <p class="catalog-brand"><?= htmlspecialchars($item['brand']) ?> — <?= htmlspecialchars($item['model']) ?></p>
                <p><?= htmlspecialchars($item['description']) ?></p>
                <div class="catalog-prices">
                    <span class="price-day">£<?= number_format($item['daily_rate'], 2) ?> / day</span>
                    <span class="price-week">£<?= number_format($item['weekly_rate'], 2) ?> / week</span>
                </div>
                <span class="availability <?= $item['availability'] === 'Available' ? 'available' : 'unavailable' ?>">
                    <?= htmlspecialchars($item['availability']) ?>
                </span>
                <?php if ($item['availability'] === 'Available'): ?>
                    <button class="catalog-hire-btn" 
                        data-id="<?= $item['id'] ?>" 
                        data-name="<?= htmlspecialchars($item['name']) ?>"
                        data-daily="<?= $item['daily_rate'] ?>">
                        Hire Now
                    </button>
                <?php else: ?>
                    <button class="catalog-hire-btn unavailable-btn" disabled>Not Available</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal-box">
        <h3>Book: <span id="modalItemName"></span></h3>
        <p id="modalPricePreview"></p>
        <div class="input-group">
            <label>Start Date</label>
            <input type="date" id="startDate" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="input-group">
            <label>End Date</label>
            <input type="date" id="endDate" min="<?= date('Y-m-d') ?>">
        </div>
        <p class="price-estimate" id="priceEstimate"></p>
        <div class="modal-buttons">
            <button class="auth-btn" id="confirmBooking">Confirm Booking</button>
            <button class="cancel-btn" id="cancelBooking">Cancel</button>
        </div>
        <div class="message" id="bookingMessage"></div>
    </div>
</div>

<script src="./script.js"></script>
<script>
// Catalog hire button → open modal
let selectedItemId = null;
let selectedDailyRate = 0;

document.querySelectorAll('.catalog-hire-btn:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => {
        selectedItemId   = btn.dataset.id;
        selectedDailyRate = parseFloat(btn.dataset.daily);
        document.getElementById('modalItemName').textContent = btn.dataset.name;
        document.getElementById('bookingModal').classList.add('show');
        document.getElementById('priceEstimate').textContent = '';
        document.getElementById('bookingMessage').className = 'message';
        document.getElementById('bookingMessage').textContent = '';
    });
});

// Price estimate preview
function updatePriceEstimate() {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    if (!start || !end) return;
    const days = Math.round((new Date(end) - new Date(start)) / 86400000);
    if (days <= 0) {
        document.getElementById('priceEstimate').textContent = 'End date must be after start date.';
        return;
    }
    const weeks    = Math.floor(days / 7);
    const remDays  = days % 7;
    const weekRate = selectedDailyRate * 4.5; // approx weekly
    const total    = (weeks * weekRate) + (remDays * selectedDailyRate);
    document.getElementById('priceEstimate').textContent = `Estimated total: £${total.toFixed(2)} for ${days} day(s)`;
}

document.getElementById('startDate').addEventListener('change', updatePriceEstimate);
document.getElementById('endDate').addEventListener('change', updatePriceEstimate);

// Cancel Button
document.getElementById('cancelBooking').addEventListener('click', () => {
    document.getElementById('bookingModal').classList.remove('show');
});

// Confirm booking
document.getElementById('confirmBooking').addEventListener('click', () => {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    const msg   = document.getElementById('bookingMessage');

    if (!start || !end) {
        msg.className = 'message error';
        msg.textContent = 'Please select both dates.';
        return;
    }

    const data = new FormData();
    data.append('equipment_id', selectedItemId);
    data.append('start_date',   start);
    data.append('end_date',     end);

    fetch('book_equipment.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) {
                setTimeout(() => {
                    document.getElementById('bookingModal').classList.remove('show');
                    location.reload();
                }, 2000);
            }
        })
        .catch(() => {
            msg.className = 'message error';
            msg.textContent = 'Could not connect. Please try again.';
        });
});
</script>
</body>
</html>