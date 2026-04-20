<?php
session_start();
date_default_timezone_set('Europe/Paris');

// Simuler une connexion
$_SESSION['aurion_login'] = 'charles.priem';
$_SESSION['aurion_password'] = 'Priem1234';

define('AURION_BASE', 'https://aurion.junia.com');
define('AURION_LOGIN_URL', AURION_BASE . '/login');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

$user = $_SESSION['aurion_login'];
$pass = $_SESSION['aurion_password'];

$monday = new DateTime();
$monday->modify('monday this week');
$sunday = clone $monday;
$sunday->modify('+6 days');

$startTs = $monday->getTimestamp() * 1000;
$endTs   = ($sunday->getTimestamp() + 86400) * 1000;
$dateInput = $monday->format('d/m/Y');
$weekParam = $monday->format('W-Y');

echo "<h1>Test Aurion Response</h1>\n";
echo "<p>User: $user</p>\n";
echo "<p>Date: $dateInput</p>\n";
echo "<p>Week: $weekParam</p>\n";
echo "<hr>\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'aurion_');

// === LOGIN ===
echo "<h2>Step 1: Login</h2>\n";
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
echo "Login HTTP Code: $httpCode<br>\n";
if ($httpCode !== 200 && $httpCode !== 302) {
    echo "<span style='color:red'>❌ Login failed</span><br>\n";
    exit;
}

// === GET Planning page pour ViewState ===
echo "<h2>Step 2: Get Planning Page</h2>\n";
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
echo "Got HTML: " . strlen($html) . " bytes<br>\n";

// Parse ViewState
preg_match('/name="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $html, $m);
$viewState = $m[1] ?? '';
echo "ViewState found: " . (strlen($viewState) > 0 ? 'YES (' . strlen($viewState) . ' chars)' : 'NO') . "<br>\n";

preg_match('/name="form:idInit"[^>]*value="([^"]+)"/', $html, $m2);
$idInit = $m2[1] ?? '';
echo "idInit found: " . (strlen($idInit) > 0 ? "YES: $idInit" : 'NO') . "<br>\n";

if (!$viewState) {
    echo "<span style='color:red'>❌ Cannot extract ViewState</span><br>\n";
    exit;
}

// === POST AJAX pour événements ===
echo "<h2>Step 3: Request Events</h2>\n";
$body = http_build_query([
    'javax.faces.partial.ajax'    => 'true',
    'javax.faces.source'          => 'form:j_idt118',
    'javax.faces.partial.execute' => 'form:j_idt118',
    'javax.faces.partial.render'  => 'form:j_idt118',
    'form:j_idt118'               => 'form:j_idt118',
    'form:j_idt118_start'         => $startTs,
    'form:j_idt118_end'           => $endTs,
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($cookieJar);

echo "Response HTTP Code: $httpCode<br>\n";
echo "Response size: " . strlen($xmlResponse) . " bytes<br>\n";

// Parse XML → JSON
echo "<h2>Step 4: Parse Response</h2>\n";
echo "<pre style='background:#f0f0f0; padding:10px; max-height:400px; overflow-y:auto;'>\n";
echo "Raw response (first 1000 chars):\n";
echo htmlspecialchars(substr($xmlResponse, 0, 1000));
echo "\n</pre>\n";

preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $xmlResponse, $match);
if ($match) {
    echo "<span style='color:green'>✅ Found CDATA</span><br>\n";
    $json = trim($match[1]);
    $data = json_decode($json, true);
    
    echo "<h2>Parsed Data</h2>\n";
    echo "<pre>\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n</pre>\n";
    
    if (isset($data['events'])) {
        echo "<span style='color:green'>✅ Found " . count($data['events']) . " events</span><br>\n";
        if (count($data['events']) > 0) {
            echo "<h3>First Event:</h3>\n";
            echo "<pre>\n";
            echo json_encode($data['events'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "\n</pre>\n";
        }
    }
} else {
    echo "<span style='color:red'>❌ CDATA not found</span><br>\n";
    echo "<p>Looking for alternatives...</p>\n";
    preg_match('/\{"events":\[.*?\]\}/', $xmlResponse, $match2);
    if ($match2) {
        echo "<span style='color:green'>✅ Found raw JSON</span><br>\n";
    } else {
        echo "<span style='color:red'>❌ No JSON found</span><br>\n";
    }
}
?>
