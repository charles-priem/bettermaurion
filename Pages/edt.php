<?php
session_start();
date_default_timezone_set('Europe/Paris');

// ─── Config ────────────────────────────────────────────────────────────────
define('AURION_BASE',        'https://aurion.junia.com');
define('AURION_LOGIN_URL',   AURION_BASE . '/login');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

$dataPath = __DIR__ . '/../data/plannings_promotions.json';

// ─── Semaine ────────────────────────────────────────────────────────────────
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$monday = new DateTime('monday this week');
$monday->modify(($weekOffset >= 0 ? '+' : '') . $weekOffset . ' weeks');
$monday->setTime(0, 0, 0);

$sunday = clone $monday;
$sunday->modify('+6 days')->setTime(23, 59, 59);

$startTs   = $monday->getTimestamp() * 1000;
$endTs     = ($sunday->getTimestamp() + 1) * 1000;
$dateInput = $monday->format('d/m/Y');
$weekParam = $monday->format('W-Y');
$weekLabel = 'Semaine ' . $monday->format('W') . ' · ' . $monday->format('d') . ' au ' . $sunday->format('d M Y');
$todayStr  = (new DateTime())->format('Y-m-d');

$days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$dayDates = [];
for ($i = 0; $i < 6; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $dayDates[$i] = $d->format('Y-m-d');
}

// ─── Aurion fetch ───────────────────────────────────────────────────────────
function aurionFetch(string $user, string $pass, int $start, int $end, string $dateInput, string $weekParam): array {
    $jar = tempnam(sys_get_temp_dir(), 'aurion_');

    $ch = curl_init(AURION_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['username'=>$user,'password'=>$pass,'j_idt28'=>'']),
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!in_array($code, [200, 302])) return ['error' => 'Login échoué'];

    $ch = curl_init(AURION_PLANNING_URL);
    curl_setopt_array($ch, [
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $html, $m1);
    preg_match('/name="form:idInit"[^>]*value="([^"]+)"/', $html, $m2);
    $viewState = $m1[1] ?? '';
    $idInit    = $m2[1] ?? '';
    if (!$viewState) return ['error' => 'ViewState introuvable'];

    $body = http_build_query([
        'javax.faces.partial.ajax'    => 'true',
        'javax.faces.source'          => 'form:j_idt118',
        'javax.faces.partial.execute' => 'form:j_idt118',
        'javax.faces.partial.render'  => 'form:j_idt118',
        'form:j_idt118'               => 'form:j_idt118',
        'form:j_idt118_start'         => $start,
        'form:j_idt118_end'           => $end,
        'form'                        => 'form',
        'form:largeurDivCenter'       => '897',
        'form:idInit'                 => $idInit,
        'form:date_input'             => $dateInput,
        'form:week'                   => $weekParam,
        'form:j_idt118_view'          => 'agendaWeek',
        'form:offsetFuseauNavigateur' => '-7200000',
        'form:onglets_activeIndex'    => '0',
        'form:onglets_scrollState'    => '0',
        'javax.faces.ViewState'       => $viewState,
    ]);

    $ch = curl_init(AURION_PLANNING_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Faces-Request: partial/ajax',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);
    $xml = curl_exec($ch);
    curl_close($ch);
    @unlink($jar);

    preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $xml, $match);
    if (!$match) return ['error' => 'Pas de données'];

    $data = json_decode(trim($match[1]), true);
    return $data['events'] ?? [];
}

// ─── Parse & organise les events ────────────────────────────────────────────
function parseEventDate(?string $v): ?DateTime {
    if (!$v) return null;
    $dt = DateTime::createFromFormat('Y-m-d\\TH:i:sO', $v);
    if ($dt) return $dt;
    $ts = strtotime($v);
    if ($ts === false) return null;
    $d = new DateTime(); $d->setTimestamp($ts); return $d;
}

function parseTitleParts(?string $title): array {
    $parts = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', (string)$title)),
        fn($p) => $p !== ''
    ));
    return [
        'salle'   => $parts[0] ?? '',
        'matiere' => $parts[1] ?? 'Sans titre',
        'typeRaw' => $parts[2] ?? '',
        'prof'    => $parts[3] ?? '',
    ];
}

