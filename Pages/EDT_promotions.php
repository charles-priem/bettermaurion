<?php
session_start();
date_default_timezone_set('Europe/Paris');

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$dataPath = __DIR__ . '/../data/plannings_promotions.json';

$monday = new DateTime('monday this week');
$monday->modify(($weekOffset >= 0 ? '+' : '') . $weekOffset . ' weeks');
$sunday = clone $monday;
$sunday->modify('+6 days');

$weekStart = clone $monday;
$weekStart->setTime(0, 0, 0);
$weekEnd = clone $sunday;
$weekEnd->setTime(23, 59, 59);

$weekLabel = 'Semaine ' . $monday->format('W') . ' - ' . $monday->format('d') . ' au ' . $sunday->format('d M Y');
$error = '';

function parsePromotionKey(string $key): array {
  $parts = explode('-', trim($key), 2);
  return [
    trim($parts[0] ?? ''),
    trim($parts[1] ?? ''),
  ];
}

function buildPlanningCatalogTree(array $catalogEntries): array {
  $tree = [];

  foreach ($catalogEntries as $entry) {
    if (!is_array($entry)) {
      continue;
    }

    $parentLabel = trim((string)($entry['label'] ?? $entry['choiceLabel'] ?? $entry['parent'] ?? ''));
    if ($parentLabel === '') {
      continue;
    }

    $parentValue = trim((string)($entry['choiceIdInit'] ?? $parentLabel));
    if ($parentValue === '') {
      $parentValue = $parentLabel;
    }

    $tree[$parentValue] = [
      'label' => $parentLabel,
      'value' => $parentValue,
      'choiceIdInit' => trim((string)($entry['choiceIdInit'] ?? '')),
      'codes' => (int)($entry['codes'] ?? 0),
      'children' => [],
    ];

    $children = $entry['children'] ?? [];
    if (!is_array($children)) {
      continue;
    }

    foreach ($children as $childEntry) {
      if (!is_array($childEntry)) {
        continue;
      }

      $childLabel = trim((string)($childEntry['label'] ?? $childEntry['choiceLabel'] ?? $childEntry['resolvedLabel'] ?? ''));
      if ($childLabel === '') {
        continue;
      }

      $childValue = trim((string)($childEntry['choiceIdInit'] ?? $childEntry['planningIdInit'] ?? $childLabel));
      if ($childValue === '') {
        $childValue = $childLabel;
      }

      $childNode = [
        'label' => $childLabel,
        'value' => $childValue,
        'choiceIdInit' => trim((string)($childEntry['choiceIdInit'] ?? '')),
        'planningIdInit' => trim((string)($childEntry['planningIdInit'] ?? '')),
        'resolvedLabel' => trim((string)($childEntry['resolvedLabel'] ?? '')),
        'events' => is_array($childEntry['events'] ?? null) ? array_values($childEntry['events']) : [],
      ];

      $tree[$parentValue]['children'][$childValue] = $childNode;
      $tree[$parentValue]['count'] = ($tree[$parentValue]['count'] ?? 0) + count($childNode['events']);
    }
  }

  uasort($tree, static function (array $left, array $right): int {
    return strnatcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
  });

  foreach ($tree as $parentKey => $group) {
    $children = $group['children'] ?? [];
    if (is_array($children)) {
      uasort($children, static function (array $left, array $right): int {
        return strnatcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
      });
      $group['children'] = $children;
    }
    $tree[$parentKey] = $group;
  }

  return $tree;
}

function buildPromotionTree(array $rawPromotions): array {
  $tree = [];

  foreach ($rawPromotions as $rawKey => $events) {
    if (!is_array($events)) {
      continue;
    }

    $key = trim((string)$rawKey);
    if ($key === '') {
      continue;
    }

    [$parent, $child] = parsePromotionKey($key);
    if ($parent === '') {
      $parent = $key;
    }
    if ($child === '') {
      $child = 'Principal';
    }

    if (!isset($tree[$parent])) {
      $tree[$parent] = [
        'label' => $parent,
        'count' => 0,
        'children' => [],
      ];
    }

    $eventCount = count($events);
    $tree[$parent]['children'][$child] = [
      'label' => $child,
      'key' => $key,
      'count' => $eventCount,
      'events' => array_values($events),
    ];
    $tree[$parent]['count'] += $eventCount;
  }

  uasort($tree, static function (array $left, array $right): int {
    return strnatcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
  });
  foreach ($tree as $parentKey => $group) {
    $children = $group['children'] ?? [];
    if (is_array($children)) {
      uasort($children, static function (array $left, array $right): int {
        return strnatcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
      });
      $group['children'] = $children;
    }
    $tree[$parentKey] = $group;
  }

  return $tree;
}

