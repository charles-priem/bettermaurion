<?php
session_start();
date_default_timezone_set('Europe/Paris');

if (empty($_SESSION['aurion_user']) || empty($_SESSION['aurion_pass'])) {
    header('Location: ../Pages/connexion.php');
    exit;
}

$user = $_SESSION['aurion_user'];
$pass = $_SESSION['aurion_pass'];

define('AURION_BASE',         'https://aurion.junia.com');
define('AURION_LOGIN_URL',    AURION_BASE . '/login');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$monday = new DateTime();
$monday->modify('monday this week');
$monday->modify("$weekOffset weeks");
$sunday = clone $monday;
$sunday->modify('+6 days');

$startTs   = $monday->getTimestamp() * 1000;
$endTs     = ($sunday->getTimestamp() + 86400) * 1000;
$dateInput = $monday->format('d/m/Y');
$weekParam = $monday->format('W-Y');
$weekLabel = 'Semaine ' . $monday->format('W') . ' — '
           . $monday->format('d') . ' au ' . $sunday->format('d M Y');

function getTimezoneOffset(): int {
    $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
    return -($now->getOffset() * 1000);
}

function aurionFetch(string $user, string $pass, int $start, int $end, string $dateInput, string $weekParam): array {
    $cookieJar = tempnam(sys_get_temp_dir(), 'aurion_');

    $ch = curl_init(AURION_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['username'=>$user,'password'=>$pass,'j_idt28'=>'']),
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);
    $loginHtml = curl_exec($ch);
    curl_close($ch);

    if (str_contains($loginHtml, 'id="username"')) {
        return ['__error' => 'Identifiants Aurion incorrects'];
    }

    $ch = curl_init(AURION_BASE . '/faces/MainMenuPage.xhtml');
    curl_setopt_array($ch, [
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);
    $homeHtml = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $homeHtml, $m);
    $viewState = $m[1] ?? '';

    $ch = curl_init(AURION_BASE . '/faces/MainMenuPage.xhtml');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'form'                  => 'form',
            'form:sidebar'          => 'form:sidebar',
            'form:sidebar_menuid'   => '0',
            'javax.faces.ViewState' => $viewState,
        ]),
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);
    $planningHtml = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $planningHtml, $m);
    $viewState2 = $m[1] ?? $viewState;

    preg_match('/name="form:idInit"[^>]*value="([^"]+)"/', $planningHtml, $m2);
    $idInit = $m2[1] ?? '';

    if (!$idInit) {
        preg_match('/webscolaapp\.Planning_(\d+)/', $planningHtml, $m3);
        $idInit = $m3 ? 'webscolaapp.Planning_' . $m3[1] : '';
    }

    if (!$viewState2 || !$idInit) {
        @unlink($cookieJar);
        return ['__error' => 'Navigation Planning échouée'];
    }

    $body = http_build_query([
        'javax.faces.partial.ajax'    => 'true',
        'javax.faces.source'          => 'form:j_idt118',
        'javax.faces.partial.execute' => 'form:j_idt118',
        'javax.faces.partial.render'  => 'form:j_idt118',
        'form:j_idt118'               => 'form:j_idt118',
        'form:j_idt118_start'         => $start,
        'form:j_idt118_end'           => $end,
        'form'                        => 'form',
        'form:largeurDivCenter'       => '1519',
        'form:idInit'                 => $idInit,
        'form:date_input'             => $dateInput,
        'form:week'                   => $weekParam,
        'form:j_idt118_view'          => 'agendaWeek',
        'form:offsetFuseauNavigateur' => getTimezoneOffset(),
        'form:onglets_activeIndex'    => '0',
        'form:onglets_scrollState'    => '0',
        'javax.faces.ViewState'       => $viewState2,
    ]);

    $ch = curl_init(AURION_PLANNING_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Faces-Request: partial/ajax',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . AURION_PLANNING_URL,
        ],
    ]);
    $xml = curl_exec($ch);
    curl_close($ch);
    @unlink($cookieJar);

    preg_match_all('/<!\[CDATA\[(.*?)\]\]>/s', $xml, $matches);
    foreach ($matches[1] as $cdata) {
        $data = json_decode(trim($cdata), true);
        if (isset($data['events'])) return $data['events'];
    }

    return ['__error' => 'Aucun événement — ' . substr($xml, 0, 200)];
}

