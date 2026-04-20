<?php
session_start();
require_once '../php/config.php';
date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json; charset=utf-8');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$planning_name = trim($_GET['planning'] ?? '');
$week = isset($_GET['week']) ? (int)$_GET['week'] : 0;

if (empty($planning_name)) {
    echo json_encode(['error' => 'Planning requis']);
    exit;
}

// Calculer les dates de la semaine
$monday = new DateTime('monday this week');
$monday->modify(($week >= 0 ? '+' : '') . $week . ' weeks');
$monday->setTime(0, 0, 0);

$sunday = clone $monday;
$sunday->modify('+6 days');
$sunday->setTime(23, 59, 59);

// Récupérer les événements depuis la base de données
$stmt = $conn->prepare('
    SELECT e.* FROM events e
    JOIN plannings pl ON e.planning_id = pl.planning_id
    WHERE pl.planning_name = ? AND e.start_time BETWEEN ? AND ?
    ORDER BY e.start_time
');

$start_str = $monday->format('Y-m-d H:i:s');
$end_str = $sunday->format('Y-m-d H:i:s');

$stmt->bind_param('sss', $planning_name, $start_str, $end_str);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['event_id'],
        'title' => $row['event_title'],
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'className' => $row['type_event'],
        'salle' => $row['salle'],
        'matiere' => $row['matiere'],
        'prof' => $row['prof'],
        'allDay' => (bool)$row['all_day']
    ];
}

echo json_encode(['events' => $events]);
$stmt->close();
