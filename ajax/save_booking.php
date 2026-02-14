<?php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * DETEKSI REQUEST AJAX
 */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * INCLUDE FUNCTIONS (opsional)
 */


/**
 * FALLBACK FUNCTION getSetting() kalau belum ada
 */
if (!function_exists('getSetting')) {
    function getSetting($key, $default = null)
    {
        global $conn;

        // kalau tabel settings tidak ada / error, balikin default
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        if (!$stmt) return $default;

        $stmt->bind_param("s", $key);
        $stmt->execute();

        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['setting_value'];
        }
        return $default;
    }
}

/**
 * RESPONSE HELPER
 */
function respond($success, $message, $extra = [])
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $extra));
        exit();
    }

    // Kalau bukan AJAX, simpan message ke session lalu redirect
    if ($success) {
        $_SESSION['success'] = $message;
        header("Location: ../admin/bookings.php");
    } else {
        $_SESSION['error'] = $message;
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "../admin/bookings.php"));
    }
    exit();
}

/**
 * AUTH CHECK
 * (minimal harus login)
 */
if (!isset($_SESSION['user_id'])) {
    respond(false, "Unauthorized access!");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, "Invalid request method!");
}

/**
 * GET DATA
 */
$booking_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$user_id    = (int)($_POST['user_id'] ?? 0);
$room_id    = (int)($_POST['room_id'] ?? 0);

$check_in  = trim($_POST['check_in'] ?? '');
$check_out = trim($_POST['check_out'] ?? '');

$adults   = (int)($_POST['adults'] ?? 1);
$children = (int)($_POST['children'] ?? 0);

$special_requests = trim($_POST['special_requests'] ?? '');

$booking_status = trim($_POST['booking_status'] ?? 'pending');
$payment_status = trim($_POST['payment_status'] ?? 'pending');
$payment_method = trim($_POST['payment_method'] ?? '');

/**
 * VALIDATION
 */
if ($user_id <= 0) {
    respond(false, "Customer wajib dipilih!");
}
if ($room_id <= 0) {
    respond(false, "Room wajib dipilih!");
}
if (empty($check_in) || empty($check_out)) {
    respond(false, "Tanggal check-in dan check-out wajib diisi!");
}
if (strtotime($check_out) <= strtotime($check_in)) {
    respond(false, "Check-out harus setelah check-in!");
}
if ($adults < 1) {
    respond(false, "Adults minimal 1!");
}

/**
 * CALCULATE NIGHTS
 */
$nights = (int) round((strtotime($check_out) - strtotime($check_in)) / 86400);
if ($nights <= 0) $nights = 1;

/**
 * GET ROOM & PRICE
 */
$room_sql = "SELECT r.*, rc.base_price
            FROM rooms r
            JOIN room_categories rc ON r.category_id = rc.id
            WHERE r.id = ?";
$room_stmt = $conn->prepare($room_sql);
if (!$room_stmt) {
    respond(false, "DB Error: gagal prepare room query!");
}

$room_stmt->bind_param("i", $room_id);
$room_stmt->execute();
$room_result = $room_stmt->get_result();

if ($room_result->num_rows === 0) {
    respond(false, "Room tidak ditemukan!");
}

$room = $room_result->fetch_assoc();
$base_price_per_night = (float)$room['base_price'];

/**
 * PRICING
 */
$base_price = $base_price_per_night * $nights;

// rules tambahan (sesuai kode lama kamu)
$extra_adults   = max(0, $adults - 2) * 100000 * $nights;
$extra_children = $children * 50000 * $nights;

$total_price = $base_price + $extra_adults + $extra_children;

// tax
$tax_rate = (float) getSetting('tax_rate', 10);
$tax = $total_price * ($tax_rate / 100);
$final_price = $total_price + $tax;

/**
 * SAVE BOOKING
 */
try {
    if ($booking_id === 0) {
        // INSERT
        $booking_code = 'BOOK' . date('Ymd') . strtoupper(substr(uniqid(), -5));

        $sql = "INSERT INTO bookings
                (booking_code, user_id, room_id, check_in, check_out, total_nights,
                 adults, children, total_price, final_price, special_requests,
                 booking_status, payment_status, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            respond(false, "DB Error: gagal prepare insert booking!");
        }

        $stmt->bind_param(
            "siissiiiddssss",
            $booking_code,
            $user_id,
            $room_id,
            $check_in,
            $check_out,
            $nights,
            $adults,
            $children,
            $total_price,
            $final_price,
            $special_requests,
            $booking_status,
            $payment_status,
            $payment_method
        );

    } else {
        // UPDATE
        $sql = "UPDATE bookings SET
                user_id = ?,
                room_id = ?,
                check_in = ?,
                check_out = ?,
                total_nights = ?,
                adults = ?,
                children = ?,
                total_price = ?,
                final_price = ?,
                special_requests = ?,
                booking_status = ?,
                payment_status = ?,
                payment_method = ?,
                updated_at = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            respond(false, "DB Error: gagal prepare update booking!");
        }

        $stmt->bind_param(
            "iissiiiddssssi",
            $user_id,
            $room_id,
            $check_in,
            $check_out,
            $nights,
            $adults,
            $children,
            $total_price,
            $final_price,
            $special_requests,
            $booking_status,
            $payment_status,
            $payment_method,
            $booking_id
        );
    }

    if ($stmt->execute()) {

        if ($booking_id === 0) {
            $booking_id = $conn->insert_id; // FIX: ambil dari conn
        }

        /**
         * UPDATE ROOM STATUS (kalau booking confirmed / checked_in)
         */
        if (in_array($booking_status, ['confirmed', 'checked_in'])) {
            $update_sql = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("i", $room_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }

        respond(true, "Booking berhasil disimpan!", [
            'booking_id' => $booking_id
        ]);

    } else {
        respond(false, "Gagal menyimpan booking: " . $conn->error);
    }

} catch (Throwable $e) {
    respond(false, "Error: " . $e->getMessage());
}
