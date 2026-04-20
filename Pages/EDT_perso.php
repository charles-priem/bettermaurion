<?php
session_start();
date_default_timezone_set('Europe/Paris');

// --- Config Aurion ---
define('AURION_BASE', 'https://aurion.junia.com');
define('AURION_LOGIN_URL', AURION_BASE . '/login');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

// --- Semaine courante ---
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$monday = new DateTime();
$monday->modify('monday this week');
$monday->modify("$weekOffset weeks");
$sunday = clone $monday;
$sunday->modify('+6 days');

$startTs = $monday->getTimestamp() * 1000;
$endTs   = ($sunday->getTimestamp() + 86400) * 1000;
$dateInput = $monday->format('d/m/Y');
$weekParam = $monday->format('W-Y');

$weekLabel = 'Semaine ' . $monday->format('W') . ' — ' . $monday->format('d') . ' au ' . $sunday->format('d M Y');

// --- Credentials depuis session ---
$aurionUser = $_SESSION['aurion_login'] ?? '';
$aurionPass = $_SESSION['aurion_password'] ?? '';

$events = [];
$error  = '';

// --- Traitement de la connexion ---
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
        $_SESSION['aurion_login'] = $username;
        $_SESSION['aurion_password'] = $password;
        $aurionUser = $username;
        $aurionPass = $password;
    }
}

function aurionFetch(string $user, string $pass, int $start, int $end, string $dateInput, string $weekParam): array {
    $cookieJar = tempnam(sys_get_temp_dir(), 'aurion_');

    // === LOGIN ===
    $ch = curl_init(AURION_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'username' => $user,
            'password' => $pass,
            'j_idt28'  => '',
        ]),
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);
    $loginResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 && $httpCode !== 302) {
        return ['error' => 'Login failed'];
    }

    // === GET Planning page pour ViewState ===
    $ch = curl_init(AURION_PLANNING_URL);
    curl_setopt_array($ch, [
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    // Parse ViewState
    preg_match('/name="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $html, $m);
    $viewState = $m[1] ?? '';

    preg_match('/name="form:idInit"[^>]*value="([^"]+)"/', $html, $m2);
    $idInit = $m2[1] ?? '';

    if (!$viewState) {
        return ['error' => 'Cannot extract ViewState'];
    }

    // === POST AJAX pour événements ===
    $body = http_build_query([
        'javax.faces.partial.ajax'    => 'true',
        'javax.faces.source'          => 'form:j_idt118',
        'javax.faces.partial.execute' => 'form:j_idt118',
        'javax.faces.partial.render'  => 'form:j_idt118',
        'form:j_idt118'               => 'form:j_idt118',
        'form:j_idt118_start'         => $start,
        'form:j_idt118_end'           => $end,
        'form'                        => 'form',
        'form:largeurDivCenter'       => '1200',
        'form:idInit'                 => $idInit,
        'form:date_input'             => $dateInput,
        'form:week'                   => $weekParam,
        'form:j_idt118_view'          => 'agendaWeek',
        'form:offsetFuseauNavigateur' => '-3600000',
        'form:onglets_activeIndex'    => '0',
        'form:onglets_scrollState'    => '0',
        'javax.faces.ViewState'       => $viewState,
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
        ],
    ]);
    $xmlResponse = curl_exec($ch);
    curl_close($ch);
    @unlink($cookieJar);

    // DEBUG: Voir la réponse brute
    error_log('Aurion raw response (first 500 chars): ' . substr($xmlResponse, 0, 500));

    // Parse XML → JSON
    preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $xmlResponse, $match);
    if (!$match) {
        // DEBUG: Chercher les alternatives
        error_log('No CDATA found. Looking for alternatives...');
        // Essayer de trouver du JSON directement
        preg_match('/\{"events":\[.*?\]\}/', $xmlResponse, $match2);
        if ($match2) {
            error_log('Found raw JSON');
            $match = $match2;
        } else {
            error_log('No JSON found either. Response: ' . substr($xmlResponse, 0, 1000));
            return ['error' => 'No events data'];
        }
    }

    $json = trim($match[1]);
    $data = json_decode($json, true);

    return $data['events'] ?? [];
}

if ($aurionUser && $aurionPass) {
    $result = aurionFetch($aurionUser, $aurionPass, $startTs, $endTs, $dateInput, $weekParam);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $events = is_array($result) ? $result : [];
        // DEBUG
        error_log('EDT_perso: Found ' . count($events) . ' events');
        if (count($events) > 0) {
            error_log('First event: ' . json_encode($events[0]));
        }
    }
} else {
    // Pas encore connecté
}

// --- Organiser par jour ---
$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$dayDates = [];
for ($i = 0; $i < 6; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $dayDates[$i] = $d->format('Y-m-d');
}

// DEBUG: Voir la structure des événements
if (count($events) > 0) {
    error_log('Event structure: ' . json_encode($events[0], JSON_PRETTY_PRINT));
} else {
    error_log('No events found to process');
}

