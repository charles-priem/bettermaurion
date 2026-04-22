<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/reservation_db.php';

$building = $_POST['building'] ?? '';
$date = $_POST['date'] ?? '';

$roomsByBuilding = [
    'IC1' => ['IC1_017', 'IC1_102', 'IC1_106', 'IC1_109', 'IC1_112', 'IC1_114', 'IC1_117', 'IC1_122', 'IC1_130', 'IC1_217', 'IC1_220', 'IC1_222', 'IC1_229', 'IC1_320_PROJET', 'IC1_409', 'IC1_412', 'IC1_419', 'IC1_427'],
    'IC2' => ['IC2 A111', 'IC2 A304', 'IC2 A408', 'IC2 A411', 'IC2 A412', 'IC2 A811', 'IC2 A815_2TAB- VP', 'IC2 A816 - VP', 'IC2 A906_2TAB', 'IC2 A913 (H)', 'IC2 A919 - VP', 'IC2 B304', 'IC2 B305 (H)', 'IC2 B501', 'IC2 B502', 'IC2 B503', 'IC2 B505 (H)_2TAB', 'IC2 B802 (H)', 'IC2 B803 (H)', 'IC2 B804 (H)', 'IC2 C304 - Amphi JND (H)', 'IC2 C350', 'IC2 C401 - Amphi Prépa OZANAM (H)', 'IC2 C403 - Salle Prépa OZANAM', 'IC2 C404 - Salle Prépa OZANAM', 'IC2 C405 - Amphi Prépa OZANAM', 'IC2 C601 - VP', 'IC2 C854', 'IC2 C953', 'IC2 C955 - VP', 'IC2 C956 (H)'],
    'ALG' => ['ALG Amphi 002 H', 'ALG Amphi 003', 'ALG Salle 201-202', 'ALG Salle 204', 'ALG Salle 205 H', 'ALG Salle 207', 'ALG Salle 208 H', 'ALG Salle 209 H', 'ALG Salle 211', 'ALG Salle 217', 'ALG Salle 220', 'ALG Salle 221 H', 'ALG Salle 222 H', 'ALG Salle 223', 'ALG Salle 224', 'ALG Salle 225'],
];

if (empty($building) || empty($date)) {
    echo json_encode(['error' => 'Veuillez sélectionner un bâtiment et une date.']);
    exit;
}

if (!isset($roomsByBuilding[$building])) {
    echo json_encode(['error' => 'Bâtiment invalide.']);
    exit;
}

function parseSlotDateTime(string $value): DateTimeImmutable
{
    return new DateTimeImmutable($value);
}

function mergeIntervals(array $intervals): array
{
    if (empty($intervals)) {
        return [];
    }

    usort($intervals, static function (array $left, array $right): int {
        $leftStart = $left['start']->getTimestamp();
        $rightStart = $right['start']->getTimestamp();

        if ($leftStart === $rightStart) {
            return $left['end']->getTimestamp() <=> $right['end']->getTimestamp();
        }

        return $leftStart <=> $rightStart;
    });

    $merged = [];

    foreach ($intervals as $interval) {
        if (empty($merged)) {
            $merged[] = $interval;
            continue;
        }

        $lastIndex = count($merged) - 1;
        $lastInterval = $merged[$lastIndex];

        if ($interval['start'] <= $lastInterval['end']) {
            if ($interval['end'] > $lastInterval['end']) {
                $merged[$lastIndex]['end'] = $interval['end'];
            }
            continue;
        }

        $merged[] = $interval;
    }

    return $merged;
}

function subtractBusyIntervals(DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd, array $busyIntervals): array
{
    $freeSegments = [];
    $cursor = $windowStart;

    foreach ($busyIntervals as $busyInterval) {
        if ($busyInterval['end'] <= $windowStart || $busyInterval['start'] >= $windowEnd) {
            continue;
        }

        $busyStart = $busyInterval['start'] < $windowStart ? $windowStart : $busyInterval['start'];
        $busyEnd = $busyInterval['end'] > $windowEnd ? $windowEnd : $busyInterval['end'];

        if ($busyStart > $cursor) {
            $freeSegments[] = ['start' => $cursor, 'end' => $busyStart];
        }

        if ($busyEnd > $cursor) {
            $cursor = $busyEnd;
        }

        if ($cursor >= $windowEnd) {
            break;
        }
    }

    if ($cursor < $windowEnd) {
        $freeSegments[] = ['start' => $cursor, 'end' => $windowEnd];
    }

    return $freeSegments;
}

