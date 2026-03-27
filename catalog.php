<?php
session_start();
require 'config.php';

// Get category from URL e.g. catalog.php?category=Cleaners
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