function eventTypeKeys(?string $className, ?string $typeRaw = null): array {
  $sources = [
    strtoupper(trim((string)$className)),
    strtoupper(trim((string)$typeRaw)),
  ];

  foreach ($sources as $source) {
    if ($source === '') {
      continue;
    }

    if (str_contains($source, 'EPREUVE') || in_array($source, ['CC', 'DS', 'EXAM', 'PARTIEL', 'RATTRAPAGE', 'INTERRO_SURV'], true)) {
      return ['exam'];
    }

    $types = [];
    if (str_contains($source, 'PROJET')) {
      $types[] = 'projet';
    }

    $hasCours = str_contains($source, 'COURS');
    $hasTd = str_contains($source, 'TD');
    $hasTp = str_contains($source, 'TP');

    if ($hasCours && $hasTd) {
      $types[] = 'cours';
      $types[] = 'td';
    } elseif ($hasTp) {
      $types[] = 'tp';
    } elseif ($hasTd) {
      $types[] = 'td';
    } elseif ($hasCours) {
      $types[] = 'cours';
    }

    $types = array_values(array_unique($types));
    if ($types !== []) {
      return $types;
    }
  }

  return ['cours'];
}

function eventTypeKey(?string $className): string {
  return eventTypeKeys($className)[0] ?? 'cours';
}

function eventTypeLabel(string $type): string {
  return match ($type) {
    'tp' => 'TP',
    'td' => 'TD',
    'projet' => 'Projet',
    'exam' => 'Examen',
    default => 'Cours',
  };
}

function eventTypeLabelList(array $types): string {
  $labels = array_map(static fn(string $type): string => eventTypeLabel($type), $types);
  $labels = array_values(array_unique($labels));

  return implode(' / ', $labels);
}

function typeColor(string $class): string {
  return match (eventTypeKey($class)) {
    'exam' => 'type-exam',
    'tp' => 'type-tp',
    'td' => 'type-td',
    'projet' => 'type-projet',
    default => 'type-cours',
  };
}

function buildPromotionsUrl(array $params = []): string {
  $query = array_merge([
    'week' => isset($_GET['week']) ? (int)$_GET['week'] : 0,
  ], $params);

  foreach ($query as $key => $value) {
    if ($value === null || $value === '') {
      unset($query[$key]);
    }
  }

  return '?' . http_build_query($query);
}

$raw = @file_get_contents($dataPath);
$decoded = is_string($raw) ? json_decode($raw, true) : null;
$catalogEntries = is_array($decoded) && isset($decoded['planningCatalog']) && is_array($decoded['planningCatalog'])
  ? $decoded['planningCatalog']
  : [];
$legacyPromotions = is_array($decoded) && isset($decoded['promotions']) && is_array($decoded['promotions'])
  ? $decoded['promotions']
  : [];

if ($catalogEntries === [] && $legacyPromotions === []) {
  $error = 'Aucune donnee de planning chargee. Remplis data/plannings_promotions.json.';
}

$usingPlanningCatalog = $catalogEntries !== [];
$promotionTree = $usingPlanningCatalog
  ? buildPlanningCatalogTree($catalogEntries)
  : buildPromotionTree($legacyPromotions);
$promotionParents = array_keys($promotionTree);

$requestedParent = trim((string)($_GET['promo'] ?? ''));
$requestedChild = trim((string)($_GET['child'] ?? ''));

$selectedParent = '';
$selectedChild = '';

if ($requestedParent !== '') {
  if (isset($promotionTree[$requestedParent])) {
    $selectedParent = $requestedParent;
  } else {
    foreach ($promotionTree as $parentKey => $parentNode) {
      if (
        $parentNode['label'] === $requestedParent ||
        ($parentNode['choiceIdInit'] ?? '') === $requestedParent ||
        ($parentNode['value'] ?? '') === $requestedParent
      ) {
        $selectedParent = $parentKey;
        break;
      }
    }

    if ($selectedParent === '' && !$usingPlanningCatalog) {
      [$maybeParent, $maybeChild] = parsePromotionKey($requestedParent);
      if ($maybeParent !== '' && isset($promotionTree[$maybeParent])) {
        $selectedParent = $maybeParent;
        $selectedChild = $requestedChild !== '' ? $requestedChild : $maybeChild;
      }
    }
  }
}

