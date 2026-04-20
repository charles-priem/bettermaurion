<?php
session_start();
require_once '../php/config.php';
date_default_timezone_set('Europe/Paris');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Pages/connexion.php');
    exit;
}

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$dataPath = __DIR__ . '/../plannings/';

$monday = new DateTime('monday this week');
$monday->modify(($weekOffset >= 0 ? '+' : '') . $weekOffset . ' weeks');

$saturday = clone $monday;
$saturday->modify('+5 days');

$weekStart = clone $monday;
$weekStart->setTime(0, 0, 0);

$weekEnd = clone $saturday;
$weekEnd->setTime(23, 59, 59);

$weekLabel = 'Semaine ' . $monday->format('W') . ' - ' . $monday->format('d') . ' au ' . $saturday->format('d M Y');
$error = '';

function buildPromotionsUrl(array $params = []): string {
  $query = array_merge([
    'week' => isset($_GET['week']) ? (int)$_GET['week'] : 0,
  ], $params);

  foreach ($query as $key => $value) {
    if ($value === null || $value === '') unset($query[$key]);
  }

  return '?' . http_build_query($query);
}

function prettyPlanningLabel(string $raw): string {
  $raw = trim($raw);
  if ($raw === '') return '';

  $parts = preg_split('/_+/', $raw);

  $parts = array_values(array_filter($parts, static function ($p) {
    $p = strtoupper(trim((string)$p));
    return $p !== '' && !in_array($p, ['COMPLET', 'COMPLETE', 'FULL'], true);
  }));

  if ($parts === []) return $raw;

  $yearIndicators = ['A1','A2','A3','A4','A5','CIR1','CIR2','CIR3','CSI3','AP3','AP4','AP5','M1','M2','CPG2'];

  $year = '';
  foreach ($parts as $p) {
    $u = strtoupper($p);
    if (in_array($u, $yearIndicators, true)) { $year = $u; break; }
  }

  $name = ucfirst(strtolower($parts[0])); // ISEN -> Isen
  return $year !== '' ? ($name . ' ' . $year) : $name;
}

function buildPromotionTreeFromFiles(string $basePath): array {
  $tree = [];
  if (!is_dir($basePath)) return [];

  $yearIndicators = ['A1','A2','A3','A4','A5','CIR1','CIR2','CIR3','CSI3','AP3','AP4','AP5','M1','M2','CPG2'];

  foreach (new DirectoryIterator($basePath) as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') continue;

    $filename = $file->getBasename('.json'); // ex: ADIMAKER_Lille_A1_COMPLET
    $parts = explode('_', $filename);

    $parentParts = [];
    $foundYear = false;

    foreach ($parts as $part) {
      if (!$foundYear && in_array($part, $yearIndicators, true)) $foundYear = true;
      if (!$foundYear) $parentParts[] = $part;
    }

    if ($parentParts === []) $parentParts = [$parts[0] ?? $filename];

    $parentKey = implode('_', $parentParts);
    if (!isset($tree[$parentKey])) {
      $tree[$parentKey] = [
        'label' => str_replace('_', ' ', $parentKey),
        'children' => [],
      ];
    }

    // count events
    $eventCount = 0;
    $filePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $filename . '.json';
    $raw = @file_get_contents($filePath);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
      $eventsData = is_array($decoded['events'] ?? null) ? $decoded['events'] : [];
      $eventCount = count($eventsData);
    }

    $tree[$parentKey]['children'][$filename] = [
      'planningIdInit' => $filename,
      'label' => prettyPlanningLabel($filename),
      'eventCount' => $eventCount,
    ];
  }

  uasort($tree, static fn($a, $b) => strnatcasecmp($a['label'] ?? '', $b['label'] ?? ''));
  foreach ($tree as &$p) {
    uasort($p['children'], static fn($a, $b) => strnatcasecmp($a['label'] ?? '', $b['label'] ?? ''));
  }
  unset($p);

  return $tree;
}

