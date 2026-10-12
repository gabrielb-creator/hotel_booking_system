<?php require '../config/database.php';require '../includes/functions.php';if (!is_logged_in() || !is_customer()) { if (is_logged_in() && !is_customer()) { flash('error','Only customer accounts can create reservations.'); header('Location: ' . dashboard_url()); exit; }$_SESSION['after_login']=$_SERVER['HTTP_REFERER']??url('rooms/index.php');flash('error','Please sign in before booking.');header('Location: '.url('auth/login.php'));exit;}if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.url('rooms/index.php'));exit;}verify_csrf();$roomId=(int)($_POST['room_id']??0);$in=$_POST['check_in']??'';$out=$_POST['check_out']??'';$guests=(int)($_POST['guests']??1);try {
    $pdo->beginTransaction();
    $st=$pdo->prepare("SELECT * FROM rooms WHERE id=? AND status='available' FOR UPDATE");
    $st->execute([$roomId]); $r=$st->fetch();
    if(!$r||!$in||!$out||$out<=$in||$in<date('Y-m-d')||$guests<1||$guests>$r['capacity']) {
        $pdo->rollBack(); flash('error','Invalid booking details.'); header('Location: '.url('rooms/view.php?id='.$roomId)); exit;
    }
    if(!room_available($pdo,$roomId,$in,$out)) {
        $pdo->rollBack(); flash('error','This room is already reserved for some or all of those dates. Please choose another room or dates.'); header('Location: '.url('rooms/index.php?check_in='.urlencode($in).'&check_out='.urlencode($out).'&guests='.$guests)); exit;
    }
    [$n,$total]=booking_total($r['price_per_night'],$in,$out);
    $ref='LS'.date('ymd').strtoupper(substr(bin2hex(random_bytes(4)),0,6));
    $pdo->prepare("INSERT INTO bookings(reference_no,user_id,room_id,check_in,check_out,guests,nights,total_amount,status,payment_status) VALUES(?,?,?,?,?,?,?,?,'pending','unpaid')")->execute([$ref,$_SESSION['user']['id'],$roomId,$in,$out,$guests,$n,$total]);
    $id=$pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error','We could not reserve this room. Please refresh availability and try again.');
    header('Location: '.url('rooms/index.php?check_in='.urlencode($in).'&check_out='.urlencode($out).'&guests='.$guests)); exit;
}flash('success','Booking created. Reference: '.$ref);header('Location: '.url('booking/view.php?id='.$id));exit;?>