function generateSlotsFromFreeSegment(DateTimeImmutable $start, DateTimeImmutable $end, int $durationMinutes = 120): array
{
    $slots = [];
    $cursor = $start;
    $slotInterval = new DateInterval('PT' . $durationMinutes . 'M');

    while ($cursor->add($slotInterval) <= $end) {
        $slotEnd = $cursor->add($slotInterval);
        $slots[] = [
            'start' => $cursor->format('Y-m-d H:i:s'),
            'end' => $slotEnd->format('Y-m-d H:i:s'),
            'duration' => (int) ($durationMinutes / 60),
        ];
        $cursor = $slotEnd;
    }

    return $slots;
}

function buildAllowedWindows(string $date): array
{
    return [
        [
            'start' => parseSlotDateTime($date . ' 08:00:00'),
            'end' => parseSlotDateTime($date . ' 12:00:00'),
        ],
        [
            'start' => parseSlotDateTime($date . ' 13:00:00'),
            'end' => parseSlotDateTime($date . ' 18:00:00'),
        ],
        [
            'start' => parseSlotDateTime($date . ' 18:00:00'),
            'end' => parseSlotDateTime($date . ' 20:00:00'),
        ],
    ];
}

try {
    ensure_room_reservations_table($conn);

    $planningFiles = array_filter(scandir(__DIR__ . '/../plannings'), static function (string $file): bool {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    });

    $eventsByRoom = [];

    foreach ($planningFiles as $file) {
        $content = file_get_contents(__DIR__ . '/../plannings/' . $file);
        if ($content === false) {
            continue;
        }

        $planningData = json_decode($content, true);
        if (!is_array($planningData)) {
            continue;
        }

        foreach ($planningData as $event) {
            if (!isset($event['start'], $event['end'], $event['location'])) {
                continue;
            }

            $eventDate = date('Y-m-d', strtotime($event['start']));
            if ($eventDate !== $date) {
                continue;
            }

            if (strpos($event['location'], $building) !== 0) {
                continue;
            }

            $roomName = $event['location'];
            if (!isset($eventsByRoom[$roomName])) {
                $eventsByRoom[$roomName] = [];
            }

            $eventsByRoom[$roomName][] = [
                'start' => parseSlotDateTime($event['start']),
                'end' => parseSlotDateTime($event['end']),
            ];
        }
    }

    $reservedByRoom = fetch_room_reservations_for_day($conn, $building, $date);
    foreach ($reservedByRoom as $roomName => $reservations) {
        if (!isset($eventsByRoom[$roomName])) {
            $eventsByRoom[$roomName] = [];
        }

        foreach ($reservations as $reservation) {
            $eventsByRoom[$roomName][] = [
                'start' => parseSlotDateTime($reservation['start']),
                'end' => parseSlotDateTime($reservation['end']),
            ];
        }
    }

    $availableSlotsByRoom = [];
    $allowedWindows = buildAllowedWindows($date);

    foreach ($roomsByBuilding[$building] as $roomName) {
        $busyIntervals = mergeIntervals($eventsByRoom[$roomName] ?? []);
        $roomSlots = [];

        foreach ($allowedWindows as $window) {
            $freeSegments = subtractBusyIntervals($window['start'], $window['end'], $busyIntervals);

            foreach ($freeSegments as $segment) {
                $roomSlots = array_merge($roomSlots, generateSlotsFromFreeSegment($segment['start'], $segment['end']));
            }
        }

        if (!empty($roomSlots)) {
            $availableSlotsByRoom[$roomName] = $roomSlots;
        }
    }

    ksort($availableSlotsByRoom);

    echo json_encode(['available_slots_by_room' => $availableSlotsByRoom]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
}