function eventTypeKeys(?string $className, ?string $typeRaw = null): array {
  $sources = [
    strtoupper(trim((string)$className)),
    strtoupper(trim((string)$typeRaw)),
  ];

  foreach ($sources as $source) {
    if ($source === '') continue;

    if (
      str_contains($source, 'EPREUVE') ||
      str_contains($source, 'CC') ||
      str_contains($source, 'DS') ||
      str_contains($source, 'EXAM') ||
      str_contains($source, 'PARTIEL') ||
      str_contains($source, 'RATTRAPAGE') ||
      str_contains($source, 'INTERRO')
    ) return ['exam'];

    $types = [];
    if (str_contains($source, 'PROJET')) $types[] = 'projet';

    $hasCours = str_contains($source, 'COURS');
    $hasTd = str_contains($source, 'TD');
    $hasTp = str_contains($source, 'TP');

    if ($hasCours && $hasTd) $types = array_merge($types, ['cours','td']);
    elseif ($hasTp) $types[] = 'tp';
    elseif ($hasTd) $types[] = 'td';
    elseif ($hasCours) $types[] = 'cours';

    $types = array_values(array_unique($types));
    if ($types !== []) return $types;
  }

  return ['cours'];
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
  $labels = array_map(static fn($t) => eventTypeLabel((string)$t), $types);
  $labels = array_values(array_unique($labels));
  return implode(' / ', $labels);
}

function typeColor(string $classOrType): string {
  $key = eventTypeKeys($classOrType)[0] ?? 'cours';
  return match ($key) {
    'exam' => 'type-exam',
    'tp' => 'type-tp',
    'td' => 'type-td',
    'projet' => 'type-projet',
    default => 'type-cours',
  };
}

function parseEventDate(?string $value): ?DateTime {
  if (!is_string($value) || $value === '') return null;

  $dt = DateTime::createFromFormat('Y-m-d\\TH:i:sO', $value);
  if ($dt instanceof DateTime) return $dt;

  $ts = strtotime($value);
  if ($ts === false) return null;

  $fallback = new DateTime();
  $fallback->setTimestamp($ts);
  return $fallback;
}

function validateEvent($event): bool {
  return is_array($event)
    && isset($event['start'], $event['end'], $event['title'])
    && is_string($event['start']) && $event['start'] !== ''
    && is_string($event['end']) && $event['end'] !== ''
    && is_string($event['title']);
}

function parseTitleParts(?string $title): array {
  $title = is_string($title) ? $title : '';
  $parts = array_map('trim', preg_split('/\r\n|\r|\n/', $title));
  $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));

  // 5 lignes: salle, matière, type, horaires, prof
  // 4 lignes: salle, matière, type, prof
  $salle = $parts[0] ?? '';
  $matiere = $parts[1] ?? 'Sans titre';
  $typeRaw = $parts[2] ?? '';
  $prof = '';

  if (isset($parts[4])) $prof = $parts[4];
  elseif (isset($parts[3]) && !preg_match('/\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}/', $parts[3])) $prof = $parts[3];

  return compact('salle', 'matiere', 'typeRaw', 'prof');
}

function clampInt(int $v, int $min, int $max): int {
  return max($min, min($max, $v));
}

/* ========= LOAD TREE ========= */
$promotionTree = buildPromotionTreeFromFiles($dataPath);
$promotionParents = array_keys($promotionTree);

$selectedParent = trim((string)($_GET['promo'] ?? ''));
if ($selectedParent === '' || !isset($promotionTree[$selectedParent])) {
  $selectedParent = $promotionParents[0] ?? '';
}

$childOptions = $selectedParent !== '' ? array_keys($promotionTree[$selectedParent]['children'] ?? []) : [];
$selectedChild = trim((string)($_GET['child'] ?? ''));
if ($selectedChild === '' || !isset($promotionTree[$selectedParent]['children'][$selectedChild])) {
  $selectedChild = $childOptions[0] ?? '';
}

$selectedPlanningId = (string)($promotionTree[$selectedParent]['children'][$selectedChild]['planningIdInit'] ?? '');

$events = [];
if ($selectedChild !== '') {
  $filePath = rtrim($dataPath, '/\\') . DIRECTORY_SEPARATOR . $selectedChild . '.json';
  if (file_exists($filePath)) {
    $raw = @file_get_contents($filePath);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) $events = is_array($decoded['events'] ?? null) ? $decoded['events'] : [];
  }
}

/* ========= BUILD WEEK DAYS ========= */
$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$dayDates = [];
for ($i = 0; $i < 6; $i++) {
  $d = clone $monday;
  $d->modify('+' . $i . ' days');
  $dayDates[$i] = $d->format('Y-m-d');
}