function typeColor(string $c): string {
    $c = strtoupper($c);
    if (str_contains($c, 'EPREUVE') || in_array($c, ['CC','DS','EXAM','PARTIEL','RATTRAPAGE','INTERRO_SURV'], true)) return 'type-exam';
    if (str_contains($c, 'TP'))     return 'type-tp';
    if (str_contains($c, 'TD'))     return 'type-td';
    if (str_contains($c, 'PROJET')) return 'type-projet';
    return 'type-cours';
}

function typeLabel(string $c): string {
    $c = strtoupper($c);
    if (str_contains($c, 'EPREUVE')) return 'Épreuve';
    if (str_contains($c, 'TP'))      return 'TP';
    if (str_contains($c, 'TD'))      return 'TD';
    if (str_contains($c, 'PROJET'))  return 'Projet';
    return 'Cours';
}

function buildDayMap(array $rawEvents, array $dayDates): array {
    $map = array_fill(0, 6, []);
    foreach ($rawEvents as $ev) {
        $start = parseEventDate($ev['start'] ?? null);
        $end   = parseEventDate($ev['end']   ?? null);
        if (!$start || !$end) continue;
        $idx = array_search($start->format('Y-m-d'), $dayDates, true);
        if ($idx === false) continue;
        $tp = parseTitleParts($ev['title'] ?? '');
        $map[$idx][] = [
            'startTime' => $start->format('H:i'),
            'endTime'   => $end->format('H:i'),
            'salle'     => $tp['salle'],
            'matiere'   => $tp['matiere'],
            'prof'      => $tp['prof'],
            'className' => strtoupper((string)($ev['className'] ?? '')),
        ];
    }
    foreach ($map as &$d) usort($d, fn($a,$b) => strcmp($a['startTime'], $b['startTime']));
    return $map;
}

// ─── Données perso ───────────────────────────────────────────────────────────
$aurionUser = $_SESSION['aurion_login']    ?? '';
$aurionPass = $_SESSION['aurion_password'] ?? '';
$persoError = '';
$persoByDay = array_fill(0, 6, []);

if ($aurionUser && $aurionPass) {
    $result = aurionFetch($aurionUser, $aurionPass, $startTs, $endTs, $dateInput, $weekParam);
    if (isset($result['error'])) {
        $persoError = $result['error'];
    } else {
        $persoByDay = buildDayMap($result, $dayDates);
    }
} else {
    $persoError = 'Non connecté à Aurion';
}

// ─── Données promotions ──────────────────────────────────────────────────────
$raw     = @file_get_contents($dataPath);
$decoded = is_string($raw) ? json_decode($raw, true) : null;
$promoData = (is_array($decoded) && isset($decoded['promotions'])) ? $decoded['promotions'] : [];
$promotions = array_keys($promoData);
sort($promotions);

$selectedPromo = $_GET['promo'] ?? ($promotions[0] ?? '');
if ($selectedPromo !== '' && !in_array($selectedPromo, $promotions, true)) {
    $selectedPromo = $promotions[0] ?? '';
}

$promoRaw   = $selectedPromo !== '' ? ($promoData[$selectedPromo] ?? []) : [];
$promoByDay = buildDayMap($promoRaw, $dayDates);
$promoError = empty($promoData) ? 'Aucune donnée promo chargée.' : '';

