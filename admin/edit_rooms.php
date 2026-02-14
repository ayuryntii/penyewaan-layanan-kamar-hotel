<?php
require_once '../config.php';
requireAdmin();

if (!isset($_GET['id'])) {
    header("Location: rooms.php");
    exit();
}

$room_id = $conn->real_escape_string($_GET['id']);
$page_title = "Edit Kamar";

// Get room data
$sql = "SELECT * FROM rooms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: rooms.php");
    exit();
}

$room = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-edit"></i> Edit Kamar: <?php echo $room['room_name']; ?></h1>
                    <a href="rooms.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- Update Logic -->
                <?php
                $error = '';
                $success = '';
                
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $room_number = $conn->real_escape_string($_POST['room_number']);
                    $room_name = $conn->real_escape_string($_POST['room_name']);
                    $room_type = $conn->real_escape_string($_POST['room_type']);
                    $description = $conn->real_escape_string($_POST['description']);
                    $price_per_night = $conn->real_escape_string($_POST['price_per_night']);
                    $capacity = $conn->real_escape_string($_POST['capacity']);
                    $amenities = $conn->real_escape_string($_POST['amenities']);
                    $status = $conn->real_escape_string($_POST['status']);
                    
                    // Handle main image upload
                    $main_image = $room['main_image'];
                    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
                        // Delete old image if exists
                        if ($main_image && file_exists('../' . $main_image)) {
                            unlink('../' . $main_image);
                        }
                        
                        $upload = uploadFile($_FILES['main_image'], 'rooms');
                        if ($upload['success']) {
                            $main_image = $upload['path'];
                        } else {
                            $error = $upload['error'];
                        }
                    }
                    
                    // Check if room number exists for other room
                    if ($room_number != $room['room_number']) {
                        $check_sql = "SELECT id FROM rooms WHERE room_number = ? AND id != ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("si", $room_number, $room_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $error = "Nomor kamar sudah digunakan!";
                        }
                        $check_stmt->close();
                    }
                    
                    if (empty($error)) {
                        // Update room
                        $sql = "UPDATE rooms SET room_number = ?, room_name = ?, room_type = ?, 
                                description = ?, price_per_night = ?, capacity = ?, amenities = ?, 
                                main_image = ?, status = ?, updated_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssdiss", $room_number, $room_name, $room_type, 
                                         $description, $price_per_night, $capacity, 
                                         $amenities, $main_image, $status, $room_id);
                        
                        if ($stmt->execute()) {
                            $success = "Kamar berhasil diperbarui!";
                            // Refresh room data
                            $sql = "SELECT * FROM rooms WHERE id = ?";
                            $refresh_stmt = $conn->prepare($sql);
                            $refresh_stmt->bind_param("i", $room_id);
                            $refresh_stmt->execute();
                            $room = $refresh_stmt->get_result()->fetch_assoc();
                            $refresh_stmt->close();
                        } else {
                            $error = "Gagal update kamar: " . $conn->error;
                        }
                        $stmt->close();
                    }
                }
                ?>

                <!-- Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="room_number" class="form-label">Nomor Kamar *</label>
                                        <input type="text" class="form-control" id="room_number" 
                                               name="room_number" value="<?php echo $room['room_number']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="room_name" class="form-label">Nama Kamar *</label>
                                        <input type="text" class="form-control" id="room_name" 
                                               name="room_name" value="<?php echo $room['room_name']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="room_type" class="form-label">Tipe Kamar *</label>
                                        <select class="form-select" id="room_type" name="room_type" required>
                                            <option value="Standard" <?php echo $room['room_type'] == 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                            <option value="Deluxe" <?php echo $room['room_type'] == 'Deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                                            <option value="Suite" <?php echo $room['room_type'] == 'Suite' ? 'selected' : ''; ?>>Suite</option>
                                            <option value="Family" <?php echo $room['room_type'] == 'Family' ? 'selected' : ''; ?>>Family</option>
                                            <option value="Executive" <?php echo $room['room_type'] == 'Executive' ? 'selected' : ''; ?>>Executive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="price_per_night" class="form-label">Harga per Malam *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="price_per_night" 
                                                   name="price_per_night" value="<?php echo $room['price_per_night']; ?>" 
                                                   min="0" step="1000" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="capacity" class="form-label">Kapasitas *</label>
                                        <select class="form-select" id="capacity" name="capacity" required>
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?php echo $i; ?>" 
                                                    <?php echo $room['capacity'] == $i ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> Orang
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="amenities" class="form-label">Fasilitas</label>
                                        <textarea class="form-control" id="amenities" name="amenities" 
                                                  rows="3"><?php echo $room['amenities']; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="available" <?php echo $room['status'] == 'available' ? 'selected' : ''; ?>>Tersedia</option>
                                            <option value="booked" <?php echo $room['status'] == 'booked' ? 'selected' : ''; ?>>Dipesan</option>
                                            <option value="maintenance" <?php echo $room['status'] == 'maintenance' ? 'selected' : ''; ?>>Perbaikan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi Kamar *</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" required><?php echo $room['description']; ?></textarea>
                            </div>
                            
                            <!-- Current Main Image -->
                            <div class="mb-3">
                                <label class="form-label">Foto Utama Saat Ini</label>
                                <?php if ($room['main_image']): ?>
                                    <div>
                                        <img src="../<?php echo $room['main_image']; ?>" 
                                             alt="Current Image" style="max-width: 200px; max-height: 200px;" 
                                             class="img-thumbnail">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="delete_image" name="delete_image">
                                            <label class="form-check-label text-danger" for="delete_image">
                                                Hapus foto ini
                                            </label>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada foto</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- New Main Image -->
                            <div class="mb-3">
                                <label for="main_image" class="form-label">Foto Utama Baru (Opsional)</label>
                                <input type="file" class="form-control" id="main_image" 
                                       name="main_image" accept="image/*">
                                <small class="text-muted">Kosongkan jika tidak ingin mengganti foto</small>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Kamar
                                </button>
                                <a href="rooms.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>