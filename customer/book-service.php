<?php
// customer/book-service.php - CREATE SERVICE REQUEST (CUSTOMER) [FIXED]
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = "Book Service";

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer = $customer_stmt->get_result()->fetch_assoc();
$customer_stmt->close();

// Get active booking (checked_in)
$booking_sql = "
SELECT b.*, r.room_number, rc.name AS room_type
FROM bookings b
JOIN rooms r ON b.room_id = r.id
LEFT JOIN room_categories rc ON r.category_id = rc.id
WHERE b.user_id = ?
AND b.booking_status = 'checked_in'
ORDER BY b.id DESC
LIMIT 1
";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("i", $user_id);
$booking_stmt->execute();
$booking = $booking_stmt->get_result()->fetch_assoc();
$booking_stmt->close();

if (!$booking) {
    $_SESSION['flash_message'] = "You must be checked-in to request hotel services.";
    $_SESSION['flash_type'] = "error";
    header("Location: services.php");
    exit;
}

// Service type from URL
$service_type = trim($_GET['service'] ?? 'Other');
$allowed_services = ['Room Service','Maintenance','Cleaning','Laundry','Transportation','Other'];
if (!in_array($service_type, $allowed_services, true)) {
    $service_type = "Other";
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $service_type = trim($_POST['service_type'] ?? 'Other');
    if (!in_array($service_type, $allowed_services, true)) {
        $service_type = "Other";
    }

    $priority = trim($_POST['priority'] ?? 'medium');
    $allowed_priority = ['low','medium','high'];
    if (!in_array($priority, $allowed_priority, true)) {
        $priority = "medium";
    }

    $description = trim($_POST['description'] ?? '');

    if ($description === '') {
        $_SESSION['flash_message'] = "Description is required!";
        $_SESSION['flash_type'] = "error";
    } else {

        // ✅ INSERT INTO service_requests (user_id + booking_id) - sesuai FK kamu
        $insert_sql = "
        INSERT INTO service_requests (user_id, booking_id, service_type, description, priority, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ";

        $stmt = $conn->prepare($insert_sql);

        if ($stmt) {
            $stmt->bind_param("iisss", $user_id, $booking['id'], $service_type, $description, $priority);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Service request sent successfully!";
                $_SESSION['flash_type'] = "success";
                $stmt->close();

                header("Location: services.php");
                exit;
            } else {
                $_SESSION['flash_message'] = "Failed to send service request!";
                $_SESSION['flash_type'] = "error";
            }

            $stmt->close();
        } else {
            $_SESSION['flash_message'] = "Database error: cannot prepare statement.";
            $_SESSION['flash_type'] = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($hotel_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
--navy:#0a192f; --blue:#4cc9f0; --light:#f8f9fa; --dark-bg:#0a192f;
--card-bg:rgba(20,30,50,0.85); --red:#dc3545; --green:#28a745; --yellow:#ffc107;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:var(--dark-bg);color:var(--light)}
.container{max-width:1000px;margin:0 auto;padding:30px}
.card{
background:var(--card-bg);
border:1px solid rgba(255,255,255,0.1);
border-radius:16px;
padding:25px;
}
h1{font-size:1.8rem;margin-bottom:10px}
.small{color:#aaa;font-size:0.9rem;margin-bottom:18px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px}
@media(max-width:768px){.form-grid{grid-template-columns:1fr}}
.select, .textarea{
width:100%;padding:12px 14px;border-radius:10px;
border:1px solid rgba(255,255,255,0.1);
background:rgba(0,0,0,0.25);color:#fff;outline:none;
}
.textarea{min-height:130px;resize:vertical}
.btn{
display:inline-flex;align-items:center;gap:8px;
padding:12px 18px;border-radius:10px;border:none;cursor:pointer;
font-weight:600;text-decoration:none;
}
.btn-primary{background:var(--blue);color:var(--navy)}
.btn-secondary{background:rgba(255,255,255,0.12);color:#fff}
.badge{
display:inline-flex;align-items:center;gap:8px;
background:rgba(255,255,255,0.06);
border:1px solid rgba(255,255,255,0.1);
padding:8px 12px;border-radius:12px;color:#ccc;font-size:0.9rem;
}
.alert{
padding:12px 15px;border-radius:10px;margin-bottom:18px;
border:1px solid transparent;
}
.alert-success{background:rgba(40,167,69,0.2);border-color:rgba(40,167,69,0.3);color:#28a745}
.alert-error{background:rgba(220,53,69,0.2);border-color:rgba(220,53,69,0.3);color:#dc3545}
</style>
</head>

<body>
<div class="container">

<?php
if (isset($_SESSION['flash_message'])) {
    $class = ($_SESSION['flash_type'] ?? '') === 'error' ? 'alert-error' : 'alert-success';
    echo '<div class="alert '.$class.'"><i class="fas fa-info-circle"></i> '.$_SESSION['flash_message'].'</div>';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>

<div class="card">
    <h1><i class="fas fa-concierge-bell"></i> Request Hotel Service</h1>
    <div class="small">Send a service request to the receptionist.</div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
        <div class="badge"><i class="fas fa-user"></i> <?= htmlspecialchars($customer['full_name'] ?? $customer['username']) ?></div>
        <div class="badge"><i class="fas fa-door-closed"></i> Room <?= htmlspecialchars($booking['room_number']) ?></div>
        <div class="badge"><i class="fas fa-tag"></i> Booking <?= htmlspecialchars($booking['booking_code']) ?></div>
    </div>

    <form method="POST">
        <div class="form-grid">
            <div>
                <label style="display:block;margin-bottom:8px;color:#aaa;">Service Type</label>
                <select class="select" name="service_type" required>
                    <?php foreach ($allowed_services as $s): ?>
                        <option value="<?= $s ?>" <?= ($service_type === $s ? 'selected' : '') ?>>
                            <?= $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block;margin-bottom:8px;color:#aaa;">Priority</label>
                <select class="select" name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom:15px">
            <label style="display:block;margin-bottom:8px;color:#aaa;">Description</label>
            <textarea class="textarea" name="description" placeholder="Write what you need..." required></textarea>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Request
            </button>
            <a href="services.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </form>
</div>

</div>
</body>
</html>
