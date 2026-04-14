<?php
session_start();
require 'config.php';

// Block non-users and guests
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if ($_SESSION['role'] === 'Admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// Get user data (wallet + status)
$u = $conn->prepare("SELECT wallet, status FROM users WHERE id = ?");
$u->bind_param("i", $user_id);
$u->execute();
$user = $u->get_result()->fetch_assoc();

// Block suspended users
if ($user['status'] === 'Suspended') {
    session_destroy('message' => 'User Suspended Contact Admin');
    header('Location: login.html?suspended=1');
    exit;
}

$wallet = $user['wallet'];

// Fetch active rentals
$active = $conn->prepare("
    SELECT b.id, b.start_date, b.end_date, b.total_price, b.status,
           e.name, e.brand, e.model, e.image_url, e.id as equipment_id,
           e.daily_rate, e.weekly_rate
    FROM bookings b
    JOIN equipment e ON b.equipment_id = e.id
    WHERE b.user_id = ? AND b.status IN ('Pending', 'Confirmed')
    ORDER BY b.end_date ASC
");
$active->bind_param("i", $user_id);
$active->execute();
$active_rentals = $active->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch booking history
$history = $conn->prepare("
    SELECT b.id, b.start_date, b.end_date, b.total_price, b.status,
           e.name, e.brand, e.model, e.id as equipment_id,
           e.daily_rate, e.weekly_rate
    FROM bookings b
    JOIN equipment e ON b.equipment_id = e.id
    WHERE b.user_id = ? AND b.status IN ('Completed', 'Cancelled')
    ORDER BY b.created_at DESC
");
$history->bind_param("i", $user_id);
$history->execute();
$booking_history = $history->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch wallet transactions
$trans = $conn->prepare("
    SELECT * FROM wallet_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$trans->bind_param("i", $user_id);
$trans->execute();
$transactions = $trans->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch available equipment for search
$equipment = $conn->query("SELECT * FROM equipment WHERE availability = 'Available' ORDER BY category, name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ClaudTools</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="dashboard-body">

<header class="admin-header">
    <div class="admin-header-inner">
        <div class="admin-logo">
            <a href="./index.html"><img src="./Assets/logo Yellow.png" alt="logo" height="45px"></a>
            <span>My Dashboard</span>
        </div>
        <div class="admin-user">
            <span>👋 <?= htmlspecialchars($user_name) ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>

<div class="admin-container">

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <h3><?= count($active_rentals) ?></h3>
            <p>Active Rentals</p>
        </div>
        <div class="stat-card">
            <h3><?= count($booking_history) ?></h3>
            <p>Past Bookings</p>
        </div>
        <div class="stat-card wallet-stat">
            <h3>£<?= number_format($wallet, 2) ?></h3>
            <p>Wallet Balance</p>
        </div>
        <div class="stat-card">
            <h3><?= count($equipment) ?></h3>
            <p>Tools Available</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
        <button class="admin-tab active" data-tab="rentals">
            <i class="bi bi-clock-history"></i> Active Rentals
        </button>
        <button class="admin-tab" data-tab="browse">
            <i class="bi bi-search"></i> Browse & Hire
        </button>
        <button class="admin-tab" data-tab="history">
            <i class="bi bi-calendar-check"></i> Booking History
        </button>
        <button class="admin-tab" data-tab="wallet">
            <i class="bi bi-wallet2"></i> Wallet
        </button>
    </div>

    <!-- ACTIVE RENTALS TAB -->
    <div class="tab-content active" id="tab-rentals">
        <?php if (empty($active_rentals)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>You have no active rentals.</p>
                <button class="add-btn" onclick="switchTab('browse')">Browse Equipment</button>
            </div>
        <?php else: ?>
        <div class="rental-grid">
            <?php foreach ($active_rentals as $rental): ?>
            <?php
                $end    = new DateTime($rental['end_date']);
                $now    = new DateTime();
                $diff   = $now->diff($end);
                $overdue = $now > $end;
            ?>
            <div class="rental-card <?= $overdue ? 'overdue' : '' ?>">
                <img src="<?= htmlspecialchars($rental['image_url'] ?? 'assets/images/equipment/placeholder.jpg') ?>"
                     alt="<?= htmlspecialchars($rental['name']) ?>"
                     onerror="this.src='assets/images/equipment/placeholder.jpg'">
                <div class="rental-info">
                    <span class="catalog-category"><?= htmlspecialchars($rental['status']) ?></span>
                    <h3><?= htmlspecialchars($rental['name']) ?></h3>
                    <p class="catalog-brand"><?= htmlspecialchars($rental['brand']) ?> — <?= htmlspecialchars($rental['model']) ?></p>
                    <div class="rental-dates">
                        <span><i class="bi bi-calendar-event"></i> From: <?= date('d M Y', strtotime($rental['start_date'])) ?></span>
                        <span><i class="bi bi-calendar-x"></i> Due: <?= date('d M Y', strtotime($rental['end_date'])) ?></span>
                    </div>
                    <div class="countdown <?= $overdue ? 'countdown-overdue' : '' ?>"
                         data-end="<?= $rental['end_date'] ?>">
                        <?= $overdue ? '⚠️ Overdue by ' . $diff->days . ' day(s)' : $diff->days . 'd ' . $diff->h . 'h ' . $diff->i . 'm remaining' ?>
                    </div>
                    <div class="rental-price">Total: £<?= number_format($rental['total_price'], 2) ?></div>
                    <button class="return-btn"
                        data-id="<?= $rental['id'] ?>"
                        data-name="<?= htmlspecialchars($rental['name']) ?>">
                        <i class="bi bi-arrow-return-left"></i> Mark as Returned
                    </button>
                    <button class="extend-btn"
                        data-id="<?= $rental['id'] ?>"
                        data-name="<?= htmlspecialchars($rental['name']) ?>"
                        data-end="<?= $rental['end_date'] ?>"
                        data-daily="<?= $rental['daily_rate'] ?>"
                        data-weekly="<?= $rental['weekly_rate'] ?>"
                        data-equipment-id="<?= $rental['equipment_id'] ?>">
                        <i class="bi bi-calendar-plus"></i> Extend Booking
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- BROWSE & HIRE TAB -->
    <div class="tab-content" id="tab-browse">
        <div class="tab-toolbar">
            <input type="text" id="browseSearch" placeholder="Search equipment..." class="admin-search">
            <select id="browseCategory" class="admin-search" style="width:200px">
                <option value="">All Categories</option>
                <?php
                $cats = array_unique(array_column($equipment, 'category'));
                sort($cats);
                foreach ($cats as $cat):
                ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="catalog-grid" id="browseGrid">
            <?php foreach ($equipment as $item): ?>
            <div class="catalog-card browse-item"
                 data-name="<?= strtolower($item['name']) ?>"
                 data-category="<?= htmlspecialchars($item['category']) ?>">
                <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>"
                     alt="<?= htmlspecialchars($item['name']) ?>"
                     onerror="this.src='assets/images/equipment/placeholder.jpg'">
                <div class="catalog-info">
                    <span class="catalog-category"><?= htmlspecialchars($item['category']) ?></span>
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="catalog-brand"><?= htmlspecialchars($item['brand']) ?> — <?= htmlspecialchars($item['model']) ?></p>
                    <p><?= htmlspecialchars($item['description']) ?></p>
                    <div class="catalog-prices">
                        <span class="price-day">£<?= number_format($item['daily_rate'], 2) ?>/day</span>
                        <span class="price-week">£<?= number_format($item['weekly_rate'], 2) ?>/week</span>
                    </div>
                    <button class="catalog-hire-btn hire-now-btn"
                        data-id="<?= $item['id'] ?>"
                        data-name="<?= htmlspecialchars($item['name']) ?>"
                        data-daily="<?= $item['daily_rate'] ?>"
                        data-weekly="<?= $item['weekly_rate'] ?>">
                        <i class="bi bi-bag-plus"></i> Hire Now
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BOOKING HISTORY TAB -->
    <div class="tab-content" id="tab-history">
        <?php if (empty($booking_history)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>No booking history yet.</p>
            </div>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
             <thead>
                 <tr>
                        <th>Equipment</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Paid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($booking_history as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['name']) ?> <span style="color:#888;font-size:0.85rem">(<?= htmlspecialchars($b['brand']) ?>)</span></td>
                        <td><?= date('d M Y', strtotime($b['start_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($b['end_date'])) ?></td>
                        <td>£<?= number_format($b['total_price'], 2) ?></td>
                        <td>
                            <span class="badge <?= $b['status'] === 'Completed' ? 'badge-green' : 'badge-red' ?>">
                                <?= htmlspecialchars($b['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="rebook-btn"
                                data-equipment-id="<?= $b['equipment_id'] ?>"
                                data-name="<?= htmlspecialchars($b['name']) ?>"
                                data-daily="<?= $b['daily_rate'] ?>"
                                data-weekly="<?= $b['weekly_rate'] ?>">
                                <i class="bi bi-arrow-repeat"></i> Rebook
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- WALLET TAB -->
    <div class="tab-content" id="tab-wallet">
        <div class="wallet-layout">

            <div class="wallet-card">
                <div class="wallet-balance-display">
                    <i class="bi bi-wallet2"></i>
                    <div>
                        <p>Current Balance</p>
                        <h2 id="walletBalanceDisplay">£<?= number_format($wallet, 2) ?></h2>
                    </div>
                </div>
                <div class="topup-section">
                    <h4>Top Up Wallet</h4>
                    <div class="topup-presets">
                        <button class="preset-btn" data-amount="10">£10</button>
                        <button class="preset-btn" data-amount="20">£20</button>
                        <button class="preset-btn" data-amount="50">£50</button>
                        <button class="preset-btn" data-amount="100">£100</button>
                    </div>
                    <div class="input-group" style="margin-top:14px">
                        <label>Custom Amount (£)</label>
                        <input type="number" id="topupAmount" placeholder="Enter amount" min="1" step="0.01">
                    </div>
                    <button class="add-btn" id="confirmTopup" style="width:100%;margin-top:12px;justify-content:center">
                        <i class="bi bi-plus-circle"></i> Top Up
                    </button>
                    <div class="message" id="topupMessage"></div>
                </div>
            </div>

            <div class="wallet-transactions">
                <h4>Recent Transactions</h4>
                <?php if (empty($transactions)): ?>
                    <p style="color:#888;margin-top:12px">No transactions yet.</p>
                <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($transactions as $t): ?>
                    <div class="transaction-item">
                        <div class="transaction-icon <?= $t['type'] === 'TopUp' ? 'topup' : ($t['type'] === 'Refund' ? 'refund' : 'deduction') ?>">
                            <i class="bi bi-<?= $t['type'] === 'TopUp' ? 'plus' : ($t['type'] === 'Refund' ? 'arrow-counterclockwise' : 'dash') ?>"></i>
                        </div>
                        <div class="transaction-details">
                            <p><?= htmlspecialchars($t['description']) ?></p>
                            <span><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></span>
                        </div>
                        <div class="transaction-amount <?= $t['type'] === 'Deduction' ? 'negative' : 'positive' ?>">
                            <?= $t['type'] === 'Deduction' ? '-' : '+' ?>£<?= number_format($t['amount'], 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- HIRE MODAL -->
<div class="modal-overlay" id="hireModal">
    <div class="modal-box">
        <h3>Hire: <span id="hireItemName"></span></h3>
        <div class="input-group">
            <label>Start Date</label>
            <input type="date" id="hireStartDate" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="input-group">
            <label>End Date</label>
            <input type="date" id="hireEndDate" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="price-estimate" id="hirePriceEstimate"></div>
        <p style="font-size:0.88rem;color:#888;margin-bottom:12px">
            Wallet balance: <strong id="walletInModal">£<?= number_format($wallet, 2) ?></strong>
        </p>
        <div class="modal-buttons">
            <button class="auth-btn" id="confirmHire">Confirm & Pay</button>
            <button class="cancel-btn" id="cancelHire">Cancel</button>
        </div>
        <div class="message" id="hireMessage"></div>
    </div>
</div>

<!-- RETURN MODAL -->
<div class="modal-overlay" id="returnModal">
    <div class="modal-box modal-sm">
        <h3>Return Tool</h3>
        <p id="returnText">Are you sure you want to mark this as returned?</p>
        <div class="modal-buttons">
            <button class="auth-btn" id="confirmReturn">Yes, Mark Returned</button>
            <button class="cancel-btn" id="cancelReturn">Cancel</button>
        </div>
        <div class="message" id="returnMessage"></div>
    </div>
</div>

        <!-- REBOOK PAST BOOKING -->
        <div class="modal-overlay" id="rebookModal">
            <div class="modal-box">
             <h3>Rebook: <span id="rebookItemName"></span></h3>
                <p style="font-size:0.88rem;color:#888;margin-bottom:16px">Choose new hire dates for this item.</p>
                <div class="input-group">
                    <label>Start Date</label>
                    <input type="date" id="rebookStartDate" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="input-group">
                    <label>End Date</label>
                    <input type="date" id="rebookEndDate" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="price-estimate" id="rebookPriceEstimate"></div>
                <p style="font-size:0.88rem;color:#888;margin-bottom:12px">
                    Wallet balance: <strong id="rebookWalletDisplay">£<?= number_format($wallet, 2) ?></strong>
                </p>
                <div class="modal-buttons">
                    <button class="auth-btn" id="confirmRebook">Confirm & Pay</button>
                    <button class="cancel-btn" id="cancelRebook">Cancel</button>
                </div>
                <div class="message" id="rebookMessage"></div>
            </div>
        </div>

        <!-- EXTEND BOOKING -->
        <div class="modal-overlay" id="extendModal">
            <div class="modal-box">
                <h3>Extend: <span id="extendItemName"></span></h3>
                <p style="font-size:0.88rem;color:#888;margin-bottom:4px">
                    Current return date: <strong id="extendCurrentEnd"></strong>
                </p>
                <p style="font-size:0.88rem;color:#888;margin-bottom:16px">Choose a new return date to extend your hire.</p>
                <div class="input-group">
                    <label>New Return Date</label>
                    <input type="date" id="extendNewEnd">
                </div>
                <div class="price-estimate" id="extendPriceEstimate"></div>
                <p style="font-size:0.88rem;color:#888;margin-bottom:12px">
                    Wallet balance: <strong id="extendWalletDisplay">£<?= number_format($wallet, 2) ?></strong>
                </p>
                <div class="modal-buttons">
                    <button class="auth-btn" id="confirmExtend">Confirm & Pay</button>
                    <button class="cancel-btn" id="cancelExtend">Cancel</button>
                </div>
                <div class="message" id="extendMessage"></div>
            </div>
        </div>

<script>
// wallet balance available to all functions
const walletBalance = <?= floatval($wallet) ?>;

// ---- TABS ----
function switchTab(name) {
    document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelector(`[data-tab="${name}"]`).classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}
document.querySelectorAll('.admin-tab').forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
});

// ---- BROWSE SEARCH & FILTER ----
function filterBrowse() {
    const q   = document.getElementById('browseSearch').value.toLowerCase();
    const cat = document.getElementById('browseCategory').value;
    document.querySelectorAll('.browse-item').forEach(card => {
        const matchName = card.dataset.name.includes(q);
        const matchCat  = !cat || card.dataset.category === cat;
        card.style.display = matchName && matchCat ? '' : 'none';
    });
}
document.getElementById('browseSearch').addEventListener('input', filterBrowse);
document.getElementById('browseCategory').addEventListener('change', filterBrowse);

// ---- HIRE MODAL ----
let selectedId = null, selectedDaily = 0, selectedWeekly = 0;

document.querySelectorAll('.hire-now-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        selectedId     = btn.dataset.id;
        selectedDaily  = parseFloat(btn.dataset.daily);
        selectedWeekly = parseFloat(btn.dataset.weekly);
        document.getElementById('hireItemName').textContent    = btn.dataset.name;
        document.getElementById('hirePriceEstimate').textContent = '';
        document.getElementById('hireMessage').className        = 'message';
        document.getElementById('hireMessage').textContent      = '';
        document.getElementById('hireStartDate').value          = '';
        document.getElementById('hireEndDate').value            = '';
        document.getElementById('hireModal').classList.add('show');
    });
});

function calcPrice(daily, weekly, startVal, endVal) {
    if (!startVal || !endVal) return 0;
    const days = Math.round((new Date(endVal) - new Date(startVal)) / 86400000);
    if (days <= 0) return 0;
    const weeks = Math.floor(days / 7), rem = days % 7;
    return (weeks * weekly) + (rem * daily);
}

['hireStartDate','hireEndDate'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const total = calcPrice(selectedDaily, selectedWeekly,
            document.getElementById('hireStartDate').value,
            document.getElementById('hireEndDate').value);
        const days = Math.round((new Date(document.getElementById('hireEndDate').value) - new Date(document.getElementById('hireStartDate').value)) / 86400000);
        document.getElementById('hirePriceEstimate').textContent =
            total > 0 ? `Estimated: £${total.toFixed(2)} for ${days} day(s)` : '';
    });
});

document.getElementById('cancelHire').addEventListener('click', () => {
    document.getElementById('hireModal').classList.remove('show');
});

document.getElementById('confirmHire').addEventListener('click', () => {
    const start = document.getElementById('hireStartDate').value;
    const end   = document.getElementById('hireEndDate').value;
    const msg   = document.getElementById('hireMessage');
    const total = calcPrice(selectedDaily, selectedWeekly, start, end);

    if (!start || !end) { msg.className='message error'; msg.textContent='Please select both dates.'; return; }
    if (total <= 0)      { msg.className='message error'; msg.textContent='End date must be after start date.'; return; }
    if (total > walletBalance) {
        msg.className='message error';
        msg.textContent=`Insufficient balance. Need £${total.toFixed(2)} but have £${walletBalance.toFixed(2)}.`;
        return;
    }

    const data = new FormData();
    data.append('action',       'book_equipment');
    data.append('equipment_id', selectedId);
    data.append('start_date',   start);
    data.append('end_date',     end);

    fetch('user_actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) setTimeout(() => location.reload(), 1800);
        })
        .catch(() => { msg.className='message error'; msg.textContent='Could not connect. Try again.'; });
});

// ---- RETURN MODAL ----
let returnBookingId = null;

document.querySelectorAll('.return-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        returnBookingId = btn.dataset.id;
        document.getElementById('returnText').textContent = `Mark "${btn.dataset.name}" as returned?`;
        document.getElementById('returnModal').classList.add('show');
    });
});
document.getElementById('cancelReturn').addEventListener('click', () => {
    document.getElementById('returnModal').classList.remove('show');
});
document.getElementById('confirmReturn').addEventListener('click', () => {
    const msg  = document.getElementById('returnMessage');
    const data = new FormData();
    data.append('action',     'return_equipment');
    data.append('booking_id', returnBookingId);

    fetch('user_actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) setTimeout(() => location.reload(), 1500);
        });
});

