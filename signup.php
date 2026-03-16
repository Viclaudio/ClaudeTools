<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];

    $admin_domain = 'claudetools.com';
    $email_domain = substr(strrchr($email, "@"), 1);

    // Check correct email for role
    if ($role === 'Admin' && $email_domain !== $admin_domain) {
        echo json_encode(['success' => false, 'message' => 'Admin must use a @claudetools.com email.']);
        exit;
    }
    if ($role === 'User' && $email_domain === $admin_domain) {
        echo json_encode(['success' => false, 'message' => '@claudetools.com is reserved for admins only.']);
        exit;
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        exit;
    }

    // Save to database
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $full_name, $email, $hashed, $role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created! You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Try again.']);
    }
}
?>