/* ========= TIMELINE SETTINGS ========= */
$dayStartMin = 8 * 60;       // 08:00
$dayEndMin = 20 * 60;        // 20:00
$minutePx = 1.2;             // 1 minute = 1.2px
$timelineHeight = (int)(($dayEndMin - $dayStartMin) * $minutePx);

$lunchStart = 12 * 60;       // 12:00
$lunchEnd = 13 * 60 + 30;    // 13:30
$lunchTop = (int)(($lunchStart - $dayStartMin) * $minutePx);
$lunchHeight = (int)(($lunchEnd - $lunchStart) * $minutePx);

/* ========= GROUP EVENTS BY DAY ========= */
$eventTypeCounts = ['cours'=>0,'tp'=>0,'td'=>0,'projet'=>0,'exam'=>0];
$eventsByDay = array_fill(0, 6, []);

foreach ($events as $ev) {
  if (!validateEvent($ev)) continue;

  $start = parseEventDate($ev['start']);
  $end = parseEventDate($ev['end']);
  if (!$start || !$end) continue;

  if ($start < $weekStart || $start > $weekEnd) continue;

  $dateKey = $start->format('Y-m-d');
  $dayIdx = array_search($dateKey, $dayDates, true);
  if ($dayIdx === false) continue;

  $titleParts = parseTitleParts($ev['title'] ?? '');
  $className = (string)($ev['className'] ?? '');

  $typeKeys = eventTypeKeys($className, $titleParts['typeRaw'] ?? '');
  foreach ($typeKeys as $t) if (isset($eventTypeCounts[$t])) $eventTypeCounts[$t]++;

  $startMin = ((int)$start->format('H'))*60 + (int)$start->format('i');
  $endMin = ((int)$end->format('H'))*60 + (int)$end->format('i');

  $eventsByDay[$dayIdx][] = [
    'id' => (string)($ev['id'] ?? ''),
    'startTime' => $start->format('H:i'),
    'endTime' => $end->format('H:i'),
    'startMin' => $startMin,
    'endMin' => $endMin,
    'salle' => (string)($titleParts['salle'] ?? ''),
    'matiere' => (string)($titleParts['matiere'] ?? 'Sans titre'),
    'prof' => (string)($titleParts['prof'] ?? ''),
    'className' => $className,
    'typeKeys' => $typeKeys,
    'typeClass' => typeColor($typeKeys[0] ?? 'cours'),
    'typeLabel' => eventTypeLabelList($typeKeys),
  ];
}