function parseTitleParts(?string $title): array {
    $title = is_string($title) ? $title : '';
    $parts = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $title)),
        fn($p) => $p !== '' && !preg_match('/^Horaire\s+TT\s*:/i', $p)
    ));
    return [
        'salle'   => $parts[0] ?? '',
        'matiere' => $parts[1] ?? ($parts[0] ?? 'Sans titre'),
        'typeRaw' => $parts[2] ?? '',
        'prof'    => $parts[3] ?? '',
    ];
}

function eventTypeKeys(string $className, string $typeRaw = ''): array {
    $text = strtoupper("$className $typeRaw");
    if (preg_match('/(EPREUVE|PARTIEL|RATTRAPAGE|INTERRO|EXAM|DS|CC)/', $text)) return ['exam'];
    $types = [];
    if (str_contains($text, 'PROJET')) $types[] = 'projet';
    if (str_contains($text, 'TP'))     $types[] = 'tp';
    if (str_contains($text, 'TD'))     $types[] = 'td';
    return $types ?: ['cours'];
}

function eventTypeLabel(string $key): string {
    return match($key) {
        'tp'     => 'TP',
        'td'     => 'TD',
        'projet' => 'Projet',
        'exam'   => 'Examen',
        default  => 'Cours',
    };
}

function enrichEvent(array $ev): array {
    $p    = parseTitleParts($ev['title'] ?? '');
    $keys = eventTypeKeys($ev['className'] ?? '', $p['typeRaw']);
    $ev['salle']     = $p['salle'];
    $ev['matiere']   = $p['matiere'];
    $ev['prof']      = $p['prof'];
    $ev['typeKeys']  = $keys;
    $ev['typeClass'] = 'type-' . $keys[0];
    $ev['typeLabel'] = implode(' / ', array_map('eventTypeLabel', $keys));
    return $ev;
}

function clampInt(int $v, int $min, int $max): int {
    return max($min, min($max, $v));
}

// === Fetch + organisation ===
$error  = '';
$raw    = aurionFetch($user, $pass, $startTs, $endTs, $dateInput, $weekParam);

if (isset($raw['__error'])) { $error = $raw['__error']; $raw = []; }

$days     = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$dayDates = [];
for ($i = 0; $i < 6; $i++) {
    $d = clone $monday; $d->modify("+$i days");
    $dayDates[$i] = $d->format('Y-m-d');
}

$weekStart = clone $monday; $weekStart->setTime(0,0,0);
$weekEnd   = clone $sunday; $weekEnd->setTime(23,59,59);

$dayStartMin    = 8  * 60;
$dayEndMin      = 20 * 60;
$minutePx       = 1.2;
$timelineHeight = (int)(($dayEndMin - $dayStartMin) * $minutePx);
$lunchTop       = (int)((12*60 - $dayStartMin) * $minutePx);
$lunchHeight    = (int)((90) * $minutePx);

$eventTypeCounts = ['cours'=>0,'tp'=>0,'td'=>0,'projet'=>0,'exam'=>0];
$eventsByDay     = array_fill(0, 6, []);
$todayStr        = (new DateTime())->format('Y-m-d');

foreach ($raw as $ev) {
    if (empty($ev['start']) || empty($ev['end']) || empty($ev['title'])) continue;
    $ev = enrichEvent($ev);

    $start = new DateTime($ev['start']);
    $end   = new DateTime($ev['end']);

    if ($start < $weekStart || $start > $weekEnd) continue;

    $dayIdx = array_search($start->format('Y-m-d'), $dayDates, true);
    if ($dayIdx === false) continue;

    $startMin = (int)$start->format('H') * 60 + (int)$start->format('i');
    $endMin   = (int)$end->format('H')   * 60 + (int)$end->format('i');

    foreach ($ev['typeKeys'] as $t) if (isset($eventTypeCounts[$t])) $eventTypeCounts[$t]++;

    $eventsByDay[$dayIdx][] = array_merge($ev, [
        'startTime' => $start->format('H:i'),
        'endTime'   => $end->format('H:i'),
        'startMin'  => $startMin,
        'endMin'    => $endMin,
    ]);
}