$eventsByDay = array_fill(0, 6, []);
foreach ($events as $ev) {
    // Enrichir l'événement avec tous les champs nécessaires
    $ev = enrichEventRecord($ev);
    
    $start = $ev['start'] ?? '';
    $end = $ev['end'] ?? '';
    
    if (!$start || !$end) {
        continue;
    }
    
    $date = substr($start, 0, 10);
    foreach ($dayDates as $idx => $dd) {
        if ($dd === $date) {
            preg_match('/T(\d{2}:\d{2})/', $start, $ms);
            preg_match('/T(\d{2}:\d{2})/', $end, $me);
            $ev['startTime'] = $ms[1] ?? '';
            $ev['endTime'] = $me[1] ?? '';

            $eventsByDay[$idx][] = $ev;
            break;
        }
    }
}

// Trier les événements par heure de début
foreach ($eventsByDay as &$dayEvs) {
    usort($dayEvs, fn($a, $b) => strcmp($a['startTime'], $b['startTime']));
}
unset($dayEvs);

function eventTypeKeys(string $className, string $typeRaw = ''): array {
    $text = strtoupper(trim("$className $typeRaw"));
    $keys = [];
    
    if (preg_match('/(EPREUVE|CC|DS|EXAM|PARTIEL|RATTRAPAGE|INTERRO_SURV)/', $text)) {
        $keys[] = 'exam';
    }
    if (strpos($text, 'TP') !== false) {
        $keys[] = 'tp';
    }
    if (strpos($text, 'TD') !== false) {
        $keys[] = 'td';
    }
    if (strpos($text, 'PROJET') !== false) {
        $keys[] = 'projet';
    }
    
    if (empty($keys)) {
        $keys[] = 'cours';
    }
    
    return $keys;
}

function eventTypeColor(string $typeKey): string {
    $colors = [
        'cours' => 'type-cours',
        'tp' => 'type-tp',
        'td' => 'type-td',
        'projet' => 'type-projet',
        'exam' => 'type-exam',
    ];
    return $colors[strtolower($typeKey)] ?? 'type-cours';
}

function eventTypeLabel(string $typeKey): string {
    $labels = [
        'cours' => 'Cours',
        'tp' => 'TP',
        'td' => 'TD',
        'projet' => 'Projet',
        'exam' => 'Épreuve',
    ];
    return $labels[strtolower($typeKey)] ?? 'Cours';
}

function enrichEventRecord(array $event): array {
    $titleParts = parseTitleParts($event['title'] ?? '');
    $className = $event['className'] ?? '';
    $typeKeys = eventTypeKeys($className, $titleParts['typeRaw']);
    $primaryType = $typeKeys[0] ?? 'cours';
    
    $event['salle'] = $titleParts['salle'];
    $event['matiere'] = $titleParts['matiere'];
    $event['typeRaw'] = $titleParts['typeRaw'];
    $event['prof'] = $titleParts['prof'];
    $event['typeKeys'] = $typeKeys;
    $event['typeClass'] = eventTypeColor($primaryType);
    $event['typeLabel'] = implode(' / ', array_map('eventTypeLabel', $typeKeys));
    
    return $event;
}

