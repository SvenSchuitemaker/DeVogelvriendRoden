<?php
declare(strict_types=1);
// One-time setup. Create admin/install-code.php by FTP, containing:
// <?php return 'your-long-random-installation-code';
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
if (!$local && !$secure) { http_response_code(403); exit('Gebruik HTTPS.'); }
$config = __DIR__ . '/config.local.php';
if (is_file($config)) { header('Location: index.php'); exit; }
$permit = __DIR__ . '/install-code.php';
if (!is_file($permit)) {
    http_response_code(403);
    exit('Installatie vergrendeld. Maak eerst via FTP admin/install-code.php met een geheime installatiecode. Zie SPONSORBEHEER.md.');
}
$secret = require $permit;
if (!is_string($secret) || strlen($secret) < 24) { http_response_code(403); exit('Ongeldige installatiecode.'); }
session_name('vogelvriend_install');
session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Strict','path'=>'/admin']);
session_start();
$_SESSION['setup_csrf'] ??= bin2hex(random_bytes(32));
$error = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    $inputCode = (string)($_POST['setup_code'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!hash_equals($_SESSION['setup_csrf'], $csrf)) $error='Sessie verlopen. Vernieuw de pagina.';
    elseif (!hash_equals($secret, $inputCode)) $error='Verkeerde installatiecode.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $username)) $error='Gebruikersnaam: 3 tot 64 letters, cijfers, punten of streepjes.';
    elseif (strlen($password) < 12 || strlen($password) > 256) $error='Gebruik een wachtwoord van minimaal 12 tekens.';
    else {
        $content = "<?php\n// Automatically generated; do not commit or share this file.\nreturn " .
            var_export(['user'=>$username,'password_hash'=>password_hash($password, PASSWORD_DEFAULT)], true) . ";\n";
        $handle = @fopen($config, 'x');
        if ($handle === false) $error='Kan config.local.php niet aanmaken. Controleer schrijfrechten van admin/.';
        else {
            try {
                if (!flock($handle, LOCK_EX) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
                    throw new RuntimeException('Opslag mislukt');
                }
                @chmod($config, 0600);
                $done = true;
                @unlink($permit);
            } catch (Throwable $e) {
                @unlink($config);
                $error = 'Instellen mislukt. Controleer de schrijfrechten.';
            } finally { fclose($handle); }
        }
    }
}
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin installeren – De Vogelvriend Roden</title>
<style>body{font:16px system-ui;background:#f2f6f9;color:#18344a;margin:0;padding:40px 16px}main{max-width:470px;margin:auto;background:white;border:1px solid #d9e5ea;border-radius:16px;padding:28px;box-shadow:0 8px 24px #142e4012}h1{font-size:1.5rem}label{display:block;font-weight:600;margin-top:16px}input{box-sizing:border-box;width:100%;padding:12px;margin-top:6px;border:1px solid #abbcc7;border-radius:8px}button{margin-top:24px;background:#167b72;color:white;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer}.error{color:#9c2525}</style></head><body><main>
<h1>Sponsorbeheer installeren</h1>
<?php if ($done): ?>
<p>Het beheerdersaccount is ingesteld. Verwijder via FTP ook <code>admin/install-code.php</code> als het bestand nog bestaat.</p>
<p><a href="index.php">Naar admin-login</a></p>
<?php else: ?>
<p>Vul de geheime installatiecode in die je via FTP hebt ingesteld. Kies daarna je beheerdersaccount.</p>
<?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['setup_csrf']) ?>">
<label>Installatiecode<input name="setup_code" type="password" autocomplete="off" required></label>
<label>Beheerdersgebruikersnaam<input name="username" value="admin" maxlength="64" required></label>
<label>Beheerderswachtwoord<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>
<button type="submit">Account veilig aanmaken</button></form>
<?php endif; ?></main></body></html>