// ---- WALLET TOP UP ----
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('topupAmount').value = btn.dataset.amount;
    });
});
document.getElementById('confirmTopup').addEventListener('click', () => {
    const amount = parseFloat(document.getElementById('topupAmount').value);
    const msg    = document.getElementById('topupMessage');
    if (!amount || amount <= 0) { msg.className='message error'; msg.textContent='Please enter a valid amount.'; return; }

    const data = new FormData();
    data.append('action', 'topup_wallet');
    data.append('amount', amount);

    fetch('user_actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) setTimeout(() => location.reload(), 1500);
        });
});

// ---- REBOOK ----
let rebookEquipmentId = null, rebookDaily = 0, rebookWeekly = 0;

document.querySelectorAll('.rebook-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        rebookEquipmentId = btn.dataset.equipmentId;
        rebookDaily       = parseFloat(btn.dataset.daily);
        rebookWeekly      = parseFloat(btn.dataset.weekly);
        document.getElementById('rebookItemName').textContent     = btn.dataset.name;
        document.getElementById('rebookPriceEstimate').textContent = '';
        document.getElementById('rebookMessage').className         = 'message';
        document.getElementById('rebookMessage').textContent       = '';
        document.getElementById('rebookStartDate').value           = '';
        document.getElementById('rebookEndDate').value             = '';
        document.getElementById('rebookModal').classList.add('show');
    });
});