foreach ($eventsByDay as &$d) {
    usort($d, fn($a,$b) => strcmp($a['startTime'], $b['startTime']));
}
unset($d);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon emploi du temps</title>
<link rel="stylesheet" href="../css/style.css">
<style>
  body { font-family:'Roboto',serif; background:#f5f0f0; color:#1a1a1a; }
  .wrap { max-width:1280px; margin:24px auto; padding:0 16px; }

  .toolbar { display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; }
  .toolbar h1 { margin:0; font-size:24px; }
  .week-nav { display:flex; gap:8px; align-items:center; }
  .week-label { min-width:260px; text-align:center; font-weight:600; }
  .week-nav a { height:36px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 12px; text-decoration:none; color:#111; display:inline-flex; align-items:center; }

  .type-filters { display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:12px 14px; background:#fff; border:1px solid #ddd; border-radius:12px; margin-bottom:12px; }
  .type-filters strong { margin-right:4px; }
  .type-filters label { display:inline-flex; gap:6px; align-items:center; font-size:13px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:999px; padding:6px 10px; cursor:pointer; }
  .type-filters input { accent-color:#111; }
  .type-filters button { height:32px; border:1px solid #ccc; border-radius:8px; background:#fff; padding:0 10px; cursor:pointer; }

  .timeline { background:#fff; border:1px solid #ddd; border-radius:10px; overflow:hidden; }
  .timeline-header { display:grid; grid-template-columns:90px repeat(6,1fr); }
  .timeline-head { padding:10px 6px; text-align:center; background:#fafafa; border-right:1px solid #ddd; }
  .timeline-head:last-child { border-right:0; }
  .timeline-head.is-today { background:#111; color:#fff; }

  .timeline-body { display:grid; grid-template-columns:90px repeat(6,1fr); }
  .hours-col { position:relative; background:#fafafa; border-right:1px solid #ddd; }
  .hour-label { position:absolute; left:8px; transform:translateY(-50%); font-size:12px; color:#666; }
  .day-col { position:relative; border-right:1px solid #ddd; overflow:hidden; }
  .day-col:last-child { border-right:0; }
  .hour-line { position:absolute; left:0; right:0; height:1px; background:#f1f5f9; }
  .lunch-band {
    position:absolute; left:0; right:0; pointer-events:none;
    background:repeating-linear-gradient(45deg,rgba(0,0,0,0.04),rgba(0,0,0,0.04) 6px,transparent 6px,transparent 12px);
    border-top:2px solid rgba(0,0,0,0.12); border-bottom:2px solid rgba(0,0,0,0.12);
  }

  .ev { padding:8px; border-left:4px solid #999; border-radius:6px; background:#f7f7f7; font-size:13px; box-sizing:border-box; }
  .ev.is-hidden { display:none; }
  .ev-abs { position:absolute; left:6px; right:6px; overflow:hidden; }
  .ev-time { font-size:12px; font-weight:700; }
  .ev-badge { display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,0.75); font-size:11px; font-weight:700; }
  .ev-matiere { font-weight:700; margin:3px 0; }
  .ev-meta { color:#555; font-size:12px; }

  .type-cours  { background:#dbeafe; border-color:#60a5fa; }
  .type-tp     { background:#dcfce7; border-color:#4ade80; }
  .type-td     { background:#fef3c7; border-color:#f59e0b; }
  .type-projet { background:#ede9fe; border-color:#8b5cf6; }
  .type-exam   { background:#fee2e2; border-color:#ef4444; }

  .notice-error { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; }

  @media(max-width:800px) {
    .timeline-header, .timeline-body { grid-template-columns:60px repeat(6,1fr); }
  }
</style>
</head>
<body>
<?php require_once 'header.php'; ?>
<main class="wrap">

  <div class="toolbar">
    <h1>Mon emploi du temps</h1>
    <div class="week-nav">
      <a href="?week=<?= $weekOffset-1 ?>">← Précédente</a>
      <span class="week-label"><?= htmlspecialchars($weekLabel) ?></span>
      <a href="?week=<?= $weekOffset+1 ?>">Suivante →</a>
      <?php if ($weekOffset !== 0): ?><a href="?week=0">Aujourd'hui</a><?php endif; ?>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="notice-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="type-filters">
    <strong>Afficher</strong>
    <label><input type="checkbox" data-type-toggle value="cours"  checked> Cours   <span>(<?= $eventTypeCounts['cours']  ?>)</span></label>
    <label><input type="checkbox" data-type-toggle value="tp"     checked> TP      <span>(<?= $eventTypeCounts['tp']     ?>)</span></label>
    <label><input type="checkbox" data-type-toggle value="td"     checked> TD      <span>(<?= $eventTypeCounts['td']     ?>)</span></label>
    <label><input type="checkbox" data-type-toggle value="projet" checked> Projet  <span>(<?= $eventTypeCounts['projet'] ?>)</span></label>
    <label><input type="checkbox" data-type-toggle value="exam"   checked> Examen  <span>(<?= $eventTypeCounts['exam']   ?>)</span></label>
    <button type="button" id="reset-types">Tout afficher</button>
  </div>

  <div class="timeline">
    <div class="timeline-header">
      <div class="timeline-head">Heure</div>
      <?php foreach ($days as $i => $dayName):
        $d = clone $monday; $d->modify("+$i days");
        $isToday = $d->format('Y-m-d') === $todayStr;
      ?>
        <div class="timeline-head <?= $isToday ? 'is-today' : '' ?>">
          <?= $dayName ?><br><strong><?= $d->format('d/m') ?></strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="timeline-body">
      <div class="hours-col" style="height:<?= $timelineHeight ?>px;">
        <?php for ($h=8;$h<=20;$h++): ?>
          <div class="hour-label" style="top:<?= ($h*60-$dayStartMin)*$minutePx ?>px;">
            <?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00
          </div>
        <?php endfor; ?>
      </div>

      <?php foreach ($eventsByDay as $dayEvents): ?>
        <div class="day-col" style="height:<?= $timelineHeight ?>px;">
          <div class="lunch-band" style="top:<?= $lunchTop ?>px;height:<?= $lunchHeight ?>px;"></div>
          <?php for ($h=8;$h<=20;$h++): ?>
            <div class="hour-line" style="top:<?= ($h*60-$dayStartMin)*$minutePx ?>px;"></div>
          <?php endfor; ?>

          <?php foreach ($dayEvents as $ev):
            $sMin = clampInt($ev['startMin'], $dayStartMin, $dayEndMin);
            $eMin = clampInt($ev['endMin'],   $dayStartMin, $dayEndMin);
            if ($eMin <= $sMin) continue;
            $top    = (int)(($sMin - $dayStartMin) * $minutePx);
            $height = max(30, (int)(($eMin - $sMin) * $minutePx));
          ?>
            <div class="ev ev-abs <?= htmlspecialchars($ev['typeClass']) ?>"
                 data-types="<?= htmlspecialchars(implode(' ', $ev['typeKeys'])) ?>"
                 style="top:<?= $top ?>px;height:<?= $height ?>px;">
              <div class="ev-time">
                <?= $ev['startTime'] ?> – <?= $ev['endTime'] ?>
                <span class="ev-badge"><?= htmlspecialchars($ev['typeLabel']) ?></span>
              </div>
              <div class="ev-matiere"><?= htmlspecialchars($ev['matiere']) ?></div>
              <div class="ev-meta">
                <?php if ($ev['salle']): ?> <?= htmlspecialchars($ev['salle']) ?><br><?php endif; ?>
                <?php if ($ev['prof']):  ?> <?= htmlspecialchars($ev['prof'])  ?><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>

<script>
(function() {
  const storageKey = 'edt_perso_visible_types';
  const checkboxes = Array.from(document.querySelectorAll('[data-type-toggle]'));
  const resetBtn   = document.getElementById('reset-types');

  function getSelected() { return checkboxes.filter(c=>c.checked).map(c=>c.value); }

  function save() {
    try { localStorage.setItem(storageKey, JSON.stringify(getSelected())); } catch(e) {}
  }

  function load() {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return;
      const vals = JSON.parse(raw);
      if (!Array.isArray(vals) || !vals.length) return;
      checkboxes.forEach(c => c.checked = vals.includes(c.value));
    } catch(e) {}
  }

  function apply() {
    const sel = new Set(getSelected());
    document.querySelectorAll('.ev[data-types]').forEach(el => {
      const types = (el.dataset.types||'').split(/\s+/).filter(Boolean);
      el.classList.toggle('is-hidden', !types.some(t => sel.has(t)));
    });
  }

  checkboxes.forEach(c => c.addEventListener('change', () => { save(); apply(); }));
  if (resetBtn) resetBtn.addEventListener('click', () => {
    checkboxes.forEach(c => c.checked = true); save(); apply();
  });

  load(); apply();
})();
</script>
</body>
</html>