if ($selectedParent === '') {
  $selectedParent = $promotionParents[0] ?? '';
}

$childOptions = $selectedParent !== '' ? array_keys($promotionTree[$selectedParent]['children']) : [];
if ($requestedChild !== '' && $selectedParent !== '') {
  if (isset($promotionTree[$selectedParent]['children'][$requestedChild])) {
    $selectedChild = $requestedChild;
  } else {
    foreach ($promotionTree[$selectedParent]['children'] as $childKey => $childNode) {
      if (
        $childNode['label'] === $requestedChild ||
        ($childNode['choiceIdInit'] ?? '') === $requestedChild ||
        ($childNode['planningIdInit'] ?? '') === $requestedChild ||
        ($childNode['value'] ?? '') === $requestedChild
      ) {
        $selectedChild = $childKey;
        break;
      }
    }
  }
}

if ($selectedChild === '' || !isset($promotionTree[$selectedParent]['children'][$selectedChild])) {
  $selectedChild = $childOptions[0] ?? '';
}

$selectedNode = $selectedParent !== '' && $selectedChild !== ''
  ? ($promotionTree[$selectedParent]['children'][$selectedChild] ?? null)
  : null;

if (!$selectedNode && $selectedParent !== '') {
  $selectedChild = $childOptions[0] ?? '';
  $selectedNode = $selectedChild !== '' ? ($promotionTree[$selectedParent]['children'][$selectedChild] ?? null) : null;
}

$selectedChoiceId = (string)($selectedNode['choiceIdInit'] ?? '');
$selectedPlanningId = (string)($selectedNode['planningIdInit'] ?? '');
$selectedPathLabel = $selectedParent !== ''
  ? ($selectedChild !== '' ? ($promotionTree[$selectedParent]['label'] ?? $selectedParent) . ' > ' . ($selectedNode['label'] ?? $selectedChild) : ($promotionTree[$selectedParent]['label'] ?? $selectedParent))
  : '';

$events = [];

// Si mode legacy (pas catalog), charge depuis JSON
if (!$usingPlanningCatalog && isset($legacyPromotions[$selectedChild]) && is_array($legacyPromotions[$selectedChild])) {
  $events = $legacyPromotions[$selectedChild];
}
// Si mode catalog, les événements seront chargés par AJAX

$catalogNotice = '';
if ($usingPlanningCatalog && $selectedNode && $events === []) {
  $catalogNotice = 'Aucun événement n\'est encore associé à cette sélection. Les identifiants de navigation sont bien chargés, mais les données d\'emploi du temps n\'ont pas été récupérées pour ce planning.';
}

$eventTypeCounts = [
  'cours' => 0,
  'tp' => 0,
  'td' => 0,
  'projet' => 0,
  'exam' => 0,
];
foreach ($events as $ev) {
  $titleParts = parseTitleParts($ev['title'] ?? '');
  $typeKeys = eventTypeKeys($ev['className'] ?? '', $titleParts['typeRaw']);
  foreach ($typeKeys as $typeKey) {
    if (isset($eventTypeCounts[$typeKey])) {
      $eventTypeCounts[$typeKey]++;
    }
  }
}

$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$dayDates = [];
for ($i = 0; $i < 6; $i++) {
    $d = clone $monday;
    $d->modify('+' . $i . ' days');
    $dayDates[$i] = $d->format('Y-m-d');
}

