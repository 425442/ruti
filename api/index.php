<?php
/**
 * Ruti – Server-API (Benutzerkonten + Datenspeicherung)
 *
 * Eine Datei, keine Abhängigkeiten. Läuft mit PHP 7.4+ und MySQL/MariaDB
 * (Hostinger) oder SQLite (lokaler Test).
 *
 * Aufruf:  POST api/?a=<aktion>   Body: JSON   Header: X-Ruti-Token: <token>
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// ---------- Konfiguration ----------
$CONFIG_PATHS = [
    getenv('RUTI_CONFIG') ?: '',
    dirname(__DIR__, 3) . '/ruti-config.php',   // außerhalb von public_html (empfohlen)
    dirname(__DIR__, 2) . '/ruti-config.php',
    __DIR__ . '/config.php',
];
$cfg = null;
foreach ($CONFIG_PATHS as $p) {
    if ($p && is_file($p)) { $cfg = require $p; break; }
}
if (!is_array($cfg)) fail(503, 'not_configured', 'Der Server ist noch nicht eingerichtet (Konfigurationsdatei fehlt).');
$cfg += [
    'db_user' => null, 'db_pass' => null,
    'app_url' => '', 'mail_from' => '', 'mail_from_name' => 'Ruti',
    'mail_log' => '', 'session_days' => 180, 'max_data_bytes' => 5 * 1024 * 1024,
];

// ---------- Hilfsfunktionen ----------
function fail(int $code, string $err, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $err, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
function ok(array $data = []): void {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function now(): int { return time(); }
function tokenHash(string $t): string { return hash('sha256', $t); }
function newToken(): string { return bin2hex(random_bytes(32)); }
function clientIp(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 64); }
function normEmail($e): string { return strtolower(trim((string)$e)); }
function validEmail(string $e): bool { return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }
function checkPassword($p): void {
    $p = (string)$p;
    if (mb_strlen($p) < 10) fail(400, 'weak_password', 'Das Passwort muss mindestens 10 Zeichen lang sein.');
    if (mb_strlen($p) > 200) fail(400, 'weak_password', 'Das Passwort ist zu lang (höchstens 200 Zeichen).');
    $classes = (int)preg_match('/[a-z]/', $p) + (int)preg_match('/[A-Z]/', $p) + (int)preg_match('/\d/', $p) + (int)preg_match('/[^A-Za-z0-9]/', $p);
    if ($classes < 2) fail(400, 'weak_password', 'Bitte mische mindestens zwei Zeichenarten (Buchstaben, Ziffern, Sonderzeichen).');
}

// ---------- Datenbank ----------
try {
    $db = new PDO($cfg['db_dsn'], $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    fail(503, 'db_unavailable', 'Die Datenbank ist nicht erreichbar. Bitte später erneut versuchen.');
}
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

function ensureSchema(PDO $db, string $driver): void {
    $marker = sys_get_temp_dir() . '/ruti_schema_v1_' . md5(__DIR__ . $driver);
    if (is_file($marker)) return;
    $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $engine = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $text = $driver === 'sqlite' ? 'TEXT' : 'LONGTEXT';
    $db->exec("CREATE TABLE IF NOT EXISTS ruti_users (
        id $id, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL DEFAULT '',
        pass_hash VARCHAR(255) NOT NULL, verified INT NOT NULL DEFAULT 0,
        created_at INT NOT NULL, pass_changed_at INT NOT NULL)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS ruti_sessions (
        token_hash CHAR(64) NOT NULL PRIMARY KEY, user_id INT NOT NULL,
        created_at INT NOT NULL, last_seen INT NOT NULL, expires_at INT NOT NULL, agent VARCHAR(190) NOT NULL DEFAULT '')$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS ruti_tokens (
        token_hash CHAR(64) NOT NULL PRIMARY KEY, user_id INT NOT NULL, kind VARCHAR(16) NOT NULL,
        expires_at INT NOT NULL, used INT NOT NULL DEFAULT 0)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS ruti_data (
        user_id INT NOT NULL PRIMARY KEY, data $text NOT NULL, version INT NOT NULL DEFAULT 0, updated_at INT NOT NULL)$engine");
    $db->exec("CREATE TABLE IF NOT EXISTS ruti_attempts (
        id $id, k VARCHAR(220) NOT NULL, ts INT NOT NULL)$engine");
    @touch($marker);
}
ensureSchema($db, $driver);

// Einfache Begrenzung gegen Passwort-Raten und Mail-Flut
function tooMany(PDO $db, string $key, int $limit, int $window): bool {
    $db->prepare('DELETE FROM ruti_attempts WHERE ts < ?')->execute([now() - 86400]);
    $st = $db->prepare('SELECT COUNT(*) FROM ruti_attempts WHERE k = ? AND ts > ?');
    $st->execute([$key, now() - $window]);
    return (int)$st->fetchColumn() >= $limit;
}
function note(PDO $db, string $key): void {
    $db->prepare('INSERT INTO ruti_attempts (k, ts) VALUES (?, ?)')->execute([$key, now()]);
}
function clearNotes(PDO $db, string $key): void {
    $db->prepare('DELETE FROM ruti_attempts WHERE k = ?')->execute([$key]);
}

function createSession(PDO $db, array $cfg, int $uid): string {
    $t = newToken();
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190);
    $db->prepare('INSERT INTO ruti_sessions (token_hash, user_id, created_at, last_seen, expires_at, agent) VALUES (?,?,?,?,?,?)')
       ->execute([tokenHash($t), $uid, now(), now(), now() + 86400 * (int)$cfg['session_days'], $agent]);
    return $t;
}
function currentUser(PDO $db, array $cfg): array {
    $t = (string)($_SERVER['HTTP_X_RUTI_TOKEN'] ?? '');
    if (strlen($t) !== 64) fail(401, 'not_logged_in', 'Bitte melde dich an.');
    $st = $db->prepare('SELECT s.user_id, s.expires_at, s.last_seen, u.email, u.name, u.verified FROM ruti_sessions s JOIN ruti_users u ON u.id = s.user_id WHERE s.token_hash = ?');
    $st->execute([tokenHash($t)]);
    $row = $st->fetch();
    if (!$row || (int)$row['expires_at'] < now()) fail(401, 'session_expired', 'Deine Anmeldung ist abgelaufen. Bitte melde dich erneut an.');
    if (now() - (int)$row['last_seen'] > 3600) { // gleitende Laufzeit
        $db->prepare('UPDATE ruti_sessions SET last_seen = ?, expires_at = ? WHERE token_hash = ?')
           ->execute([now(), now() + 86400 * (int)$cfg['session_days'], tokenHash($t)]);
    }
    return ['id' => (int)$row['user_id'], 'email' => $row['email'], 'name' => $row['name'], 'verified' => (bool)$row['verified'], 'token' => $t];
}
function publicUser(array $u): array { return ['id' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'verified' => (bool)$u['verified']]; }

function sendMail(array $cfg, string $to, string $subject, string $text): bool {
    if ($cfg['mail_log']) { // Testmodus: Mail in Datei schreiben
        return (bool)file_put_contents($cfg['mail_log'], json_encode(['to' => $to, 'subject' => $subject, 'text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    }
    $from = $cfg['mail_from'];
    if (!$from) return false;
    $headers = [
        'From: ' . '=?UTF-8?B?' . base64_encode($cfg['mail_from_name']) . '?= <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, implode("\r\n", $headers), '-f' . $from);
}
function mailToken(PDO $db, int $uid, string $kind, int $ttl): string {
    $db->prepare('UPDATE ruti_tokens SET used = 1 WHERE user_id = ? AND kind = ? AND used = 0')->execute([$uid, $kind]);
    $t = newToken();
    $db->prepare('INSERT INTO ruti_tokens (token_hash, user_id, kind, expires_at) VALUES (?,?,?,?)')->execute([tokenHash($t), $uid, $kind, now() + $ttl]);
    return $t;
}
function useToken(PDO $db, string $t, string $kind): int {
    $st = $db->prepare('SELECT user_id, expires_at, used FROM ruti_tokens WHERE token_hash = ? AND kind = ?');
    $st->execute([tokenHash($t), $kind]);
    $row = $st->fetch();
    if (!$row || (int)$row['used'] === 1 || (int)$row['expires_at'] < now()) return 0;
    $db->prepare('UPDATE ruti_tokens SET used = 1 WHERE token_hash = ?')->execute([tokenHash($t)]);
    return (int)$row['user_id'];
}
function appLink(array $cfg, string $param, string $token): string {
    $base = $cfg['app_url'] ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/app/');
    return $base . (strpos($base, '?') === false ? '?' : '&') . $param . '=' . $token;
}
function sendVerify(PDO $db, array $cfg, int $uid, string $email): bool {
    $t = mailToken($db, $uid, 'verify', 7 * 86400);
    $link = appLink($cfg, 'verify', $t);
    return sendMail($cfg, $email, 'Ruti: Bitte bestätige deine E-Mail-Adresse',
        "Hallo,\n\nbitte bestätige deine E-Mail-Adresse für Ruti mit diesem Link:\n\n$link\n\nDer Link ist 7 Tage gültig. Wenn du dich nicht bei Ruti registriert hast, kannst du diese E-Mail ignorieren.\n\nRuti");
}

// ---------- Aktionen ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') fail(405, 'method', 'Nur POST erlaubt.');
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$a = (string)($_GET['a'] ?? '');
$ip = clientIp();

switch ($a) {

case 'ping':
    ok(['server' => 'ruti', 'time' => now()]);

case 'register': {
    $email = normEmail($in['email'] ?? '');
    $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 80));
    if (!validEmail($email)) fail(400, 'bad_email', 'Bitte gib eine gültige E-Mail-Adresse ein.');
    checkPassword($in['password'] ?? '');
    if (tooMany($db, "reg:$ip", 10, 3600)) fail(429, 'rate_limited', 'Zu viele Registrierungen von diesem Anschluss. Bitte später erneut versuchen.');
    note($db, "reg:$ip");
    $st = $db->prepare('SELECT id FROM ruti_users WHERE email = ?'); $st->execute([$email]);
    if ($st->fetch()) fail(409, 'email_taken', 'Für diese E-Mail-Adresse gibt es schon ein Konto. Melde dich an oder nutze „Passwort vergessen“.');
    $db->prepare('INSERT INTO ruti_users (email, name, pass_hash, created_at, pass_changed_at) VALUES (?,?,?,?,?)')
       ->execute([$email, $name, password_hash((string)$in['password'], PASSWORD_DEFAULT), now(), now()]);
    $uid = (int)$db->lastInsertId();
    $mailed = sendVerify($db, $cfg, $uid, $email);
    $token = createSession($db, $cfg, $uid);
    ok(['token' => $token, 'user' => ['id' => $uid, 'email' => $email, 'name' => $name, 'verified' => false], 'mailed' => $mailed]);
}

case 'login': {
    $email = normEmail($in['email'] ?? '');
    $pw = (string)($in['password'] ?? '');
    $key = 'login:' . $email;
    if (tooMany($db, $key, 8, 900) || tooMany($db, "loginip:$ip", 30, 900))
        fail(429, 'rate_limited', 'Zu viele Fehlversuche. Bitte warte 15 Minuten oder setze dein Passwort zurück.');
    $st = $db->prepare('SELECT * FROM ruti_users WHERE email = ?'); $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify($pw, $u['pass_hash'])) {
        note($db, $key); note($db, "loginip:$ip");
        fail(401, 'bad_credentials', 'E-Mail-Adresse oder Passwort stimmen nicht.');
    }
    clearNotes($db, $key);
    if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT))
        $db->prepare('UPDATE ruti_users SET pass_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
    ok(['token' => createSession($db, $cfg, (int)$u['id']), 'user' => publicUser($u)]);
}

case 'me': {
    $u = currentUser($db, $cfg);
    ok(['user' => publicUser($u)]);
}

case 'logout': {
    $u = currentUser($db, $cfg);
    if (!empty($in['all'])) $db->prepare('DELETE FROM ruti_sessions WHERE user_id = ?')->execute([$u['id']]);
    else $db->prepare('DELETE FROM ruti_sessions WHERE token_hash = ?')->execute([tokenHash($u['token'])]);
    ok();
}

case 'forgot': {
    $email = normEmail($in['email'] ?? '');
    // Antwort immer gleich, damit niemand herausfinden kann, welche Adressen registriert sind
    $answer = 'Falls ein Konto zu dieser Adresse existiert, haben wir dir einen Link zum Zurücksetzen geschickt. Er ist eine Stunde gültig.';
    if (!validEmail($email)) fail(400, 'bad_email', 'Bitte gib eine gültige E-Mail-Adresse ein.');
    if (tooMany($db, "forgot:$email", 3, 3600) || tooMany($db, "forgotip:$ip", 10, 3600)) ok(['message' => $answer]);
    note($db, "forgot:$email"); note($db, "forgotip:$ip");
    $st = $db->prepare('SELECT id FROM ruti_users WHERE email = ?'); $st->execute([$email]);
    if ($u = $st->fetch()) {
        $t = mailToken($db, (int)$u['id'], 'reset', 3600);
        $link = appLink($cfg, 'reset', $t);
        sendMail($cfg, $email, 'Ruti: Passwort zurücksetzen',
            "Hallo,\n\nfür dein Ruti-Konto wurde ein neues Passwort angefordert. Über diesen Link legst du es fest:\n\n$link\n\nDer Link ist eine Stunde gültig und funktioniert nur einmal. Wenn du das nicht angefordert hast, ignoriere diese E-Mail – dein Passwort bleibt unverändert.\n\nRuti");
    }
    ok(['message' => $answer]);
}

case 'reset': {
    $t = (string)($in['token'] ?? '');
    checkPassword($in['password'] ?? '');
    $uid = strlen($t) === 64 ? useToken($db, $t, 'reset') : 0;
    if (!$uid) fail(400, 'bad_token', 'Der Link ist abgelaufen oder wurde schon benutzt. Fordere bitte einen neuen an.');
    $db->prepare('UPDATE ruti_users SET pass_hash = ?, pass_changed_at = ?, verified = 1 WHERE id = ?')
       ->execute([password_hash((string)$in['password'], PASSWORD_DEFAULT), now(), $uid]);
    $db->prepare('DELETE FROM ruti_sessions WHERE user_id = ?')->execute([$uid]); // überall abmelden
    $st = $db->prepare('SELECT * FROM ruti_users WHERE id = ?'); $st->execute([$uid]);
    $u = $st->fetch();
    clearNotes($db, 'login:' . $u['email']);
    ok(['token' => createSession($db, $cfg, $uid), 'user' => publicUser($u)]);
}

case 'verify': {
    $t = (string)($in['token'] ?? '');
    $uid = strlen($t) === 64 ? useToken($db, $t, 'verify') : 0;
    if (!$uid) fail(400, 'bad_token', 'Der Bestätigungslink ist abgelaufen oder wurde schon benutzt.');
    $db->prepare('UPDATE ruti_users SET verified = 1 WHERE id = ?')->execute([$uid]);
    ok(['message' => 'Deine E-Mail-Adresse ist bestätigt.']);
}

case 'resend_verify': {
    $u = currentUser($db, $cfg);
    if ($u['verified']) ok(['message' => 'Deine E-Mail-Adresse ist bereits bestätigt.']);
    if (tooMany($db, 'verify:' . $u['id'], 3, 3600)) fail(429, 'rate_limited', 'Bitte warte etwas, bevor du eine weitere E-Mail anforderst.');
    note($db, 'verify:' . $u['id']);
    ok(['mailed' => sendVerify($db, $cfg, $u['id'], $u['email'])]);
}

case 'change_password': {
    $u = currentUser($db, $cfg);
    $st = $db->prepare('SELECT pass_hash FROM ruti_users WHERE id = ?'); $st->execute([$u['id']]);
    if (!password_verify((string)($in['current'] ?? ''), (string)$st->fetchColumn())) fail(401, 'bad_credentials', 'Das aktuelle Passwort stimmt nicht.');
    checkPassword($in['password'] ?? '');
    $db->prepare('UPDATE ruti_users SET pass_hash = ?, pass_changed_at = ? WHERE id = ?')
       ->execute([password_hash((string)$in['password'], PASSWORD_DEFAULT), now(), $u['id']]);
    // Andere Geräte abmelden, dieses bleibt angemeldet
    $db->prepare('DELETE FROM ruti_sessions WHERE user_id = ? AND token_hash <> ?')->execute([$u['id'], tokenHash($u['token'])]);
    ok(['message' => 'Passwort geändert. Andere Geräte wurden abgemeldet.']);
}

case 'update_profile': {
    $u = currentUser($db, $cfg);
    $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 80));
    $db->prepare('UPDATE ruti_users SET name = ? WHERE id = ?')->execute([$name, $u['id']]);
    $u['name'] = $name;
    ok(['user' => publicUser($u)]);
}

case 'delete_account': {
    $u = currentUser($db, $cfg);
    $st = $db->prepare('SELECT pass_hash FROM ruti_users WHERE id = ?'); $st->execute([$u['id']]);
    if (!password_verify((string)($in['password'] ?? ''), (string)$st->fetchColumn())) fail(401, 'bad_credentials', 'Das Passwort stimmt nicht.');
    foreach (['ruti_sessions', 'ruti_tokens', 'ruti_data'] as $tbl) $db->prepare("DELETE FROM $tbl WHERE user_id = ?")->execute([$u['id']]);
    $db->prepare('DELETE FROM ruti_users WHERE id = ?')->execute([$u['id']]);
    ok(['message' => 'Dein Konto und alle Daten wurden gelöscht.']);
}

case 'data_get': {
    $u = currentUser($db, $cfg);
    $st = $db->prepare('SELECT data, version, updated_at FROM ruti_data WHERE user_id = ?'); $st->execute([$u['id']]);
    $row = $st->fetch();
    if (!$row) ok(['data' => null, 'version' => 0, 'updated_at' => 0]);
    if (isset($in['since']) && (int)$in['since'] === (int)$row['version']) ok(['unchanged' => true, 'version' => (int)$row['version']]);
    ok(['data' => json_decode($row['data'], true), 'version' => (int)$row['version'], 'updated_at' => (int)$row['updated_at']]);
}

case 'data_put': {
    $u = currentUser($db, $cfg);
    if (!isset($in['data']) || !is_array($in['data'])) fail(400, 'bad_data', 'Keine Daten übermittelt.');
    $json = json_encode($in['data'], JSON_UNESCAPED_UNICODE);
    if (strlen($json) > (int)$cfg['max_data_bytes']) fail(413, 'too_large', 'Die Datenmenge ist zu groß.');
    $base = (int)($in['base'] ?? -1);
    $db->beginTransaction();
    $st = $db->prepare('SELECT version FROM ruti_data WHERE user_id = ?'); $st->execute([$u['id']]);
    $cur = $st->fetchColumn();
    $cur = $cur === false ? null : (int)$cur;
    if (empty($in['force']) && $cur !== null && $base !== $cur) {
        $db->rollBack();
        $st = $db->prepare('SELECT data, version, updated_at FROM ruti_data WHERE user_id = ?'); $st->execute([$u['id']]);
        $row = $st->fetch();
        fail(409, 'conflict', 'Auf einem anderen Gerät wurde inzwischen gespeichert.', ['data' => json_decode($row['data'], true), 'version' => (int)$row['version']]);
    }
    $next = ($cur ?? 0) + 1;
    if ($cur === null) $db->prepare('INSERT INTO ruti_data (user_id, data, version, updated_at) VALUES (?,?,?,?)')->execute([$u['id'], $json, $next, now()]);
    else $db->prepare('UPDATE ruti_data SET data = ?, version = ?, updated_at = ? WHERE user_id = ?')->execute([$json, $next, now(), $u['id']]);
    $db->commit();
    ok(['version' => $next, 'updated_at' => now()]);
}

default:
    fail(404, 'unknown_action', 'Unbekannte Aktion.');
}
