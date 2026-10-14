<?php
/**
 * Sample sales data so the dashboard and reports can be explored before
 * real orders exist. Everything created here is tagged and removable:
 *   orders     order_number starts with "DEMO-"
 *   customers  email ends with  "@demo.omniaedge.test"
 *   messages   email ends with  "@demo.omniaedge.test"
 * Stock levels are NOT changed by demo orders.
 */
const DEMO_DOMAIN = '@demo.omniaedge.test';

function demo_counts(PDO $pdo): array
{
    return [
        'orders'    => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_number LIKE 'DEMO-%'")->fetchColumn(),
        'customers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'")->fetchColumn(),
        'messages'  => (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE email LIKE '%" . DEMO_DOMAIN . "'")->fetchColumn(),
    ];
}

function demo_remove(PDO $pdo): int
{
    $pdo->beginTransaction();
    $n = $pdo->exec("DELETE FROM orders WHERE order_number LIKE 'DEMO-%'");        // order_items cascade
    $pdo->exec("DELETE FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'");
    $pdo->exec("DELETE FROM contact_messages WHERE email LIKE '%" . DEMO_DOMAIN . "'");
    $pdo->commit();
    return (int) $n;
}

function demo_load(PDO $pdo, int $orders = 160, int $days = 120): int
{
    demo_remove($pdo);
    $products = $pdo->query("SELECT id, name, price, sale_price FROM products WHERE status = 'active'")->fetchAll();
    if (!$products) return 0;

    $first = ['Chinedu', 'Aisha', 'Tunde', 'Ngozi', 'Emeka', 'Fatima', 'Segun', 'Blessing', 'Ibrahim', 'Chioma', 'Femi', 'Zainab', 'Kelechi', 'Yemi', 'Amaka', 'Musa', 'Ifeoma', 'Bola', 'Uche', 'Hauwa', 'Tobi', 'Adaeze', 'Sani', 'Funke'];
    $last  = ['Okafor', 'Bello', 'Adeyemi', 'Eze', 'Okonkwo', 'Abubakar', 'Balogun', 'Nwosu', 'Ibrahim', 'Okeke', 'Adebayo', 'Mohammed', 'Obi', 'Ogunleye', 'Danjuma', 'Akande'];
    $states = ['Lagos' => 34, 'Abuja FCT' => 14, 'Rivers' => 9, 'Oyo' => 8, 'Kano' => 7, 'Enugu' => 6, 'Delta' => 5, 'Ogun' => 6, 'Kaduna' => 4, 'Anambra' => 4, 'Edo' => 3];
    $cities = ['Lagos' => 'Ikeja', 'Abuja FCT' => 'Garki', 'Rivers' => 'Port Harcourt', 'Oyo' => 'Ibadan', 'Kano' => 'Kano', 'Enugu' => 'Enugu', 'Delta' => 'Asaba', 'Ogun' => 'Abeokuta', 'Kaduna' => 'Kaduna', 'Anambra' => 'Awka', 'Edo' => 'Benin City'];
    $weighted = function (array $w) { $t = array_sum($w); $r = random_int(1, $t); foreach ($w as $k => $v) { if (($r -= $v) <= 0) return $k; } return array_key_first($w); };
    $shipping = (float) get_setting('shipping_fee', '2500');

    $pdo->beginTransaction();
    // Customers
    $userIds = [];
    $insUser = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password, city, state, created_at) VALUES (:f, :l, :e, :p, :pw, :c, :s, :at)');
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // random, unusable password
    for ($i = 0; $i < 36; $i++) {
        $f = $first[array_rand($first)]; $l = $last[array_rand($last)]; $st = $weighted($states);
        $insUser->execute([':f' => $f, ':l' => $l, ':e' => strtolower($f . '.' . $l . $i) . DEMO_DOMAIN, ':p' => '080' . random_int(10000000, 99999999),
            ':pw' => $hash, ':c' => $cities[$st], ':s' => $st, ':at' => date('Y-m-d H:i:s', time() - random_int(0, $days * 86400))]);
        $userIds[] = [(int) $pdo->lastInsertId(), "$f $l", strtolower($f . '.' . $l . $i) . DEMO_DOMAIN, $st];
    }

    $insOrder = $pdo->prepare('INSERT INTO orders (user_id, order_number, subtotal, shipping_fee, total, payment_method, payment_status, order_status,
        customer_name, customer_email, customer_phone, shipping_address, city, state, country, created_at)
        VALUES (:u, :n, :sub, :ship, :total, :pm, :ps, :os, :name, :email, :phone, :addr, :city, :state, "Nigeria", :at)');
    $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) VALUES (:o, :p, :n, :q, :pr, :s)');
    // Hour weights: busy lunch time and evenings.
    $hours = [8 => 2, 9 => 3, 10 => 4, 11 => 5, 12 => 7, 13 => 7, 14 => 5, 15 => 4, 16 => 4, 17 => 5, 18 => 7, 19 => 9, 20 => 10, 21 => 8, 22 => 4, 23 => 2, 7 => 1, 6 => 1];
    $made = 0;
    for ($i = 0; $i < $orders; $i++) {
        // More orders in recent weeks (growth curve) + weekend lift.
        $dayAgo = (int) floor($days * pow(random_int(0, 1000) / 1000, 1.35));
        $ts = strtotime('-' . $dayAgo . ' days');
        $ts = strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d:%02d:00', $weighted($hours), random_int(0, 59)));
        if ($ts > time()) $ts = time() - random_int(60, 3600);
        $u = random_int(1, 10) <= 7 ? $userIds[array_rand($userIds)] : null;
        $st = $u ? $u[3] : $weighted($states);
        $name = $u ? $u[1] : $first[array_rand($first)] . ' ' . $last[array_rand($last)];
        $email = $u ? $u[2] : 'guest' . $i . DEMO_DOMAIN;

        $age = (time() - $ts) / 86400;
        $os = $age > 10 ? $weighted(['delivered' => 78, 'cancelled' => 7, 'shipped' => 10, 'processing' => 5])
            : ($age > 3 ? $weighted(['shipped' => 40, 'processing' => 30, 'delivered' => 20, 'pending' => 10]) : $weighted(['pending' => 55, 'processing' => 35, 'shipped' => 10]));
        $pm = $weighted(['paystack' => 46, 'bank_transfer' => 24, 'cod' => 30]);
        $ps = $os === 'cancelled' ? $weighted(['failed' => 3, 'refunded' => 4]) : ($pm === 'cod' && !in_array($os, ['delivered'], true) ? 'pending' : 'paid');

        $lines = []; $sub = 0;
        $n = $weighted([1 => 55, 2 => 28, 3 => 12, 4 => 5]);
        foreach ((array) array_rand($products, min($n, count($products))) as $k) {
            $p = $products[$k]; $qty = $weighted([1 => 80, 2 => 15, 3 => 5]);
            $price = (!empty($p['sale_price']) && $p['sale_price'] < $p['price']) ? (float) $p['sale_price'] : (float) $p['price'];
            $lines[] = [$p, $qty, $price]; $sub += $qty * $price;
        }
        $insOrder->execute([':u' => $u[0] ?? null, ':n' => 'DEMO-' . date('ymd', $ts) . '-' . strtoupper(bin2hex(random_bytes(3))), ':sub' => $sub, ':ship' => $shipping,
            ':total' => $sub + $shipping, ':pm' => $pm, ':ps' => $ps, ':os' => $os, ':name' => $name, ':email' => $email,
            ':phone' => '080' . random_int(10000000, 99999999), ':addr' => random_int(1, 120) . ' Demo Street', ':city' => $cities[$st], ':state' => $st,
            ':at' => date('Y-m-d H:i:s', $ts)]);
        $oid = (int) $pdo->lastInsertId();
        foreach ($lines as [$p, $qty, $price]) {
            $insItem->execute([':o' => $oid, ':p' => $p['id'], ':n' => $p['name'], ':q' => $qty, ':pr' => $price, ':s' => $qty * $price]);
        }
        $made++;
    }

    $msgs = [
        ['Delivery to Port Harcourt', 'Hello, how many days does delivery to Port Harcourt usually take? I want to order the chest freezer.'],
        ['Bulk order for our office', "Good afternoon. We need 25 laptop stands and 25 wireless mice for our new office in Victoria Island.\nCan you give us a corporate price?"],
        ['Wrong colour received', 'I ordered the black backpack but received a brown one. How do I return it?'],
        ['Is the smartwatch waterproof?', 'Please is the Oraimo Watch 6N waterproof? I want to use it while swimming.'],
        ['Pay on delivery', 'Can I pay on delivery for orders above 300k?'],
    ];
    $insMsg = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, is_read, created_at) VALUES (:n, :e, :p, :s, :m, :r, :at)');
    foreach ($msgs as $i => [$s, $m]) {
        $f = $first[array_rand($first)] . ' ' . $last[array_rand($last)];
        $insMsg->execute([':n' => $f, ':e' => 'msg' . $i . DEMO_DOMAIN, ':p' => '080' . random_int(10000000, 99999999), ':s' => $s, ':m' => $m,
            ':r' => $i > 2 ? 1 : 0, ':at' => date('Y-m-d H:i:s', time() - $i * 26000 - random_int(0, 9000))]);
    }
    $pdo->commit();
    return $made;
}