['rebookStartDate','rebookEndDate'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const total = calcPrice(rebookDaily, rebookWeekly,
            document.getElementById('rebookStartDate').value,
            document.getElementById('rebookEndDate').value);
        const days = Math.round((new Date(document.getElementById('rebookEndDate').value) - new Date(document.getElementById('rebookStartDate').value)) / 86400000);
        document.getElementById('rebookPriceEstimate').textContent =
            total > 0 ? `Estimated: £${total.toFixed(2)} for ${days} day(s)` : '';
    });
});

document.getElementById('cancelRebook').addEventListener('click', () => {
    document.getElementById('rebookModal').classList.remove('show');
});

document.getElementById('confirmRebook').addEventListener('click', () => {
    const start = document.getElementById('rebookStartDate').value;
    const end   = document.getElementById('rebookEndDate').value;
    const msg   = document.getElementById('rebookMessage');
    const total = calcPrice(rebookDaily, rebookWeekly, start, end);

    if (!start || !end) { msg.className='message error'; msg.textContent='Please select both dates.'; return; }
    if (total <= 0)      { msg.className='message error'; msg.textContent='End date must be after start date.'; return; }
    if (total > walletBalance) {
        msg.className='message error';
        msg.textContent=`Insufficient balance. Need £${total.toFixed(2)} but have £${walletBalance.toFixed(2)}.`;
        return;
    }

    const data = new FormData();
    data.append('action',       'book_equipment');
    data.append('equipment_id', rebookEquipmentId);
    data.append('start_date',   start);
    data.append('end_date',     end);

    fetch('user_actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) setTimeout(() => location.reload(), 1800);
        })
        .catch(() => { msg.className='message error'; msg.textContent='Could not connect. Try again.'; });
});