foreach ($eventsByDay as &$dayEvs) {
  usort($dayEvs, static fn($a, $b) => strcmp($a['startTime'] ?? '', $b['startTime'] ?? ''));
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

    .toolbar { display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; }
    .toolbar h1 { margin:0; font-size:24px; }

    .filters { display:flex; gap:10px; align-items:center; flex-wrap:nowrap; }
    .selection-form { display:flex; gap:10px; align-items:center; flex-wrap:nowrap; }
    .selection-form label { font-weight:600; white-space:nowrap; }
    .selection-form select { min-width: 170px; height:36px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 10px; }

    .week-nav { display:flex; gap:8px; align-items:center; }
    .week-label { min-width:260px; text-align:center; font-weight:600; }
    .filters a, .week-nav a, .type-filters button {
      height:36px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 10px;
      text-decoration:none; color:#111; display:inline-flex; align-items:center;
    }

    .selection-summary { font-size:13px; color:#555; margin-bottom:10px; }

    .type-filters { display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:12px 14px; background:#fff; border:1px solid #ddd; border-radius:12px; margin-bottom:12px; }
    .type-filters strong { margin-right:4px; }
    .type-filters label { display:inline-flex; gap:6px; align-items:center; font-size:13px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:999px; padding:6px 10px; cursor:pointer; }
    .type-filters input { accent-color:#111; }

    /* ===== TIMELINE ===== */
    .timeline { background:#fff; border:1px solid #ddd; border-radius:10px; overflow:hidden; }
    .timeline-header { display:grid; grid-template-columns: 90px repeat(6, 1fr); }
    .timeline-head { padding:10px 6px; text-align:center; background:#fafafa; border-right:1px solid #ddd; }
    .timeline-head:last-child { border-right:0; }
    .timeline-head.is-today { background:#111; color:#fff; }

    .timeline-body { display:grid; grid-template-columns: 90px repeat(6, 1fr); }
    .hours-col { position:relative; background:#fafafa; border-right:1px solid #ddd; }
    .hour-label { position:absolute; left:8px; transform: translateY(-50%); font-size:12px; color:#666; }

    .day-col { position:relative; border-right:1px solid #ddd; overflow:hidden; }
    .day-col:last-child { border-right:0; }

    .hour-line { position:absolute; left:0; right:0; height:1px; background:#f1f5f9; }
    .lunch-band {
      position:absolute; left:0; right:0;
      background: repeating-linear-gradient(
        45deg,
        rgba(0,0,0,0.04),
        rgba(0,0,0,0.04) 6px,
        rgba(0,0,0,0.00) 6px,
        rgba(0,0,0,0.00) 12px
      );
      border-top: 2px solid rgba(0,0,0,0.12);
      border-bottom: 2px solid rgba(0,0,0,0.12);
      pointer-events:none;
    }

    .ev { padding:8px; border-left:4px solid #999; border-radius:6px; background:#f7f7f7; font-size:13px; box-sizing:border-box; }
    .ev.is-hidden { display:none; }
    .ev-time { font-size:12px; font-weight:700; }
    .ev-badge { display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,0.75); font-size:11px; font-weight:700; }
    .ev-matiere { font-weight:700; margin:3px 0; }
    .ev-meta { color:#555; font-size:12px; }

    .ev-abs { position:absolute; left:6px; right:6px; overflow:hidden; }

    .type-cours { background:#dbeafe; border-color:#60a5fa; }
    .type-tp { background:#dcfce7; border-color:#4ade80; }
    .type-td { background:#fef3c7; border-color:#f59e0b; }
    .type-projet { background:#ede9fe; border-color:#8b5cf6; }
    .type-exam { background:#fee2e2; border-color:#ef4444; }

    @media (max-width: 800px) {
      .filters, .selection-form { flex-wrap:wrap; }
    }
  </style>
</head>
<body>
  <header class="header">
    <nav class="nav">
      <ul class="nav_list">
        <li class="nav_item _dropdown">
          <button class="dropbtn">Emploi du temps <i class="fa fa-caret-down"></i></button>
          <div class="dropdown-content">
            <a href="../Pages/EDT_perso.php">Mon emploi du temps</a>
            <a href="../Pages/EDT_promotions.php">Emploi du temps par promotions</a>
          </div>
        </li>
        <li class="nav_item_dropdown">
          <button class="dropbtn">Bâtiments <i class="fa fa-caret-down"></i></button>
          <div class="dropdown-content">
            <a href="#">IC1 </a>
            <a href="#">IC2 </a>
            <a href="#">ALG </a>
            <a href="#">MF </a>
          </div>
        </li>
        <li class="nav_item_dropdown">
          <button class="dropbtn">Services junia <i class="fa fa-caret-down"></i></button>
          <div class="dropdown-content">
            <a href="#">Aurion</a>
            <a href="#">Junia learning</a>
            <a href="#">OneDrive</a>
          </div>
        </li>
        <li class="nav_connection">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../Pages/profil.php">
              <img src="../uploads/<?= $_SESSION['photo_profil']; ?>" alt="Photo de Profil" class="profile-photo" width="40" height="40" style="border-radius: 50%; border: 2px solid #fff;">
            </a>
            <a href="../Pages/profil.php?logout=true"><button>Se déconnecter</button></a>
          <?php else: ?>
            <a class="nav_connection" href="../Pages/connexion.php">Connexion</a>
            <a href="../Pages/inscription.php">S'inscrire</a>
          <?php endif; ?>
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
              <option value="<?= htmlspecialchars($parent) ?>" <?= $parent === $selectedParent ? 'selected' : '' ?>>
                <?= htmlspecialchars($promotionTree[$parent]['label'] ?? $parent) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label for="child">Planning</label>
          <select id="child" name="child" onchange="this.form.submit()">
            <?php foreach ($childOptions as $child): ?>
              <?php $childNode = $promotionTree[$selectedParent]['children'][$child]; ?>
              <option value="<?= htmlspecialchars($child) ?>" <?= $child === $selectedChild ? 'selected' : '' ?>>
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

 
    <div class="type-filters" id="type-filters">
      <strong>Afficher</strong>
      <label><input type="checkbox" data-type-toggle value="cours" checked> Cours <span>(<?= (int)$eventTypeCounts['cours'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="tp" checked> TP <span>(<?= (int)$eventTypeCounts['tp'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="td" checked> TD <span>(<?= (int)$eventTypeCounts['td'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="projet" checked> Projet <span>(<?= (int)$eventTypeCounts['projet'] ?>)</span></label>
      <label><input type="checkbox" data-type-toggle value="exam" checked> Examen <span>(<?= (int)$eventTypeCounts['exam'] ?>)</span></label>
      <button type="button" id="reset-types">Tout afficher</button>
    </div>

    <div class="timeline">
      <div class="timeline-header">
        <div class="timeline-head">Heure</div>
        <?php foreach ($days as $i => $dayName):
          $d = clone $monday;
          $d->modify('+' . $i . ' days');
          $isToday = $d->format('Y-m-d') === $todayStr;
        ?>
          <div class="timeline-head <?= $isToday ? 'is-today' : '' ?>">
            <?= htmlspecialchars($dayName) ?><br>
            <strong><?= htmlspecialchars($d->format('d')) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="timeline-body">
        <div class="hours-col" style="height: <?= (int)$timelineHeight ?>px;">
          <?php for ($h = 8; $h <= 20; $h++): ?>
            <?php $top = (int)(($h*60 - $dayStartMin) * $minutePx); ?>
            <div class="hour-label" style="top: <?= $top ?>px;"><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>:00</div>
          <?php endfor; ?>
        </div>

        <?php foreach ($eventsByDay as $dayEvents): ?>
          <div class="day-col" style="height: <?= (int)$timelineHeight ?>px;">
            <div class="lunch-band" style="top: <?= (int)$lunchTop ?>px; height: <?= (int)$lunchHeight ?>px;"></div>

            <?php for ($h = 8; $h <= 20; $h++): ?>
              <?php $lineTop = (int)(($h*60 - $dayStartMin) * $minutePx); ?>
              <div class="hour-line" style="top: <?= $lineTop ?>px;"></div>
            <?php endfor; ?>

            <?php foreach ($dayEvents as $ev):
              $startMin = clampInt((int)($ev['startMin'] ?? 0), $dayStartMin, $dayEndMin);
              $endMin   = clampInt((int)($ev['endMin'] ?? 0), $dayStartMin, $dayEndMin);
              if ($endMin <= $startMin) continue;

              $top = (int)(($startMin - $dayStartMin) * $minutePx);
              $height = max(30, (int)(($endMin - $startMin) * $minutePx));
            ?>
              <div class="ev ev-abs <?= htmlspecialchars($ev['typeClass'] ?? 'type-cours') ?>"
                   data-types="<?= htmlspecialchars(implode(' ', $ev['typeKeys'] ?? ['cours'])) ?>"
                   style="top: <?= $top ?>px; height: <?= $height ?>px;">
                <div class="ev-time">
                  <?= htmlspecialchars($ev['startTime'] ?? '') ?> - <?= htmlspecialchars($ev['endTime'] ?? '') ?>
                  <span class="ev-badge"><?= htmlspecialchars($ev['typeLabel'] ?? '') ?></span>
                </div>
                <div class="ev-matiere"><?= htmlspecialchars($ev['matiere'] ?? '') ?></div>
                <div class="ev-meta">
                  <?php if (($ev['salle'] ?? '') !== ''): ?>Salle: <?= htmlspecialchars($ev['salle']) ?><br><?php endif; ?>
                  <?php if (($ev['prof'] ?? '') !== ''): ?><?= htmlspecialchars($ev['prof']) ?><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>

  <script>
    (function () {
      const storageKey = 'edt_promotions_visible_types';
      const checkboxes = Array.from(document.querySelectorAll('[data-type-toggle]'));
      const resetButton = document.getElementById('reset-types');

      function getSelectedTypes() {
        return checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
      }

      function saveState() {
        try { window.localStorage.setItem(storageKey, JSON.stringify(getSelectedTypes())); } catch (e) {}
      }

      function loadState() {
        try {
          const raw = window.localStorage.getItem(storageKey);
          if (!raw) return;
          const values = JSON.parse(raw);
          if (!Array.isArray(values) || values.length === 0) return;
          checkboxes.forEach((checkbox) => { checkbox.checked = values.includes(checkbox.value); });
        } catch (e) {}
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

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => { saveState(); applyFilters(); });
      });

      if (resetButton) {
        resetButton.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => { checkbox.checked = true; });
          saveState();
          applyFilters();
        });
      }

      loadState();
      applyFilters();
    })();
  </script>
</body>
</html>