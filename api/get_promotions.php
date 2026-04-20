<?php
session_start();
require_once '../php/config.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les promotions et plannings depuis la base de données
$result = $conn->query('SELECT DISTINCT p.name, p.label, pl.planning_name, pl.planning_label, COUNT(e.event_id) as event_count FROM plannings pl LEFT JOIN events e ON pl.planning_id = e.planning_id LEFT JOIN promotions p ON pl.promotion_id = p.promotion_id GROUP BY pl.planning_id ORDER BY p.name, pl.planning_name');

$tree = [];
while ($row = $result->fetch_assoc()) {
    $promotion = $row['name'] ?? 'Unknown';
    $planning_name = $row['planning_name'] ?? '';
    $planning_label = $row['planning_label'] ?? '';
    $event_count = (int)($row['event_count'] ?? 0);
    
    if (!isset($tree[$promotion])) {
        $tree[$promotion] = [
            'label' => $row['label'] ?? $promotion,
            'children' => []
        ];
    }
    
    $tree[$promotion]['children'][$planning_name] = [
        'label' => $planning_label,
        'eventCount' => $event_count
    ];
}

echo json_encode($tree);