// ---- EXTEND BOOKING ----
let extendBookingId = null, extendCurrentEnd = '', extendDaily = 0, extendWeekly = 0;

document.querySelectorAll('.extend-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        extendBookingId  = btn.dataset.id;
        extendCurrentEnd = btn.dataset.end;
        extendDaily      = parseFloat(btn.dataset.daily);
        extendWeekly     = parseFloat(btn.dataset.weekly);

        const minDate = new Date(extendCurrentEnd);
        minDate.setDate(minDate.getDate() + 1);
        const minStr = minDate.toISOString().split('T')[0];

        document.getElementById('extendItemName').textContent     = btn.dataset.name;
        document.getElementById('extendCurrentEnd').textContent   = new Date(extendCurrentEnd).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
        document.getElementById('extendNewEnd').min               = minStr;
        document.getElementById('extendNewEnd').value             = '';
        document.getElementById('extendPriceEstimate').textContent = '';
        document.getElementById('extendMessage').className         = 'message';
        document.getElementById('extendMessage').textContent       = '';
        document.getElementById('extendModal').classList.add('show');
    });
});

document.getElementById('extendNewEnd').addEventListener('change', function() {
    const newEnd = this.value;
    if (!newEnd) return;
    const extraDays = Math.round((new Date(newEnd) - new Date(extendCurrentEnd)) / 86400000);
    if (extraDays <= 0) {
        document.getElementById('extendPriceEstimate').textContent = 'New date must be after current return date.';
        return;
    }
    const weeks = Math.floor(extraDays / 7), rem = extraDays % 7;
    const cost  = (weeks * extendWeekly) + (rem * extendDaily);
    document.getElementById('extendPriceEstimate').textContent =
        `Extension cost: £${cost.toFixed(2)} for ${extraDays} extra day(s)`;
});

