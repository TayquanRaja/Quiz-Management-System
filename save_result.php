<?php
include "db.php";
session_start();

// Ensure headers expect clean JSON transactions
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized operation block. Access denied.']);
    exit;
}

// This variable will handle the exact logged-in user ID inside your prepare statement query
$studentId = intval($_SESSION['user_id']);

// Read the raw JSON payload arriving from the JavaScript fetch engine
$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Empty or invalid payload matrix received.']);
    exit;
}

$quizId = intval($data['quiz_id'] ?? 0);
$score = intval($data['score'] ?? 0);
$totalPoints = intval($data['total_points'] ?? 0);
$percentage = floatval($data['percentage'] ?? 0.00);
$status = 'Published'; // Matches the default column setting rule

if ($quizId <= 0 || $totalPoints <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters validation fallback flag raised.']);
    exit;
}

// Prepare secure MySQL insertion statement
$query = "INSERT INTO results (student_id, quiz_id, score, total_points, percentage, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("iiiiis", $studentId, $quizId, $score, $totalPoints, $percentage, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Result metrics saved flawlessly to MySQL database.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL Execution Error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'SQL Database Preparation Failed: ' . $conn->error]);
}
?>