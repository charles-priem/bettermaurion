<?php
/**
 * Script de test de synchronisation des plannings
 * Vérifie que tous les fichiers JSON sont correctement chargés et intégrés
 */

date_default_timezone_set('Europe/Paris');

$dataPath = __DIR__ . '/plannings/';
$errors = [];
$stats = [
    'total_files' => 0,
    'valid_files' => 0,
    'total_events' => 0,
    'files' => [],
];

if (!is_dir($dataPath)) {
    die("Erreur: Le répertoire des plannings n'existe pas: $dataPath\n");
}

// Lecture de tous les fichiers JSON
$files = new DirectoryIterator($dataPath);
foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
        continue;
    }
    
    $stats['total_files']++;
    $filename = $file->getBasename('.json');
    $filePath = $file->getPathname();
    
    // Lecture et décodage du fichier
    $raw = @file_get_contents($filePath);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    
    if (!is_array($decoded)) {
        $errors[] = "❌ $filename: JSON invalide";
        continue;
    }
    
    // Vérification de la structure
    $events = is_array($decoded['events'] ?? null) ? $decoded['events'] : (is_array($decoded) && !isset($decoded['events']) ? $decoded : []);
    
    if (!is_array($events)) {
        $errors[] = "⚠️  $filename: Aucun tableau 'events' trouvé";
        continue;
    }
    
    $eventCount = count($events);
    $stats['valid_files']++;
    $stats['total_events'] += $eventCount;
    
    // Validation des événements
    $validEvents = 0;
    $invalidEvents = [];
    
    foreach ($events as $idx => $ev) {
        if (!is_array($ev)) {
            $invalidEvents[] = "Événement $idx: pas un tableau";
            continue;
        }
        
        $hasStart = isset($ev['start']) && is_string($ev['start']) && $ev['start'] !== '';
        $hasEnd = isset($ev['end']) && is_string($ev['end']) && $ev['end'] !== '';
        $hasTitle = isset($ev['title']) && is_string($ev['title']) && $ev['title'] !== '';
        
        if ($hasStart && $hasEnd && $hasTitle) {
            $validEvents++;
        } else {
            $missingFields = [];
            if (!$hasStart) $missingFields[] = 'start';
            if (!$hasEnd) $missingFields[] = 'end';
            if (!$hasTitle) $missingFields[] = 'title';
            $invalidEvents[] = "Événement $idx: manquent " . implode(', ', $missingFields);
        }
    }
    
    $status = $validEvents === $eventCount ? '✅' : '⚠️ ';
    $stats['files'][$filename] = [
        'status' => $status,
        'total' => $eventCount,
        'valid' => $validEvents,
        'invalid' => count($invalidEvents),
    ];
    
    if (count($invalidEvents) > 0 && count($invalidEvents) <= 5) {
        foreach ($invalidEvents as $invalidErr) {
            $errors[] = "$status $filename: $invalidErr";
        }
    } elseif (count($invalidEvents) > 5) {
        $errors[] = "$status $filename: " . count($invalidEvents) . " événements invalides";
    }
}

// Affichage du rapport
echo "===============================================\n";
echo "Rapport de synchronisation des emplois du temps\n";
echo "===============================================\n\n";

echo "📊 Résumé:\n";
echo "  • Fichiers JSON trouvés: " . $stats['total_files'] . "\n";
echo "  • Fichiers valides: " . $stats['valid_files'] . "\n";
echo "  • Total d'événements: " . $stats['total_events'] . "\n\n";

echo "📋 Détails par fichier:\n";
foreach ($stats['files'] as $filename => $info) {
    $indicator = $info['valid'] === $info['total'] ? '✅' : '⚠️ ';
    echo "$indicator $filename\n";
    echo "    - Événements: {$info['total']} (valides: {$info['valid']}, invalides: {$info['invalid']})\n";
}

if (!empty($errors)) {
    echo "\n⚠️  Erreurs et avertissements:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
} else {
    echo "\n✅ Aucune erreur détectée!\n";
}

echo "\n===============================================\n";
echo "Test terminé\n";
echo "===============================================\n";
?>
