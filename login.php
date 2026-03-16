<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    $admin_domain = 'claudetools.com';
    $email_domain = substr(strrchr($email, "@"), 1);

    // Check correct email for role
    if ($role === 'Admin' && $email_domain !== $admin_domain) {
        echo json_encode(['success' => false, 'message' => 'Admin login requires a @claudetools.com email.']);
        exit;
    }
    if ($role === 'User' && $email_domain === $admin_domain) {
        echo json_encode(['success' => false, 'message' => 'Please select Admin to login with this email.']);
        exit;
    }

    // Find user in database
    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Save login session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        $redirect = $user['role'] === 'Admin' ? 'admin_dashboard.php' : 'client_dashboard.php';
        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect email, password or role.']);
    }
}
?>