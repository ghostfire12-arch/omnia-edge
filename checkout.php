<?php
require_once __DIR__ . '/includes/cart-functions.php';
$items=cart_items($pdo); if(!$items){set_flash('error','Your cart is empty.');redirect(asset_url('cart.php'));}
$subtotal=cart_subtotal($pdo);$shipping=(float)get_setting('shipping_fee','2500');$total=$subtotal+$shipping;$error='';$customer=[];
if(is_customer_logged_in()){$s=$pdo->prepare('SELECT * FROM users WHERE id=:id');$s->execute([':id'=>current_customer_id()]);$customer=$s->fetch()?:[];}
if($_SERVER['REQUEST_METHOD']==='POST'){require_csrf();$name=clean_input($_POST['customer_name']??'');$email=filter_var(trim($_POST['email']??''),FILTER_VALIDATE_EMAIL);$phone=clean_input($_POST['phone']??'');$address=clean_input($_POST['address']??'');$city=clean_input($_POST['city']??'');$state=clean_input($_POST['state']??'');$country=clean_input($_POST['country']??'Nigeria');$method=in_array($_POST['payment_method']??'', ['cod','bank_transfer','paystack'],true)?$_POST['payment_method']:'cod';
 if(!$name||!$email||!$phone||!$address||!$city||!$state)$error='Please complete all delivery details.'; else {try{$pdo->beginTransaction();foreach($items as $item){$q=$pdo->prepare('SELECT stock_quantity,status FROM products WHERE id=:id FOR UPDATE');$q->execute([':id'=>$item['product_id']]);$p=$q->fetch();if(!$p||$p['status']!=='active'||$p['stock_quantity']<$item['quantity'])throw new RuntimeException($item['name'].' is no longer available in the requested quantity.');}$number=generate_order_number();$q=$pdo->prepare('INSERT INTO orders(user_id,order_number,subtotal,shipping_fee,total,payment_method,payment_status,order_status,customer_name,customer_email,customer_phone,shipping_address,city,state,country,notes) VALUES(:uid,:num,:sub,:ship,:total,:method,:pay,:status,:name,:email,:phone,:address,:city,:state,:country,:notes)');$q->execute([':uid'=>current_customer_id(),':num'=>$number,':sub'=>$subtotal,':ship'=>$shipping,':total'=>$total,':method'=>$method,':pay'=>'pending',':status'=>'pending',':name'=>$name,':email'=>$email,':phone'=>$phone,':address'=>$address,':city'=>$city,':state'=>$state,':country'=>$country,':notes'=>clean_input($_POST['notes']??'')]);$orderId=(int)$pdo->lastInsertId();$line=$pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,quantity,price,subtotal) VALUES(:oid,:pid,:name,:qty,:price,:sub)');$stock=$pdo->prepare('UPDATE products SET stock_quantity=stock_quantity-:qty WHERE id=:id');foreach($items as $item){$line->execute([':oid'=>$orderId,':pid'=>$item['product_id'],':name'=>$item['name'],':qty'=>$item['quantity'],':price'=>$item['unit_price'],':sub'=>$item['line_total']]);$stock->execute([':qty'=>$item['quantity'],':id'=>$item['product_id']]);}cart_clear($pdo);$pdo->commit();$_SESSION['last_order_id']=$orderId;redirect(asset_url('order-success.php?id='.$orderId));}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());$error=$e instanceof RuntimeException?$e->getMessage():'We could not place your order. Please try again.';}}
}
$pageTitle='Checkout | '.SITE_NAME;include __DIR__.'/includes/header.php';?>

<div class="checkout-header">
  <div class="container">
    <h1>Secure Checkout</h1>
    <div class="checkout-steps">
      <div class="step active">
        <span class="step-number">1</span>
        <span class="step-label">Delivery</span>
      </div>
      <div class="step">
        <span class="step-number">2</span>
        <span class="step-label">Payment</span>
      </div>
      <div class="step">
        <span class="step-number">3</span>
        <span class="step-label">Complete</span>
      </div>
    </div>
  </div>
</div>

<main class="container section">
  <div class="checkout-layout">
    <div class="checkout-main">
      <?php if($error):?>
        <div class="alert alert-error"><?=e($error)?></div>
      <?php endif?>

      <div class="checkout-section">
        <div class="section-header">
          <h2>Delivery Information</h2>
          <p>Enter your delivery details</p>
        </div>
        <form method="post" class="checkout-form">
          <?=csrf_field()?>
          
          <div class="form-section-title">Contact Details</div>
          <div class="form-row">
            <div class="form-group">
              <label>Full Name</label>
              <input name="customer_name" required value="<?=e($_POST['customer_name']??(($customer['first_name']??'').' '.($customer['last_name']??'')))?>" placeholder="Enter your full name">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" required value="<?=e($_POST['email']??($customer['email']??''))?>" placeholder="your@email.com">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" required value="<?=e($_POST['phone']??($customer['phone']??''))?>" placeholder="0800 000 0000">
            </div>
          </div>
          
          <div class="form-section-title">Delivery Address</div>
          <div class="form-group">
            <label>Street Address</label>
            <textarea name="address" required placeholder="Enter your full street address"><?=e($_POST['address']??($customer['address']??''))?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>City</label>
              <input name="city" required value="<?=e($_POST['city']??($customer['city']??''))?>" placeholder="City">
            </div>
            <div class="form-group">
              <label>State</label>
              <input name="state" required value="<?=e($_POST['state']??($customer['state']??''))?>" placeholder="State">
            </div>
          </div>
          <div class="form-group">
            <label>Country</label>
            <input name="country" value="<?=e($_POST['country']??($customer['country']??'Nigeria'))?>" placeholder="Country">
          </div>
          
          <div class="form-section-title">Payment Method</div>
          <div class="payment-options">
            <label class="payment-option">
              <input type="radio" name="payment_method" value="cod" checked>
              <span class="payment-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                <span class="payment-label">
                  <strong>Cash on Delivery</strong>
                  <small>Pay when your order arrives</small>
                </span>
              </span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment_method" value="bank_transfer">
              <span class="payment-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span class="payment-label">
                  <strong>Bank Transfer</strong>
                  <small>Transfer to our bank account</small>
                </span>
              </span>
            </label>
            <label class="payment-option disabled">
              <input type="radio" name="payment_method" value="paystack" disabled>
              <span class="payment-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span class="payment-label">
                  <strong>Paystack</strong>
                  <small>Coming soon</small>
                </span>
              </span>
            </label>
          </div>
          
          <div class="form-section-title">Additional Notes</div>
          <div class="form-group">
            <label>Delivery Notes (Optional)</label>
            <textarea name="notes" placeholder="Any special instructions for delivery..."></textarea>
          </div>
          
          <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">Place Order - <?=format_price($total)?></button>
          </div>
        </form>
      </div>
    </div>

    <aside class="checkout-sidebar">
      <div class="order-summary-card">
        <h3>Order Summary</h3>
        
        <div class="order-items">
          <?php foreach($items as $item):?>
            <div class="order-item">
              <div class="order-item-info">
                <span class="order-item-name"><?=e($item['name'])?></span>
                <span class="order-item-qty">Qty: <?=$item['quantity']?></span>
              </div>
              <span class="order-item-price"><?=format_price($item['line_total'])?></span>
            </div>
          <?php endforeach?>
        </div>
        
        <div class="order-totals">
          <div class="total-row">
            <span>Subtotal</span>
            <span><?=format_price($subtotal)?></span>
          </div>
          <div class="total-row">
            <span>Shipping</span>
            <span><?=format_price($shipping)?></span>
          </div>
          <div class="total-row total">
            <span>Total</span>
            <span><?=format_price($total)?></span>
          </div>
        </div>
        
        <div class="order-security">
          <svg viewBox="0 0 256 256" fill="currentColor"><path d="M208 80h-24V56a48 48 0 0 0-96 0v24H64a16 16 0 0 0-16 16v112a16 16 0 0 0 16 16h144a16 16 0 0 0 16-16V96a16 16 0 0 0-16-16Zm-72 78a10 10 0 1 1 10-10 10 10 0 0 1-10 10Zm32-78H96V56a32 32 0 0 1 64 0v24Z"/></svg>
          <span>Secure checkout powered by SSL encryption</span>
        </div>
      </div>
    </aside>
  </div>
</main>

<?php include __DIR__.'/includes/footer.php';?>
