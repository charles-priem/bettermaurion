<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/reservation_db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Vous devez être connecté pour réserver une salle.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$building = trim((string) ($payload['building'] ?? ''));
$date = trim((string) ($payload['date'] ?? ''));
$slots = $payload['slots'] ?? [];
$userId = (int) $_SESSION['user_id'];

if ($building === '' || $date === '') {
    echo json_encode(['error' => 'Veuillez sélectionner un bâtiment et une date.']);
    exit;
}

if (!in_array($building, ['IC1', 'IC2', 'ALG'], true)) {
    echo json_encode(['error' => 'Bâtiment invalide.']);
    exit;
}

if (!is_array($slots) || empty($slots)) {
    echo json_encode(['error' => 'Veuillez sélectionner au moins un créneau.']);
    exit;
}

try {
    ensure_room_reservations_table($conn);

    $normalizedSlots = [];
    foreach ($slots as $slot) {
        $roomName = trim((string) ($slot['room'] ?? ''));
        $start = trim((string) ($slot['start'] ?? ''));
        $end = trim((string) ($slot['end'] ?? ''));

        if ($roomName === '' || $start === '' || $end === '') {
            throw new RuntimeException('Un créneau sélectionné est incomplet.');
        }

        if (strpos($roomName, $building) !== 0) {
            throw new RuntimeException('Un créneau sélectionné ne correspond pas au bâtiment choisi.');
        }

        $startDateTime = new DateTimeImmutable($start);
        $endDateTime = new DateTimeImmutable($end);

        if ($startDateTime >= $endDateTime) {
            throw new RuntimeException('Un créneau sélectionné a une heure de fin invalide.');
        }

        if ($startDateTime->format('Y-m-d') !== $date || $endDateTime->format('Y-m-d') !== $date) {
            throw new RuntimeException('Les créneaux doivent correspondre à la date sélectionnée.');
        }

        $key = $roomName . '|' . $startDateTime->format('Y-m-d H:i:s') . '|' . $endDateTime->format('Y-m-d H:i:s');
        $normalizedSlots[$key] = [
            'room' => $roomName,
            'start' => $startDateTime->format('Y-m-d H:i:s'),
            'end' => $endDateTime->format('Y-m-d H:i:s'),
            'duration_minutes' => (int) (($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60),
        ];
    }

    $conflicts = [];
    foreach ($normalizedSlots as $slot) {
        if (has_room_reservation_overlap($conn, $building, $slot['room'], $slot['start'], $slot['end'])) {
            $conflicts[] = $slot['room'] . ' (' . substr($slot['start'], 11, 5) . ' - ' . substr($slot['end'], 11, 5) . ')';
        }
    }

    if (!empty($conflicts)) {
        echo json_encode([
            'error' => 'Certains créneaux ne sont plus disponibles.',
            'conflicts' => $conflicts,
        ]);
        exit;
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare('INSERT INTO room_reservations (user_id, building_code, room_name, start_time, end_time, duration_minutes, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Impossible de préparer la réservation : ' . $conn->error);
    }

    $status = 'confirmed';
    $inserted = 0;

    foreach ($normalizedSlots as $slot) {
        $roomName = $slot['room'];
        $start = $slot['start'];
        $end = $slot['end'];
        $durationMinutes = $slot['duration_minutes'];

        $stmt->bind_param('issssis', $userId, $building, $roomName, $start, $end, $durationMinutes, $status);
        if (!$stmt->execute()) {
            throw new RuntimeException('Impossible d’enregistrer la réservation : ' . $stmt->error);
        }

        $inserted++;
    }

    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $inserted . ' créneau' . ($inserted > 1 ? 'x' : '') . ' réservé' . ($inserted > 1 ? 's' : '') . ' avec succès.',
        'inserted_count' => $inserted,
    ]);
} catch (Throwable $throwable) {
    @$conn->rollback();

    http_response_code(400);
    echo json_encode(['error' => $throwable->getMessage()]);
}
