<?php
session_start();
require 'config.php';

// Block non-admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // TOGGLE EQUIPMENT AVAILABILITY
    case 'toggle_availability':
        $id     = intval($_POST['id']);
        $status = $_POST['current_status'];
        $new    = $status === 'Available' ? 'Hired Out' : 'Available';
        $stmt   = $conn->prepare("UPDATE equipment SET availability = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $id);
        echo json_encode(['success' => $stmt->execute(), 'new_status' => $new]);
        break;

    // ADD EQUIPMENT
    case 'add_equipment':
        $name        = trim($_POST['name']);
        $brand       = trim($_POST['brand']);
        $model       = trim($_POST['model']);
        $category    = trim($_POST['category']);
        $description = trim($_POST['description']);
        $daily       = floatval($_POST['daily_rate']);
        $weekly      = floatval($_POST['weekly_rate']);
        $image       = trim($_POST['image_url']);

        $stmt = $conn->prepare("INSERT INTO equipment (name, brand, model, category, description, daily_rate, weekly_rate, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdds", $name, $brand, $model, $category, $description, $daily, $weekly, $image);
        echo json_encode(['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'Equipment added!' : 'Failed to add.']);
        break;

    // EDIT EQUIPMENT
    case 'edit_equipment':
        $id          = intval($_POST['id']);
        $name        = trim($_POST['name']);
        $brand       = trim($_POST['brand']);
        $model       = trim($_POST['model']);
        $category    = trim($_POST['category']);
        $description = trim($_POST['description']);
        $daily       = floatval($_POST['daily_rate']);
        $weekly      = floatval($_POST['weekly_rate']);
        $image       = trim($_POST['image_url']);

        $stmt = $conn->prepare("UPDATE equipment SET name=?, brand=?, model=?, category=?, description=?, daily_rate=?, weekly_rate=?, image_url=? WHERE id=?");
        $stmt->bind_param("sssssddsi", $name, $brand, $model, $category, $description, $daily, $weekly, $image, $id);
        echo json_encode(['success' => $stmt->execute(), 'message' => 'Equipment updated!']);
        break;

    // DELETE EQUIPMENT
    case 'delete_equipment':
        $id   = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute(), 'message' => 'Equipment deleted.']);
        break;

    // SUSPEND / UNSUSPEND USER
    case 'toggle_suspend':
        $id     = intval($_POST['id']);
        $status = $_POST['current_status'];
        $new    = $status === 'Active' ? 'Suspended' : 'Active';
        $stmt   = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $id);
        echo json_encode(['success' => $stmt->execute(), 'new_status' => $new]);
        break;

    // EDIT USER
    case 'edit_user':
        $id    = intval($_POST['id']);
        $name  = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role  = trim($_POST['role']);

        // Check email not taken by another user
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
        echo json_encode(['success' => $stmt->execute(), 'message' => 'User updated!']);
        break;

    // DELETE USER
    case 'delete_user':
        $id   = intval($_POST['id']);
        // Delete bookings first (foreign key)
        $conn->prepare("DELETE FROM bookings WHERE user_id = ?")->bind_param("i", $id);
        $conn->execute();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute(), 'message' => 'User deleted.']);
        break;

    // GET USER BOOKINGS
    case 'get_bookings':
        $id   = intval($_POST['user_id']);
        $stmt = $conn->prepare("
            SELECT b.id, b.start_date, b.end_date, b.total_price, b.status,
                   e.name, e.brand, e.model
            FROM bookings b
            JOIN equipment e ON b.equipment_id = e.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'bookings' => $bookings]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
?>