<?php

if (!function_exists('ensure_room_reservations_table')) {
    function ensure_room_reservations_table(mysqli $conn): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS room_reservations (
  reservation_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  building_code varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  room_name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime NOT NULL,
  duration_minutes int(11) NOT NULL DEFAULT 120,
  status varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (reservation_id),
  UNIQUE KEY uniq_room_slot (building_code, room_name, start_time, end_time),
  KEY idx_building_room_time (building_code, room_name, start_time, end_time),
  KEY idx_user_id (user_id),
  KEY idx_start_time (start_time),
  CONSTRAINT room_reservations_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        if (!$conn->query($sql)) {
            throw new RuntimeException('Impossible de préparer la table des réservations : ' . $conn->error);
        }
    }
}

if (!function_exists('fetch_room_reservations_for_day')) {
    function fetch_room_reservations_for_day(mysqli $conn, string $building, string $date): array
    {
        ensure_room_reservations_table($conn);

        $dayStart = $date . ' 00:00:00';
        $dayEnd = (new DateTimeImmutable($date . ' 00:00:00'))->modify('+1 day')->format('Y-m-d H:i:s');

        $stmt = $conn->prepare('SELECT room_name, start_time, end_time FROM room_reservations WHERE building_code = ? AND start_time < ? AND end_time > ? ORDER BY room_name, start_time');
        if (!$stmt) {
            throw new RuntimeException('Impossible de préparer la requête des réservations : ' . $conn->error);
        }

        $stmt->bind_param('sss', $building, $dayEnd, $dayStart);
        $stmt->execute();
        $result = $stmt->get_result();

        $reservationsByRoom = [];
        while ($row = $result->fetch_assoc()) {
            $roomName = $row['room_name'];
            if (!isset($reservationsByRoom[$roomName])) {
                $reservationsByRoom[$roomName] = [];
            }

            $reservationsByRoom[$roomName][] = [
                'start' => $row['start_time'],
                'end' => $row['end_time'],
            ];
        }

        $stmt->close();

        return $reservationsByRoom;
    }
}

if (!function_exists('has_room_reservation_overlap')) {
    function has_room_reservation_overlap(mysqli $conn, string $building, string $roomName, string $startTime, string $endTime): bool
    {
        ensure_room_reservations_table($conn);

        $stmt = $conn->prepare('SELECT 1 FROM room_reservations WHERE building_code = ? AND room_name = ? AND start_time < ? AND end_time > ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Impossible de préparer la vérification de réservation : ' . $conn->error);
        }

        $stmt->bind_param('ssss', $building, $roomName, $endTime, $startTime);
        $stmt->execute();
        $stmt->store_result();
        $hasOverlap = $stmt->num_rows > 0;
        $stmt->close();

        return $hasOverlap;
    }
}
