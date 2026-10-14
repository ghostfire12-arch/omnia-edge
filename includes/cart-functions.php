<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Shopping cart logic.
 *
 * Guest shoppers are tracked by a random cart key stored in the session. Logged-in customers
 * are tracked by user_id. When a guest logs in, their session cart
 * is merged into their account cart (see merge_guest_cart_into_user).
 *
 * Include after auth.php.
 */

require_once __DIR__ . '/auth.php';

/**
 * Random key that identifies a guest's cart. Kept inside the session so
 * the real session id is never written to the database, and so the cart
 * survives session-id rotation.
 */
function guest_cart_key(): string
{
    if (empty($_SESSION['cart_key']) || !preg_match('/^[a-f0-9]{64}$/', (string) $_SESSION['cart_key'])) {
        $_SESSION['cart_key'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cart_key'];
}

/**
 * Returns the identifying WHERE clause fragment + params for the
 * current shopper (logged-in user or guest session).
 */
function cart_owner_clause(): array
{
    if (is_customer_logged_in()) {
        return ['user_id = :owner', ['owner' => current_customer_id()]];
    }
    return ['session_id = :owner', ['owner' => guest_cart_key()]];
}

/**
 * Add a product to the cart, or increase quantity if already present.
 * Returns [success(bool), message(string)].
 */
function cart_add(PDO $pdo, int $productId, int $quantity = 1): array
{
    $quantity = max(1, $quantity);

    $stmt = $pdo->prepare("SELECT id, name, stock_quantity, status FROM products WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return [false, 'Product not found.'];
    }

    [$whereClause, $params] = cart_owner_clause();
    $params['pid'] = $productId;

    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE $whereClause AND product_id = :pid");
    $stmt->execute($params);
    $existing = $stmt->fetch();

    $desiredQty = $quantity + ($existing['quantity'] ?? 0);
    if ($desiredQty > $product['stock_quantity']) {
        $desiredQty = (int) $product['stock_quantity'];
    }
    if ($desiredQty < 1) {
        return [false, 'This product is currently out of stock.'];
    }

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE cart SET quantity = :qty WHERE id = :id');
        $stmt->execute([':qty' => $desiredQty, ':id' => $existing['id']]);
    } else {
        $userId = is_customer_logged_in() ? current_customer_id() : null;
        $sessionId = is_customer_logged_in() ? null : guest_cart_key();
        $stmt = $pdo->prepare(
            'INSERT INTO cart (session_id, user_id, product_id, quantity) VALUES (:sid, :uid, :pid, :qty)'
        );
        $stmt->execute([
            ':sid' => $sessionId,
            ':uid' => $userId,
            ':pid' => $productId,
            ':qty' => $desiredQty,
        ]);
    }

    return [true, 'Added to cart.'];
}

function cart_update_quantity(PDO $pdo, int $cartId, int $quantity): array
{
    [$whereClause, $params] = cart_owner_clause();
    $params['id'] = $cartId;

    $stmt = $pdo->prepare("SELECT c.id, c.product_id, p.stock_quantity FROM cart c
                            JOIN products p ON p.id = c.product_id
                            WHERE c.id = :id AND $whereClause");
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row) {
        return [false, 'Cart item not found.'];
    }

    if ($quantity < 1) {
        return cart_remove($pdo, $cartId);
    }

    $quantity = min($quantity, (int) $row['stock_quantity']);

    $stmt = $pdo->prepare('UPDATE cart SET quantity = :qty WHERE id = :id');
    $stmt->execute([':qty' => $quantity, ':id' => $cartId]);

    return [true, 'Cart updated.'];
}

function cart_remove(PDO $pdo, int $cartId): array
{
    [$whereClause, $params] = cart_owner_clause();
    $params['id'] = $cartId;

    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :id AND $whereClause");
    $stmt->execute($params);

    return [true, 'Item removed from cart.'];
}

function cart_clear(PDO $pdo): void
{
    [$whereClause, $params] = cart_owner_clause();
    $stmt = $pdo->prepare("DELETE FROM cart WHERE $whereClause");
    $stmt->execute($params);
}

/**
 * Return all cart rows for the current shopper, joined with live
 * product data (name, price, stock, image).
 */
function cart_items(PDO $pdo): array
{
    [$whereClause, $params] = cart_owner_clause();

    $sql = "SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.slug,
                   p.price, p.sale_price, p.stock_quantity, p.status
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE $whereClause
            ORDER BY c.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['image'] = get_primary_product_image($pdo, (int) $row['product_id']);
        $row['unit_price'] = effective_price($row);
        $row['line_total'] = $row['unit_price'] * $row['quantity'];
    }

    return $rows;
}

function cart_count(PDO $pdo): int
{
    [$whereClause, $params] = cart_owner_clause();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE $whereClause");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function cart_subtotal(PDO $pdo): float
{
    $total = 0.0;
    foreach (cart_items($pdo) as $item) {
        $total += $item['line_total'];
    }
    return $total;
}

/**
 * When a guest with items in their session cart logs in, fold those
 * items into their account cart so nothing is lost.
 */
function merge_guest_cart_into_user(PDO $pdo, int $userId, string $guestSessionId): void
{
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart WHERE session_id = :sid');
    $stmt->execute([':sid' => $guestSessionId]);
    $guestItems = $stmt->fetchAll();

    foreach ($guestItems as $item) {
        $stmt2 = $pdo->prepare('SELECT id, quantity FROM cart WHERE user_id = :uid AND product_id = :pid');
        $stmt2->execute([':uid' => $userId, ':pid' => $item['product_id']]);
        $existing = $stmt2->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $item['quantity'];
            $pdo->prepare('UPDATE cart SET quantity = :qty WHERE id = :id')
                ->execute([':qty' => $newQty, ':id' => $existing['id']]);
        } else {
            $pdo->prepare('UPDATE cart SET user_id = :uid, session_id = NULL WHERE session_id = :sid AND product_id = :pid')
                ->execute([':uid' => $userId, ':sid' => $guestSessionId, ':pid' => $item['product_id']]);
        }
    }

    // Clean up any leftover guest rows that were merged (qty summed) rather than reassigned.
    $pdo->prepare('DELETE FROM cart WHERE session_id = :sid')->execute([':sid' => $guestSessionId]);
}
