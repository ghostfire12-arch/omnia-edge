<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Security layer: hardened sessions, HTTP security headers, safe
 * redirects and brute-force protection for both login forms.
 *
 * Loaded by config.php BEFORE the session starts, so every page —
 * storefront and admin — gets the same protection automatically.
 */

// ---------------------------------------------------------------------
// HTTPS detection (works behind cPanel / Cloudflare proxies too)
// ---------------------------------------------------------------------
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// ---------------------------------------------------------------------
// SESSION HARDENING — must run before session_start()
// ---------------------------------------------------------------------
function secure_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // reject unknown/forged session ids
    ini_set('session.use_only_cookies', '1');  // never accept ids from the URL
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    session_name('OMNIA_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (defined('BASE_URL_PATH') && BASE_URL_PATH !== '') ? BASE_URL_PATH . '/' : '/',
        'secure'   => is_https(),   // cookie only travels over HTTPS on the live site
        'httponly' => true,         // JavaScript can never read the session cookie
        'samesite' => 'Lax',        // blocks cross-site POSTs carrying the session
    ]);
    session_start();

    // Expire idle sessions after 2 hours and rotate the id every 30 minutes.
    $now = time();
    if (isset($_SESSION['_last_seen']) && $now - (int) $_SESSION['_last_seen'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    if (!isset($_SESSION['_rotated_at']) || $now - (int) $_SESSION['_rotated_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_rotated_at'] = $now;
    }
    $_SESSION['_last_seen'] = $now;
}

// ---------------------------------------------------------------------
// HTTP SECURITY HEADERS
// ---------------------------------------------------------------------
function send_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');

    // Content Security Policy. If you add Paystack inline checkout later,
    // add https://js.paystack.co to script-src/frame-src and
    // https://api.paystack.co to connect-src.
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self'",
        "frame-src 'self' https://www.google.com",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ];
    if (is_https()) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// ---------------------------------------------------------------------
// SAFE REDIRECT TARGETS (prevents open redirects such as //evil.com)
// ---------------------------------------------------------------------
function safe_local_path(?string $path, string $fallback): string
{
    $path = (string) $path;
    if ($path === '' || strlen($path) > 2000) return $fallback;
    if ($path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) return $fallback;
    if (preg_match('/[\x00-\x1F\x7F]/', $path)) return $fallback;          // header injection
    $parts = parse_url($path);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) return $fallback;
    if (BASE_URL_PATH !== '' && !str_starts_with($path, BASE_URL_PATH . '/')) return $fallback;
    return $path;
}

// ---------------------------------------------------------------------
// BRUTE-FORCE PROTECTION
// Stored in the database (not the session) so clearing cookies does
// not reset the counter.
// ---------------------------------------------------------------------
const LOGIN_MAX_PER_ACCOUNT = 5;    // failed attempts per email + scope ...
const LOGIN_MAX_PER_IP      = 20;   // ... or per IP address + scope ...
const LOGIN_WINDOW_SECONDS  = 900;  // ... within 15 minutes

function client_ip(): string
{
    // REMOTE_ADDR is the only value a client cannot spoof.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_attempts_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `scope` VARCHAR(20) NOT NULL,
            `identifier` CHAR(64) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_login_lookup` (`scope`, `identifier`, `attempted_at`),
            KEY `idx_login_ip` (`scope`, `ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $ready = true;
}

/** Returns the number of seconds the caller must wait, or 0 if allowed. */
function login_lockout_seconds(PDO $pdo, string $scope, string $email): int
{
    login_attempts_table($pdo);
    $id = hash('sha256', strtolower(trim($email)));
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);

    $q = $pdo->prepare('SELECT COUNT(*), MIN(attempted_at) FROM login_attempts WHERE scope = :s AND identifier = :i AND attempted_at > :t');
    $q->execute([':s' => $scope, ':i' => $id, ':t' => $since]);
    [$byAccount, $firstA] = $q->fetch(PDO::FETCH_NUM);

    $q = $pdo->prepare('SELECT COUNT(*), MIN(attempted_at) FROM login_attempts WHERE scope = :s AND ip_address = :ip AND attempted_at > :t');
    $q->execute([':s' => $scope, ':ip' => client_ip(), ':t' => $since]);
    [$byIp, $firstI] = $q->fetch(PDO::FETCH_NUM);

    $first = null;
    if ((int) $byAccount >= LOGIN_MAX_PER_ACCOUNT) $first = $firstA;
    if ((int) $byIp >= LOGIN_MAX_PER_IP) $first = $first ? min($first, $firstI) : $firstI;
    if (!$first) return 0;

    return max(1, strtotime($first) + LOGIN_WINDOW_SECONDS - time());
}

function login_record_failure(PDO $pdo, string $scope, string $email): void
{
    login_attempts_table($pdo);
    $pdo->prepare('INSERT INTO login_attempts (scope, identifier, ip_address) VALUES (:s, :i, :ip)')
        ->execute([':s' => $scope, ':i' => hash('sha256', strtolower(trim($email))), ':ip' => client_ip()]);
    // Housekeeping: drop rows older than a day.
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function login_clear_failures(PDO $pdo, string $scope, string $email): void
{
    login_attempts_table($pdo);
    $pdo->prepare('DELETE FROM login_attempts WHERE scope = :s AND identifier = :i')
        ->execute([':s' => $scope, ':i' => hash('sha256', strtolower(trim($email)))]);
}

function lockout_message(int $seconds): string
{
    $minutes = (int) ceil($seconds / 60);
    return 'Too many failed sign-in attempts. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

/**
 * A real bcrypt hash used to keep sign-in timing identical whether or not
 * the email exists, so attackers cannot discover registered emails.
 */
function dummy_password_hash(): string
{
    return '$2y$10$TnKanLGpIQKs1keJTX9L.OnB8DHr42Wu9hhUEftrPcaq25qvtnU8G';
}
