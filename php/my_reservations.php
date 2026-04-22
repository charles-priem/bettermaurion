<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'delete') {
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    if ($reservation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM room_reservations WHERE reservation_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $reservation_id, $user_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => "Suppression impossible"]);
    }
    exit;
}

// Default: list
$stmt = $conn->prepare('SELECT reservation_id, building_code, room_name, start_time, end_time, status FROM room_reservations WHERE user_id = ? ORDER BY start_time DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = [];
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
echo json_encode(['reservations' => $reservations]);