function parseEventDate(?string $value): ?DateTime {
    if (!is_string($value) || $value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\\TH:i:sO', $value);
    if ($dt instanceof DateTime) {
        return $dt;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    $fallback = new DateTime();
    $fallback->setTimestamp($ts);
    return $fallback;
}

function parseTitleParts(?string $title): array {
    $title = is_string($title) ? $title : '';
    $parts = array_map('trim', preg_split('/\r\n|\r|\n/', $title));
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    return [
        'salle' => $parts[0] ?? '',
        'matiere' => $parts[1] ?? 'Sans titre',
        'typeRaw' => $parts[2] ?? '',
        'prof' => $parts[3] ?? '',
    ];
}

$eventsByDay = array_fill(0, 6, []);
foreach ($events as $ev) {
    $start = parseEventDate($ev['start'] ?? null);
    $end = parseEventDate($ev['end'] ?? null);
    if (!$start || !$end) {
        continue;
    }
    if ($start < $weekStart || $start > $weekEnd) {
        continue;
    }

    $dateKey = $start->format('Y-m-d');
    $dayIdx = array_search($dateKey, $dayDates, true);
    if ($dayIdx === false) {
        continue;
    }

    $titleParts = parseTitleParts($ev['title'] ?? '');
    $className = (string)($ev['className'] ?? '');
    $typeKeys = eventTypeKeys($className, $titleParts['typeRaw']);
    $primaryType = $typeKeys[0] ?? 'cours';

    $eventsByDay[$dayIdx][] = [
        'id' => (string)($ev['id'] ?? ''),
        'startTime' => $start->format('H:i'),
        'endTime' => $end->format('H:i'),
        'salle' => $titleParts['salle'],
        'matiere' => $titleParts['matiere'],
        'prof' => $titleParts['prof'],
        'className' => $className,
      'typeKeys' => $typeKeys,
      'typeClass' => typeColor($primaryType),
      'typeLabel' => eventTypeLabelList($typeKeys),
    ];
}

foreach ($eventsByDay as &$dayEvs) {
    usort($dayEvs, static fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
}
unset($dayEvs);

$todayStr = (new DateTime())->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Emploi du temps par promotions</title>
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; }
    .wrap { max-width: 1280px; margin: 24px auto; padding: 0 16px; }
    .toolbar { display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:12px; }
    .toolbar h1 { margin:0; font-size:24px; }
    .filters { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .filters select, .filters button, .filters a { height:36px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 10px; text-decoration:none; color:#111; }
    .selection-form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .selection-form label { font-weight:600; }
    .selection-form select { min-width: 190px; }
    .selection-summary { font-size:13px; color:#555; }
    .week-nav { display:flex; gap:8px; align-items:center; }
    .week-label { min-width:260px; text-align:center; font-weight:600; }
    .notice { background:#fff3cd; border:1px solid #ffe69c; padding:10px 12px; border-radius:8px; margin-bottom:10px; }
    .type-filters { display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:12px 14px; background:#fff; border:1px solid #ddd; border-radius:12px; margin-bottom:12px; }
    .type-filters strong { margin-right:4px; }
    .type-filters label { display:inline-flex; gap:6px; align-items:center; font-size:13px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:999px; padding:6px 10px; cursor:pointer; }
    .type-filters input { accent-color:#111; }
    .type-filters button { height:36px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 10px; cursor:pointer; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; background:#fff; border:1px solid #ddd; }
    th, td { border:1px solid #ddd; vertical-align:top; }
    th { background:#fafafa; padding:10px 6px; text-align:center; }
    td { padding:8px; min-height:120px; }
    .day-date { display:block; font-weight:700; margin-top:4px; }
    .today .day-date { display:inline-flex; width:30px; height:30px; border-radius:50%; align-items:center; justify-content:center; background:#111; color:#fff; }
    .ev { padding:8px; border-left:4px solid #999; border-radius:6px; margin-bottom:7px; background:#f7f7f7; font-size:13px; }
    .ev.is-hidden { display:none; }
    .ev:last-child { margin-bottom:0; }
    .ev-time { font-size:12px; font-weight:700; }
    .ev-badge { display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,0.75); font-size:11px; font-weight:700; }
    .ev-matiere { font-weight:700; margin:3px 0; }
    .ev-meta { color:#555; font-size:12px; }
    .type-cours { background:#dbeafe; border-color:#60a5fa; }
    .type-tp { background:#dcfce7; border-color:#4ade80; }
    .type-td { background:#fef3c7; border-color:#f59e0b; }
    .type-projet { background:#ede9fe; border-color:#8b5cf6; }
    .type-exam { background:#fee2e2; border-color:#ef4444; }
    .empty { color:#888; text-align:center; padding:20px 0; }
  </style>
</head>
<body>
  <header class="header">
    <nav class="nav">
      <ul class="nav_list">
        <li class="nav_item _dropdown">
          <button class="dropbtn">Emploi du temps</button>
          <div class="dropdown-content">
            <a href="../Pages/EDT_perso.php">Mon emploi du temps</a>
            <a href="../Pages/EDT_promotions.php">Emploi du temps par promotions</a>
          </div>
        </li>
      </ul>
    </nav>
  </header>

  <main class="wrap">
    <div class="toolbar">
      <h1>Emploi du temps par promotion</h1>
      <div class="filters">
        <form method="get" action="" class="selection-form">
          <input type="hidden" name="week" value="<?= (int)$weekOffset ?>">
          <label for="promo">Promotion</label>
          <select id="promo" name="promo" onchange="this.form.submit()">
            <?php foreach ($promotionParents as $parent): ?>
              <option value="<?= htmlspecialchars($promotionTree[$parent]['choiceIdInit'] ?: $promotionTree[$parent]['value'] ?: $parent) ?>" <?= $parent === $selectedParent ? 'selected' : '' ?>>
                <?= htmlspecialchars($promotionTree[$parent]['label'] ?? $parent) ?> (<?= (int)(count($promotionTree[$parent]['children'] ?? [])) ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <label for="child">Planning</label>
          <select id="child" name="child" onchange="this.form.submit()">
            <?php foreach ($childOptions as $child): ?>
              <?php $childNode = $promotionTree[$selectedParent]['children'][$child]; ?>
              <option value="<?= htmlspecialchars($childNode['choiceIdInit'] ?: $childNode['planningIdInit'] ?: $child) ?>" data-planning-id="<?= htmlspecialchars($childNode['planningIdInit'] ?? '') ?>" <?= $child === $selectedChild ? 'selected' : '' ?>>
                <?= htmlspecialchars($childNode['label'] ?? $child) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <div class="toolbar" style="margin-top:0;">
      <div class="week-nav">
        <a href="<?= htmlspecialchars(buildPromotionsUrl(['promo' => $selectedParent, 'child' => $selectedChild, 'week' => $weekOffset - 1])) ?>">Precedente</a>
        <span class="week-label"><?= htmlspecialchars($weekLabel) ?></span>
        <a href="<?= htmlspecialchars(buildPromotionsUrl(['promo' => $selectedParent, 'child' => $selectedChild, 'week' => $weekOffset + 1])) ?>">Suivante</a>
      </div>
      <a href="<?= htmlspecialchars(buildPromotionsUrl(['promo' => $selectedParent, 'child' => $selectedChild, 'week' => 0])) ?>">Aujourd'hui</a>
    </div>

    <div class="selection-summary">
      Sélection courante: <strong><?= htmlspecialchars($selectedPathLabel ?: 'Aucune sélection') ?></strong>
      <?php if ($selectedChoiceId !== ''): ?>
        <span> - choiceIdInit: <?= htmlspecialchars($selectedChoiceId) ?></span>
      <?php endif; ?>
      <?php if ($selectedPlanningId !== ''): ?>
        <span> - planningIdInit: <?= htmlspecialchars($selectedPlanningId) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($catalogNotice !== ''): ?>
      <div class="notice"><?= htmlspecialchars($catalogNotice) ?></div>
    <?php endif; ?>

    <div class="type-filters" id="type-filters">
      <strong>Afficher</strong>
      <label><input type="checkbox" data-type-toggle value="cours" checked> Cours <span>(<?= (int)$eventTypeCounts['cours'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="tp" checked> TP <span>(<?= (int)$eventTypeCounts['tp'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="td" checked> TD <span>(<?= (int)$eventTypeCounts['td'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="projet" checked> Projet <span>(<?= (int)$eventTypeCounts['projet'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="exam" checked> Examen <span>(<?= (int)$eventTypeCounts['exam'] ?>)</span></label>
      <button type="button" id="reset-types">Tout afficher</button>
    </div>

    <?php if ($error !== ''): ?>
      <div class="notice"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <?php foreach ($days as $i => $dayName):
            $d = clone $monday;
            $d->modify('+' . $i . ' days');
            $isToday = $d->format('Y-m-d') === $todayStr;
          ?>
            <th class="<?= $isToday ? 'today' : '' ?>">
              <?= htmlspecialchars($dayName) ?>
              <span class="day-date"><?= $d->format('d') ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <?php foreach ($eventsByDay as $dayEvents): ?>
            <td>
              <?php if ($usingPlanningCatalog): ?>
                <div class="empty">Chargement...</div>
              <?php elseif (count($dayEvents) === 0): ?>
                <div class="empty">-</div>
              <?php else: ?>
                <?php foreach ($dayEvents as $ev):
                  $typeKeys = $ev['typeKeys'] ?? eventTypeKeys($ev['className'] ?? '');
                  $typeLabel = $ev['typeLabel'] ?? eventTypeLabelList($typeKeys);
                  $typeData = implode(' ', $typeKeys);
                  $color = $ev['typeClass'] ?? typeColor($ev['className']);
                ?>
                  <div class="ev <?= $color ?>" data-types="<?= htmlspecialchars($typeData) ?>">
                    <div class="ev-time"><?= htmlspecialchars($ev['startTime']) ?> - <?= htmlspecialchars($ev['endTime']) ?><span class="ev-badge"><?= htmlspecialchars($typeLabel) ?></span></div>
                    <div class="ev-matiere"><?= htmlspecialchars($ev['matiere']) ?></div>
                    <div class="ev-meta">
                      <?php if ($ev['salle'] !== ''): ?>Salle: <?= htmlspecialchars($ev['salle']) ?><br><?php endif; ?>
                      <?php if ($ev['prof'] !== ''): ?><?= htmlspecialchars($ev['prof']) ?><?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
  </main>

  <script>
    (function () {
      const storageKey = 'edt_promotions_visible_types';
      const checkboxes = Array.from(document.querySelectorAll('[data-type-toggle]'));
      const resetButton = document.getElementById('reset-types');
      const promo = document.getElementById('promo');
      const child = document.getElementById('child');
      let cachedEvents = [];

      function typeColor(className) {
        const types = eventTypeKeys(className);
        const type = types[0] || 'cours';
        return {
          'exam': 'type-exam',
          'tp': 'type-tp',
          'td': 'type-td',
          'projet': 'type-projet',
        }[type] || 'type-cours';
      }

      function eventTypeKeys(className, typeRaw) {
        const sources = [
          (className || '').toUpperCase(),
          (typeRaw || '').toUpperCase(),
        ];

        for (const source of sources) {
          if (!source) continue;
          
          if (source.includes('EPREUVE') || ['CC', 'DS', 'EXAM', 'PARTIEL', 'RATTRAPAGE'].includes(source)) {
            return ['exam'];
          }

          const types = [];
          if (source.includes('PROJET')) types.push('projet');
          
          const hasCours = source.includes('COURS');
          const hasTd = source.includes('TD');
          const hasTp = source.includes('TP');

          if (hasCours && hasTd) {
            types.push('cours', 'td');
          } else if (hasTp) {
            types.push('tp');
          } else if (hasTd) {
            types.push('td');
          } else if (hasCours) {
            types.push('cours');
          }

          if (types.length > 0) return types;
        }
        return ['cours'];
      }

      function eventTypeLabel(type) {
        return { tp: 'TP', td: 'TD', projet: 'Projet', exam: 'Examen' }[type] || 'Cours';
      }

      function eventTypeLabelList(types) {
        return [...new Set(types.map(eventTypeLabel))].join(' / ');
      }

      function parseEventDate(value) {
        if (!value) return null;
        return new Date(value);
      }

      function parseTitleParts(title) {
        const parts = (title || '').split(/\r\n|\r|\n/).map(p => p.trim()).filter(p => p);
        return {
          salle: parts[0] || '',
          matiere: parts[1] || 'Sans titre',
          typeRaw: parts[2] || '',
          prof: parts[3] || '',
        };
      }

      function formatEventHTML(ev, dayDate) {
        const titleParts = parseTitleParts(ev.title);
        const typeKeys = eventTypeKeys(ev.className, titleParts.typeRaw);
        const typeLabel = eventTypeLabelList(typeKeys);
        const typeData = typeKeys.join(' ');
        const color = typeColor(ev.className);

        const start = parseEventDate(ev.start);
        const end = parseEventDate(ev.end);
        const startTime = start ? start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        const endTime = end ? end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';

        return `
          <div class="ev ${color}" data-types="${typeData}">
            <div class="ev-time">${startTime} - ${endTime}<span class="ev-badge">${typeLabel}</span></div>
            <div class="ev-matiere">${titleParts.matiere}</div>
            <div class="ev-meta">
              ${titleParts.salle ? 'Salle: ' + titleParts.salle + '<br>' : ''}
              ${titleParts.prof}
            </div>
          </div>
        `;
      }

      async function fetchAndRenderEvents() {
        const selectedOption = child?.options[child?.selectedIndex];
        const planningId = selectedOption?.getAttribute('data-planning-id');
        const weekParam = new URLSearchParams(window.location.search).get('week') || '0';

        if (!planningId) {
          console.log('No planningIdInit available');
          renderEmptyTable();
          return;
        }

        try {
          const response = await fetch(`../api/fetch_events.php?planningIdInit=${encodeURIComponent(planningId)}&week=${weekParam}`);
          const data = await response.json();

          if (data.error) {
            console.error('Fetch error:', data.error);
            renderEmptyTable();
            return;
          }

          cachedEvents = data.events || [];
          renderTable();
          applyFilters();
        } catch (err) {
          console.error('Fetch failed:', err);
          renderEmptyTable();
        }
      }

      function renderEmptyTable() {
        const rows = document.querySelectorAll('tbody tr td');
        rows.forEach(td => {
          td.innerHTML = '<div class="empty">-</div>';
        });
      }

      function renderTable() {
        if (!cachedEvents || cachedEvents.length === 0) {
          renderEmptyTable();
          return;
        }

        const weekOffset = new URLSearchParams(window.location.search).get('week') || '0';
        const monday = new Date();
        monday.setDate(monday.getDate() - monday.getDay() + 1);
        monday.setDate(monday.getDate() + (weekOffset * 7));

        const dayDates = [];
        for (let i = 0; i < 6; i++) {
          const d = new Date(monday);
          d.setDate(d.getDate() + i);
          dayDates.push(d.toISOString().split('T')[0]);
        }

        const eventsByDay = Array(6).fill(null).map(() => []);

        cachedEvents.forEach(ev => {
          const start = parseEventDate(ev.start);
          if (!start) return;

          const dateStr = start.toISOString().split('T')[0];
          const dayIdx = dayDates.indexOf(dateStr);
          if (dayIdx === -1) return;

          eventsByDay[dayIdx].push(ev);
        });

        eventsByDay.forEach(dayEvs => {
          dayEvs.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
        });

        const rows = document.querySelectorAll('tbody tr td');
        rows.forEach((td, idx) => {
          if (idx >= 6) return;
          const dayEvs = eventsByDay[idx] || [];
          if (dayEvs.length === 0) {
            td.innerHTML = '<div class="empty">-</div>';
          } else {
            td.innerHTML = dayEvs.map(ev => formatEventHTML(ev, dayDates[idx])).join('');
          }
        });
      }

      function getSelectedTypes() {
        return checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
      }

      function saveState() {
        try {
          window.localStorage.setItem(storageKey, JSON.stringify(getSelectedTypes()));
        } catch (error) {
          // ignore
        }
      }

      function loadState() {
        try {
          const raw = window.localStorage.getItem(storageKey);
          if (!raw) return;
          const values = JSON.parse(raw);
          if (!Array.isArray(values) || values.length === 0) return;
          checkboxes.forEach((checkbox) => {
            checkbox.checked = values.includes(checkbox.value);
          });
        } catch (error) {
          // ignore
        }
      }

      function applyFilters() {
        const selected = new Set(getSelectedTypes());
        const events = Array.from(document.querySelectorAll('.ev[data-types]'));
        events.forEach((event) => {
          const eventTypes = (event.dataset.types || '').split(/\s+/).filter(Boolean);
          const isVisible = eventTypes.some((type) => selected.has(type));
          event.classList.toggle('is-hidden', !isVisible);
        });
      }

      // Event listeners
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          saveState();
          applyFilters();
        });
      });

      if (resetButton) {
        resetButton.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
          });
          saveState();
          applyFilters();
        });
      }

      loadState();
      
      // Load events on page load if using planning catalog
      const usingCatalog = <?= $usingPlanningCatalog ? 'true' : 'false' ?>;
      if (usingCatalog) {
        fetchAndRenderEvents();
      } else {
        applyFilters();
      }
    })();
  </script>
</body>
</html>
