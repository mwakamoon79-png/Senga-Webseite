<?php
/**
 * Kontaktformular-Versand für senga-webdesign.de
 * Läuft auf normalem PHP-Shared-Hosting, ohne Abhängigkeiten.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const RECIPIENT_EMAIL = 'sengampumuliza@gmail.com';
const SITE_NAME       = 'Senga – Websites, die wirken';
const RATE_LIMIT_DIR  = __DIR__ . '/data/ratelimit';
const RATE_LIMIT_WINDOW_SECONDS = 600; // 10 Minuten
const RATE_LIMIT_MAX_REQUESTS   = 4;   // max. 4 Anfragen pro Fenster und IP

function respond(bool $success, string $message = ''): void
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/** Entfernt Zeilenumbrüche/Steuerzeichen, damit niemand zusätzliche
 *  Mail-Header (z. B. Bcc:) einschleusen kann. */
function cleanHeaderValue(string $value): string
{
    $value = str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $value);
    return trim($value);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Ungültige Anfrage.');
}

// ---- Ratenbegrenzung: verhindert wiederholtes Abschicken in kurzer Zeit ----
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!is_dir(RATE_LIMIT_DIR)) {
    @mkdir(RATE_LIMIT_DIR, 0700, true);
}

$rateFile = RATE_LIMIT_DIR . '/' . hash('sha256', $ip) . '.json';
$now = time();
$timestamps = [];

if (is_file($rateFile)) {
    $stored = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($stored)) {
        $timestamps = $stored;
    }
}

// nur Zeitstempel innerhalb des aktuellen Zeitfensters behalten
$timestamps = array_values(array_filter(
    $timestamps,
    static fn ($t) => is_int($t) && ($now - $t) < RATE_LIMIT_WINDOW_SECONDS
));

if (count($timestamps) >= RATE_LIMIT_MAX_REQUESTS) {
    http_response_code(429);
    respond(false, 'Du hast das Formular schon mehrmals abgeschickt. Bitte warte ein paar Minuten und versuche es erneut.');
}

$timestamps[] = $now;
@file_put_contents($rateFile, json_encode($timestamps), LOCK_EX);

// ---- Honeypot: unsichtbares Feld, das nur Bots ausfüllen ----
$honeypot = $_POST['website'] ?? '';
if ($honeypot !== '') {
    // Für Bots sieht es nach Erfolg aus, es wird aber nichts verschickt.
    respond(true);
}

// ---- Eingaben einlesen und prüfen ----
$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Bitte fülle alle Felder aus.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Bitte gib eine gültige E-Mail-Adresse ein.');
}

if (mb_strlen($name) > 120 || mb_strlen($email) > 190 || mb_strlen($message) > 5000) {
    respond(false, 'Eine der Eingaben ist zu lang.');
}

// Für die Mail-Kopfzeilen (Absender/Betreff) niemals Rohwerte verwenden
$safeName  = cleanHeaderValue($name);
$safeEmail = cleanHeaderValue($email);
$subject   = '=?UTF-8?B?' . base64_encode('Neue Nachricht über ' . SITE_NAME . ' von ' . $safeName) . '?=';

$body  = "Neue Nachricht über das Kontaktformular:\n\n";
$body .= "Name: {$safeName}\n";
$body .= "E-Mail: {$safeEmail}\n\n";
$body .= "Nachricht:\n{$message}\n";

$headers   = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . SITE_NAME . ' <no-reply@' . cleanHeaderValue((string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) . '>';
$headers[] = 'Reply-To: ' . $safeEmail;

$sent = @mail(RECIPIENT_EMAIL, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    respond(true);
}

respond(false, 'Die Nachricht konnte nicht verschickt werden. Bitte versuche es später erneut oder schreib direkt an ' . RECIPIENT_EMAIL . '.');
