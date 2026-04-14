<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

switch ($action) {

    // BOOK EQUIPMENT
    case 'book_equipment':
        $equipment_id = intval($_POST['equipment_id']);
        $start_date   = $_POST['start_date'];
        $end_date     = $_POST['end_date'];

        $days = (strtotime($end_date) - strtotime($start_date)) / 86400;
        if ($days <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid dates.']);
            exit;
        }

        // Get equipment rates
        $eq = $conn->prepare("SELECT daily_rate, weekly_rate, availability FROM equipment WHERE id = ?");
        $eq->bind_param("i", $equipment_id);
        $eq->execute();
        $eq_data = $eq->get_result()->fetch_assoc();

        if (!$eq_data || $eq_data['availability'] !== 'Available') {
            echo json_encode(['success' => false, 'message' => 'Equipment is not available.']);
            exit;
        }

        // Calculate total
        $weeks    = floor($days / 7);
        $rem_days = $days % 7;
        $total    = ($weeks * $eq_data['weekly_rate']) + ($rem_days * $eq_data['daily_rate']);

        // Check wallet balance
        $w = $conn->prepare("SELECT wallet FROM users WHERE id = ?");
        $w->bind_param("i", $user_id);
        $w->execute();
        $wallet = $w->get_result()->fetch_assoc()['wallet'];

        if ($wallet < $total) {
            echo json_encode(['success' => false, 'message' => "Insufficient balance. Need £{$total}, have £{$wallet}."]);
            exit;
        }

        // Insert booking
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, equipment_id, start_date, end_date, total_price, status) VALUES (?, ?, ?, ?, ?, 'Confirmed')");
        $stmt->bind_param("iissd", $user_id, $equipment_id, $start_date, $end_date, $total);

        if ($stmt->execute()) {
            $booking_id = $conn->insert_id;

            // Deduct from wallet
            $deduct = $conn->prepare("UPDATE users SET wallet = wallet - ? WHERE id = ?");
            $deduct->bind_param("di", $total, $user_id);
            $deduct->execute();

            // Log transaction
            $desc = "Hired equipment (Booking #$booking_id)";
            $t = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'Deduction', ?, ?)");
            $t->bind_param("ids", $user_id, $total, $desc);
            $t->execute();

            // Mark equipment unavailable
            $markHired = $conn->prepare("UPDATE equipment SET availability = 'Hired Out' WHERE id = ?");
            $markHired->bind_param("i", $equipment_id);
            $markHired->execute();

            echo json_encode(['success' => true, 'message' => "Booking confirmed! £{$total} deducted from your wallet."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking failed. Try again.']);
        }
        break;

    // RETURN EQUIPMENT
    case 'return_equipment':
        $booking_id = intval($_POST['booking_id']);

        // Verify booking belongs to this user
        $b = $conn->prepare("SELECT equipment_id, status FROM bookings WHERE id = ? AND user_id = ?");
        $b->bind_param("ii", $booking_id, $user_id);
        $b->execute();
        $booking = $b->get_result()->fetch_assoc();

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        // Mark booking completed
        $complete = $conn->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ?");
        $complete->bind_param("i", $booking_id);
        $complete->execute();

        // Mark equipment available again
        $avail = $conn->prepare("UPDATE equipment SET availability = 'Available' WHERE id = ?");
        $avail->bind_param("i", $booking['equipment_id']);
        $avail->execute();

        echo json_encode(['success' => true, 'message' => 'Tool marked as returned. Thank you!']);
        break;

    // ---- TOP UP WALLET ----
    case 'topup_wallet':
        $amount = floatval($_POST['amount']);

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid amount.']);
            exit;
        }
        if ($amount > 500) {
            echo json_encode(['success' => false, 'message' => 'Maximum top up is £500 at a time.']);
            exit;
        }

        // Add to wallet
        $topup = $conn->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?");
        $topup->bind_param("di", $amount, $user_id);
        $topup->execute();

        // Log transaction
        $desc = "Wallet top up";
        $t = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'TopUp', ?, ?)");
        $t->bind_param("ids", $user_id, $amount, $desc);
        $t->execute();

        echo json_encode(['success' => true, 'message' => "£{$amount} added to your wallet!"]);
        break;

    // EXTEND BOOKING
        case 'extend_booking':
            $booking_id = intval($_POST['booking_id']);
            $new_end    = $_POST['new_end'];

            // Verify booking belongs to this user and is active
            $b = $conn->prepare("
                SELECT b.end_date, b.equipment_id, e.daily_rate, e.weekly_rate
                FROM bookings b
                JOIN equipment e ON b.equipment_id = e.id
                WHERE b.id = ? AND b.user_id = ? AND b.status IN ('Pending', 'Confirmed')
            ");
            $b->bind_param("ii", $booking_id, $user_id);
            $b->execute();
            $booking = $b->get_result()->fetch_assoc();

            if (!$booking) {
                echo json_encode(['success' => false, 'message' => 'Active booking not found.']);
                exit;
            }

            // Calculate extra days and cost
            $extra_days = (strtotime($new_end) - strtotime($booking['end_date'])) / 86400;

            if ($extra_days <= 0) {
                echo json_encode(['success' => false, 'message' => 'New return date must be after the current one.']);
                exit;
            }

            $weeks    = floor($extra_days / 7);
            $rem_days = $extra_days % 7;
            $cost     = ($weeks * $booking['weekly_rate']) + ($rem_days * $booking['daily_rate']);

            // Check wallet
            $w = $conn->prepare("SELECT wallet FROM users WHERE id = ?");
            $w->bind_param("i", $user_id);
            $w->execute();
            $wallet = $w->get_result()->fetch_assoc()['wallet'];

            if ($wallet < $cost) {
                echo json_encode(['success' => false, 'message' => "Insufficient balance. Extension costs £{$cost}, you have £{$wallet}."]);
                exit;
            }

            // Update booking end date and total price
            $update = $conn->prepare("UPDATE bookings SET end_date = ?, total_price = total_price + ? WHERE id = ?");
            $update->bind_param("sdi", $new_end, $cost, $booking_id);

            if ($update->execute()) {
            // Deduct from wallet
                $deduct = $conn->prepare("UPDATE users SET wallet = wallet - ? WHERE id = ?");
                $deduct->bind_param("di", $cost, $user_id);
                $deduct->execute();

                // Log transaction
                $desc = "Booking #$booking_id extension";
                $t = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'Deduction', ?, ?)");
                $t->bind_param("ids", $user_id, $cost, $desc);
                $t->execute();

                echo json_encode(['success' => true, 'message' => "Booking extended to " . date('d M Y', strtotime($new_end)) . ". £{$cost} deducted."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Extension failed. Please try again.']);
            }
            break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
?>