// ─── Helpers HTML ────────────────────────────────────────────────────────────
function renderEvent(array $ev): string {
    $color = typeColor($ev['className']);
    $label = typeLabel($ev['className']);
    $out   = '<div class="ev ' . $color . '">';
    $out  .= '<div class="ev-time">' . htmlspecialchars($ev['startTime']) . ' – ' . htmlspecialchars($ev['endTime']) . '<span class="ev-badge">' . htmlspecialchars($label) . '</span></div>';
    $out  .= '<div class="ev-matiere">' . htmlspecialchars($ev['matiere']) . '</div>';
    if ($ev['salle'] !== '' || $ev['prof'] !== '') {
        $out .= '<div class="ev-meta">';
        if ($ev['salle'] !== '') $out .= '<span>📍 ' . htmlspecialchars($ev['salle']) . '</span>';
        if ($ev['prof']  !== '') $out .= '<span>👤 ' . htmlspecialchars($ev['prof'])  . '</span>';
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EDT — Vue comparée</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #0e0f14;
      --surface:  #16181f;
      --border:   #252730;
      --text:     #e8e9ed;
      --muted:    #6b6e7e;
      --accent:   #7c6dfa;
      --accent2:  #f0c55a;

      --cours-bg:  #1a2236; --cours-bd:  #3b6fd4;
      --tp-bg:     #162418; --tp-bd:     #3ab06a;
      --td-bg:     #25200f; --td-bd:     #c9940a;
      --projet-bg: #1e1730; --projet-bd: #8b5cf6;
      --exam-bg:   #271418; --exam-bd:   #e0434f;
    }

    body {
      font-family: 'Roboto', serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── Header ── */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--surface);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .topbar-title {
      font-family: 'Roboto', serif;
      font-size: 20px;
      font-weight: 800;
      letter-spacing: -0.5px;
    }
    .topbar-title span { color: var(--accent); }

    .week-nav {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .week-nav a, .week-nav .today-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 8px;
      background: var(--border);
      color: var(--text);
      text-decoration: none;
      font-size: 16px;
      transition: background .15s;
    }
    .week-nav a:hover, .week-nav .today-btn:hover { background: var(--accent); }
    .week-nav .today-btn { width: auto; padding: 0 12px; font-size: 12px; font-family: 'Roboto', serif; }
    .week-label {
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
      min-width: 220px;
      text-align: center;
    }

    .promo-select {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: var(--muted);
    }
    .promo-select select {
      background: var(--border);
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 6px 10px;
      font-family: 'Roboto', serif;
      font-size: 12px;
      cursor: pointer;
    }

    /* ── Layout principal ── */
    .main { padding: 20px 16px; }

    /* Colonnes côte à côte */
    .col-wrap {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 900px) {
      .col-wrap { grid-template-columns: 1fr; }
    }

    .col-panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
    }
    .col-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 18px;
      border-bottom: 1px solid var(--border);
      font-family: 'Roboto', serif;
      font-weight: 700;
      font-size: 14px;
      letter-spacing: .3px;
    }
    .col-header .dot {
      width: 8px; height: 8px; border-radius: 50%;
      flex-shrink: 0;
    }
    .col-perso  .dot { background: var(--accent); }
    .col-promo  .dot { background: var(--accent2); }

    /* ── Grille des jours ── */
    .days-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
    }
    @media (max-width: 1200px) {
      .days-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 600px) {
      .days-grid { grid-template-columns: repeat(2, 1fr); }
    }

    .day-col {
      border-right: 1px solid var(--border);
      min-height: 180px;
    }
    .day-col:last-child { border-right: none; }

    .day-head {
      padding: 10px 10px 8px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .day-name {
      font-family: 'Roboto', serif;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--muted);
    }
    .day-num {
      font-size: 18px;
      font-weight: 700;
      font-family: 'Roboto', serif;
      color: var(--text);
      line-height: 1;
    }
    .day-col.is-today .day-num {
      background: var(--accent);
      color: #fff;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
    }

    .day-events { padding: 8px; display: flex; flex-direction: column; gap: 6px; }

    /* ── Events ── */
    .ev {
      border-radius: 8px;
      padding: 8px 10px;
      border-left: 3px solid;
      font-size: 11px;
      line-height: 1.4;
      animation: fadeIn .25s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }

    .type-cours  { background: var(--cours-bg);  border-color: var(--cours-bd); }
    .type-tp     { background: var(--tp-bg);     border-color: var(--tp-bd); }
    .type-td     { background: var(--td-bg);     border-color: var(--td-bd); }
    .type-projet { background: var(--projet-bg); border-color: var(--projet-bd); }
    .type-exam   { background: var(--exam-bg);   border-color: var(--exam-bd); }

    .ev-time {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 10px;
      color: var(--muted);
      margin-bottom: 4px;
    }
    .ev-badge {
      font-size: 9px;
      padding: 1px 5px;
      border-radius: 4px;
      background: rgba(255,255,255,.07);
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .ev-matiere {
      font-family: 'Roboto', serif;
      font-size: 12px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 4px;
    }
    .ev-meta {
      display: flex;
      flex-direction: column;
      gap: 2px;
      color: var(--muted);
      font-size: 10px;
    }

    .empty {
      padding: 20px 10px;
      text-align: center;
      color: var(--muted);
      font-size: 11px;
    }

    /* ── Notice d'erreur ── */
    .notice {
      padding: 10px 18px;
      background: rgba(224, 67, 79, .1);
      border-bottom: 1px solid rgba(224, 67, 79, .2);
      color: #e0434f;
      font-size: 12px;
    }

    /* ── Légende ── */
    .legend {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      padding: 14px 24px;
      border-top: 1px solid var(--border);
      background: var(--surface);
      font-size: 11px;
      color: var(--muted);
    }
    .legend-item { display: flex; align-items: center; gap: 6px; }
    .legend-dot  { width: 10px; height: 10px; border-radius: 3px; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="topbar-title">EDT <span>comparé</span></div>

  <div class="week-nav">
    <a href="?promo=<?= urlencode($selectedPromo) ?>&week=<?= $weekOffset - 1 ?>" title="Semaine précédente">‹</a>
    <span class="week-label"><?= htmlspecialchars($weekLabel) ?></span>
    <a href="?promo=<?= urlencode($selectedPromo) ?>&week=<?= $weekOffset + 1 ?>" title="Semaine suivante">›</a>
    <a href="?promo=<?= urlencode($selectedPromo) ?>&week=0" class="today-btn">Auj.</a>
  </div>

  <form method="get" class="promo-select">
    <input type="hidden" name="week" value="<?= $weekOffset ?>">
    <label for="promo">Promo :</label>
    <select id="promo" name="promo" onchange="this.form.submit()">
      <?php foreach ($promotions as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $p === $selectedPromo ? 'selected' : '' ?>>
          <?= htmlspecialchars($p) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<main class="main">
  <div class="col-wrap">

    <!-- ── Colonne Perso ── -->
    <div class="col-panel col-perso">
      <div class="col-header">
        <span class="dot"></span>
        Mon emploi du temps
        <?php if ($aurionUser): ?>
          <span style="color:var(--muted);font-size:11px;margin-left:auto;"><?= htmlspecialchars($aurionUser) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($persoError): ?>
        <div class="notice">⚠ <?= htmlspecialchars($persoError) ?></div>
      <?php endif; ?>
      <div class="days-grid">
        <?php foreach ($days as $i => $dayName):
          $d = clone $monday; $d->modify("+$i days");
          $isToday = $d->format('Y-m-d') === $todayStr;
        ?>
          <div class="day-col <?= $isToday ? 'is-today' : '' ?>">
            <div class="day-head">
              <div>
                <div class="day-name"><?= $dayName ?></div>
                <div class="day-num"><?= $d->format('d') ?></div>
              </div>
            </div>
            <div class="day-events">
              <?php if (empty($persoByDay[$i])): ?>
                <div class="empty">—</div>
              <?php else: ?>
                <?php foreach ($persoByDay[$i] as $ev): echo renderEvent($ev); endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Colonne Promo ── -->
    <div class="col-panel col-promo">
      <div class="col-header">
        <span class="dot"></span>
        <?= $selectedPromo !== '' ? htmlspecialchars($selectedPromo) : 'Promotion' ?>
      </div>
      <?php if ($promoError): ?>
        <div class="notice">⚠ <?= htmlspecialchars($promoError) ?></div>
      <?php endif; ?>
      <div class="days-grid">
        <?php foreach ($days as $i => $dayName):
          $d = clone $monday; $d->modify("+$i days");
          $isToday = $d->format('Y-m-d') === $todayStr;
        ?>
          <div class="day-col <?= $isToday ? 'is-today' : '' ?>">
            <div class="day-head">
              <div>
                <div class="day-name"><?= $dayName ?></div>
                <div class="day-num"><?= $d->format('d') ?></div>
              </div>
            </div>
            <div class="day-events">
              <?php if (empty($promoByDay[$i])): ?>
                <div class="empty">—</div>
              <?php else: ?>
                <?php foreach ($promoByDay[$i] as $ev): echo renderEvent($ev); endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /.col-wrap -->
</main>

<div class="legend">
  <span style="font-family:'Roboto',serif;font-weight:700;color:var(--text);">Légende :</span>
  <span class="legend-item"><span class="legend-dot" style="background:var(--cours-bd)"></span>Cours</span>
  <span class="legend-item"><span class="legend-dot" style="background:var(--tp-bd)"></span>TP</span>
  <span class="legend-item"><span class="legend-dot" style="background:var(--td-bd)"></span>TD</span>
  <span class="legend-item"><span class="legend-dot" style="background:var(--projet-bd)"></span>Projet</span>
  <span class="legend-item"><span class="legend-dot" style="background:var(--exam-bd)"></span>Examen</span>
</div>

</body>
</html>