document.getElementById('cancelExtend').addEventListener('click', () => {
    document.getElementById('extendModal').classList.remove('show');
});

document.getElementById('confirmExtend').addEventListener('click', () => {
    const newEnd = document.getElementById('extendNewEnd').value;
    const msg    = document.getElementById('extendMessage');

    if (!newEnd) { msg.className='message error'; msg.textContent='Please select a new return date.'; return; }

    const extraDays = Math.round((new Date(newEnd) - new Date(extendCurrentEnd)) / 86400000);
    if (extraDays <= 0) { msg.className='message error'; msg.textContent='New date must be after the current return date.'; return; }

    const weeks = Math.floor(extraDays / 7), rem = extraDays % 7;
    const cost  = (weeks * extendWeekly) + (rem * extendDaily);

    if (cost > walletBalance) {
        msg.className='message error';
        msg.textContent=`Insufficient balance. Extension costs £${cost.toFixed(2)} but you have £${walletBalance.toFixed(2)}.`;
        return;
    }

    const data = new FormData();
    data.append('action',     'extend_booking');
    data.append('booking_id', extendBookingId);
    data.append('new_end',    newEnd);

    fetch('user_actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            msg.textContent = res.message;
            if (res.success) setTimeout(() => location.reload(), 1800);
        })
        .catch(() => { msg.className='message error'; msg.textContent='Could not connect. Try again.'; });
});

// ---- COUNTDOWN TIMERS ----
function updateCountdowns() {
    document.querySelectorAll('.countdown[data-end]').forEach(el => {
        const end  = new Date(el.dataset.end);
        const now  = new Date();
        const diff = end - now;
        if (diff <= 0) {
            el.textContent = '⚠️ Overdue!';
            el.classList.add('countdown-overdue');
            return;
        }
        const days  = Math.floor(diff / 86400000);
        const hours = Math.floor((diff % 86400000) / 3600000);
        const mins  = Math.floor((diff % 3600000) / 60000);
        el.textContent = `${days}d ${hours}h ${mins}m remaining`;
    });
}
updateCountdowns();
setInterval(updateCountdowns, 60000);
</script>
</body>
</html>