function parseTitleParts(?string $title): array {
    $title = is_string($title) ? $title : '';
    $parts = array_map('trim', preg_split('/\r\n|\r|\n/', $title));
    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
    return [
        'salle' => $parts[0] ?? '',
        'matiere' => $parts[1] ?? 'Sans titre',
        'typeRaw' => $parts[2] ?? '',
        'prof' => $parts[3] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mon emploi du temps - ProjetBetterMoroomia</title>
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; }
    .wrap { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
    .login-form { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input { padding: 8px; width: 100%; max-width: 300px; border: 1px solid #ccc; border-radius: 4px; }
    .form-group button { padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .form-group button:hover { background: #0052a3; }
    .notice { background: #fff3cd; border: 1px solid #ffe69c; padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; }
    .notice.error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; background: #fff; border: 1px solid #ddd; }
    th, td { border: 1px solid #ddd; vertical-align: top; padding: 8px; }
    th { background: #fafafa; text-align: center; }
    .ev { padding: 8px; border-left: 4px solid #999; border-radius: 6px; margin-bottom: 7px; background: #f7f7f7; font-size: 13px; }
    .ev-time { font-size: 12px; font-weight: 700; }
    .ev-matiere { font-weight: 700; margin: 3px 0; }
    .ev-meta { color: #555; font-size: 12px; }
    .type-cours { background: #dbeafe; border-color: #60a5fa; }
    .type-tp { background: #dcfce7; border-color: #4ade80; }
    .type-td { background: #fef3c7; border-color: #f59e0b; }
    .type-projet { background: #ede9fe; border-color: #8b5cf6; }
    .type-exam { background: #fee2e2; border-color: #ef4444; }
  </style>
</head>
<body>
  <header class="header">
    <nav class="nav">
      <ul class="nav_list">
        <li class="nav_item _dropdown">
          <button class="dropbtn">Emploi du temps</button>
          <div class="dropdown-content">
            <a href="EDT_perso.php">Mon emploi du temps</a>
            <a href="EDT_promotions.php">Emploi du temps par promotions</a>
          </div>
        </li>
      </ul>
    </nav>
  </header>

  <main class="wrap">
    <h1>Mon emploi du temps personnel</h1>

    <?php if ($error !== ''): ?>
      <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$aurionUser || !$aurionPass): ?>
      <div class="login-form">
        <h2>Connexion Aurion requise</h2>
        <form method="post">
          <div class="form-group">
            <label for="username">Identifiant Aurion:</label>
            <input type="text" id="username" name="username" placeholder="prenom.nom">
          </div>
          <div class="form-group">
            <label for="password">Mot de passe:</label>
            <input type="password" id="password" name="password" placeholder="mot de passe">
          </div>
          <div class="form-group">
            <button type="submit" name="login" value="1">Se connecter</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="notice" style="background: #d4edda; border-color: #c3e6cb; color: #155724;">
         Connecté à Aurion - Affichage de la semaine <?= htmlspecialchars($weekLabel) ?>
      </div>

      <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px; padding: 10px 0;">
        <div style="display: flex; gap: 8px; align-items: center;">
          <a href="?week=<?= $weekOffset - 1 ?>" style="padding: 8px 12px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px;">← Précédente</a>
          <span style="font-weight: bold; min-width: 180px; text-align: center;"><?= htmlspecialchars($weekLabel) ?></span>
          <a href="?week=<?= $weekOffset + 1 ?>" style="padding: 8px 12px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px;">Suivante →</a>
        </div>
        <a href="?week=0" style="padding: 8px 12px; background: #666; color: white; text-decoration: none; border-radius: 4px;">Aujourd'hui</a>
      </div>

      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
          <thead>
            <tr>
              <th style="width: 50px; background: #fafafa; text-align: center; border: 1px solid #ddd;"></th>
              <?php foreach ($days as $i => $dayName):
                $d = clone $monday;
                $d->modify("+$i days");
              ?>
                <th style="background: #fafafa; text-align: center; border: 1px solid #ddd; padding: 8px;">
                  <strong><?= htmlspecialchars($dayName) ?></strong><br>
                  <small style="color: #0066cc;">
                    <?php if ($d->format('Y-m-d') === date('Y-m-d')): ?>
                      <span style="background: #0066cc; color: white; padding: 2px 6px; border-radius: 3px;">W18</span> <?= $d->format('d/m') ?>
                    <?php else: ?>
                      <?= $d->format('d/m') ?>
                    <?php endif; ?>
                  </small>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php 
              $hours = range(7, 19);
              foreach ($hours as $hour):
                $hourStr = str_pad($hour, 2, '0', STR_PAD_LEFT);
            ?>
              <tr>
                <td style="background: #fafafa; text-align: center; border: 1px solid #ddd; font-weight: bold; font-size: 12px; width: 50px;">
                  <?= $hourStr ?>
                </td>
                <?php foreach ($eventsByDay as $dayIdx => $dayEvents): ?>
                  <td style="border: 1px solid #ddd; vertical-align: top; height: 60px; padding: 2px; background: #fafafa; position: relative;">
                    <?php 
                      $eventsThisHour = [];
                      foreach ($dayEvents as $ev) {
                        $evStart = substr($ev['start'], 11, 2);
                        $evStartHour = (int)$evStart;
                        if ($evStartHour === $hour) {
                          $eventsThisHour[] = $ev;
                        }
                      }
                      
                      if (!empty($eventsThisHour)):
                        foreach ($eventsThisHour as $ev):
                    ?>
                      <div class="ev <?= htmlspecialchars($ev['typeClass'] ?? 'type-cours') ?>" style="font-size: 11px; padding: 6px; margin: 1px 0; min-height: 40px; display: flex; flex-direction: column; justify-content: center;">
                        <div class="ev-time" style="font-weight: bold; font-size: 11px;"><?= htmlspecialchars($ev['startTime'] ?? '') ?></div>
                        <div class="ev-matiere" style="font-weight: bold; font-size: 12px; line-height: 1.2;"><?= htmlspecialchars($ev['matiere'] ?? 'Sans titre') ?></div>
                        <div class="ev-meta" style="color: #555; font-size: 10px; line-height: 1.2;">
                          <?php if (!empty($ev['prof'])): ?><strong><?= htmlspecialchars($ev['prof']) ?></strong><br><?php endif; ?>
                          <?php if (!empty($ev['salle'])): ?><?= htmlspecialchars($ev['salle']) ?><?php endif; ?>
                        </div>
                      </div>
                    <?php 
                        endforeach;
                      endif;
                    ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>