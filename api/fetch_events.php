<?php
session_start();
date_default_timezone_set('Europe/Paris');
header('Content-Type: application/json');

// --- Config Aurion ---
define('AURION_BASE', 'https://aurion.junia.com');
define('AURION_LOGIN_URL', AURION_BASE . '/login');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

// --- Get parameters ---
$planningIdInit = isset($_GET['planningIdInit']) ? trim((string)$_GET['planningIdInit']) : '';
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

// --- Credentials from session ---
$aurionUser = $_SESSION['aurion_login'] ?? '';
$aurionPass = $_SESSION['aurion_password'] ?? '';

if (!$planningIdInit) {
    echo json_encode(['error' => 'Missing planningIdInit']);
    exit;
}

if (!$aurionUser || !$aurionPass) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// --- Calculate date range ---
$monday = new DateTime();
$monday->modify('monday this week');
$monday->modify("$weekOffset weeks");
$sunday = clone $monday;
$sunday->modify('+6 days');

$startTs = $monday->getTimestamp() * 1000;
$endTs   = ($sunday->getTimestamp() + 86400) * 1000;
$dateInput = $monday->format('d/m/Y');
$weekParam = $monday->format('W-Y');

function aurionFetch(string $user, string $pass, string $idInit, int $start, int $end, string $dateInput, string $weekParam): array {
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
        'form:offsetFuseauNavigateur' => '-120', // GMT+2 in minutes
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

    // Parse XML → JSON
    preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $xmlResponse, $match);
    if (!$match) {
        return ['error' => 'No events data'];
    }

    $json = trim($match[1]);
    $data = json_decode($json, true);

    return $data['events'] ?? [];
}

$events = aurionFetch($aurionUser, $aurionPass, $planningIdInit, $startTs, $endTs, $dateInput, $weekParam);

if (isset($events['error'])) {
    echo json_encode(['error' => $events['error']]);
} else {
    echo json_encode(['success' => true, 'events' => $events]);
}

