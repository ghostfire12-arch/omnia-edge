<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Authentication helpers — customer accounts and admin accounts.
 *
 * Include after functions.php.
 */

require_once __DIR__ . '/functions.php';

// =====================================================================
// CUSTOMER AUTH
// =====================================================================

function customer_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
}

function customer_logout(): void
{
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['cart_key']);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

function is_customer_logged_in(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);

    return (bool) $stmt->fetchColumn();
}

function current_customer_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Redirect to login if the customer is not authenticated.
 * $returnTo is stored so login.php can bounce back after success.
 */
function require_customer_login(string $returnTo = ''): void
{
    if (!is_customer_logged_in()) {
        customer_logout();
        if ($returnTo !== '') {
            $_SESSION['redirect_after_login'] = $returnTo;
        }
        redirect(asset_url('login.php'));
    }
}

// =====================================================================
// ADMIN AUTH
// =====================================================================

function admin_login(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int) $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_default_password']);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function current_admin_id(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Call at the top of every protected admin page.
 */
function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        redirect(asset_url('admin/login.php'));
    }
}
