<?php
session_start();
header('Content-Type: text/html'); // We expect HTML back from this
date_default_timezone_set('Europe/Paris');

// --- Config Aurion ---
define('AURION_BASE', 'https://aurion.junia.com');
define('AURION_PLANNING_URL', AURION_BASE . '/faces/Planning.xhtml');

// --- Get parameters ---
// These are the expected parameters from the client-side click
$eventId = $_POST['eventId'] ?? '';
$viewState = $_POST['viewState'] ?? '';

if (!$eventId || !$viewState) {
    http_response_code(400);
    echo 'Missing eventId or viewState';
    exit;
}

// --- Use existing cookie from session ---
$cookieJarPath = session_id() ? sys_get_temp_dir() . '/aurion_cookie_' . session_id() : '';
if (!file_exists($cookieJarPath)) {
    http_response_code(401);
    echo 'Not authenticated. Cookie jar not found.';
    exit;
}

// === POST AJAX to get event details ===
$postData = http_build_query([
    'javax.faces.partial.ajax' => 'true',
    'javax.faces.source' => 'form:j_idt118', // The main schedule component
    'javax.faces.partial.execute' => '@all',
    'javax.faces.partial.render' => 'form:j_idt232', // The right-side details panel
    'javax.faces.ViewState' => $viewState,
    'form' => 'form',
    'form:j_idt118_selectedEventId' => $eventId, // The critical ID of the clicked event
]);

$ch = curl_init(AURION_PLANNING_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_COOKIEFILE => $cookieJarPath,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Faces-Request: partial/ajax',
        'X-Requested-With: XMLHttpRequest',
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
    ],
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
]);

$xmlResult = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$xmlResult) {
    http_response_code(502);
    echo 'Failed to fetch event details from Aurion.';
    exit;
}

// --- Parse XML and extract the HTML content ---
$doc = new DOMDocument();
if (!$doc->loadXML($xmlResult)) {
    http_response_code(500);
    echo 'Invalid XML response from Aurion.';
    exit;
}

$xpath = new DOMXPath($doc);
$xpath->registerNamespace('p', 'http://www.w3.org/2000/xmlns/');

// Find the <update> element for the details panel
$cdataNode = $xpath->query("//p:update[@id='form:j_idt232']/text()")->item(0);

if ($cdataNode) {
    // Output the raw HTML content from the CDATA section
    echo $cdataNode->nodeValue;
} else {
    // If not found, maybe there was an error or session expiry
    http_response_code(404);
    echo 'Could not find event details panel in the response.';
}
