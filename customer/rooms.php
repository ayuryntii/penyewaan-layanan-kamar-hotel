<?php
// customer/rooms.php - VIEW ROOMS PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'View Rooms';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

// Get room categories
$categories_sql = "SELECT * FROM room_categories ORDER BY base_price ASC";
$categories_result = $conn->query($categories_sql);
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
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-dark: #3a86ff;
            --light: #f8f9fa;
            --gray: #6c757d;
            --dark-bg: #0a192f;
            --card-bg: rgba(20, 30, 50, 0.85);
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        .customer-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* === Sidebar === */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--navy);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(76, 201, 240, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 18px;
        }

        .sidebar-title h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-title p {
            font-size: 0.85rem;
            color: #aaa;
        }

        .sidebar-nav {
            padding: 20px 0;
            overflow-y: auto;
            height: calc(100vh - 180px);
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #ccc;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .nav-label {
            padding: 15px 25px 8px;
            color: #777;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 15px 0;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--navy);
        }

        .user-info .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-info .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        /* === Main Content === */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(76, 201, 240, 0.1);
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .content-area {
            padding: 30px;
        }

        /* === Cards === */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .card-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .form-control {
            width: 200px;
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23ccc'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }

        .card-body {
            padding: 25px;
        }

        /* === Buttons === */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-1px);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-1px);
        }

        /* === Last Login === */
        .last-login {
            color: #aaa;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* === Logout Button === */
        .logout-btn {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            transform: translateY(-1px);
        }

        /* === Room Grid === */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .room-card {
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }

        .room-card:hover {
            transform: translateY(-10px);
            border-color: var(--blue);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .room-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }

        .room-price {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--blue);
            color: var(--navy);
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .room-price span {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .room-content {
            padding: 25px;
        }

        .room-title {
            font-size: 1.4rem;
            color: white;
            margin-bottom: 10px;
        }

        .room-description {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .room-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #aaa;
            font-size: 0.9rem;
        }

        .feature-item i {
            color: var(--blue);
            width: 20px;
        }

        .room-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* === Room Details Modal === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: rgba(255,255,255,0.1);
        }

        .modal-body {
            padding: 30px;
        }

        .room-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .gallery-item {
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--blue);
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
        }

        .amenity-item i {
            color: var(--blue);
            font-size: 1.2rem;
        }

        /* === Responsive === */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
            .rooms-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }
            
            .room-features {
                grid-template-columns: 1fr;
            }
            
            .room-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 10px;
            }
            
            .modal-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="customer-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="sidebar-title">
                    <h3><?= htmlspecialchars($hotel_name) ?></h3>
                    <p>Customer Portal</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">BOOKINGS</p>
                    <a href="bookings.php" class="nav-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Bookings</span>
                    </a>
                    <a href="new-booking.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Booking</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">PROFILE</p>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                
                <div class="nav-divider"></div>
                
                <div class="nav-group">
                    <p class="nav-label">HOTEL</p>
                    <a href="rooms.php" class="nav-item active">
                        <i class="fas fa-bed"></i>
                        <span>View Rooms</span>
                    </a>
                    <a href="services.php" class="nav-item">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Services</span>
                    </a>
                </div>

                <div class="nav-divider"></div>

<div class="nav-group">
    <p class="nav-label">INVOICES</p>
    <a href="invoice.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'invoice.php') ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>My Invoices</span>
    </a>
</div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($customer['full_name']) ?></div>
                        <div class="user-role"><?= ucfirst($customer['role']) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="last-login">
                        <i class="fas fa-clock"></i>
                        Last Login: <?= $customer['last_login'] ? date('d M Y H:i', strtotime($customer['last_login'])) : 'First login' ?>
                    </div>
                    
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <div class="content-area">
                <div class="card">
                    <div class="card-header">
                        <h3>Our Luxury Rooms & Suites</h3>
                        <div class="card-filters">
                            <a href="new-booking.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($categories_result->num_rows > 0): ?>
                        <div class="rooms-grid">
                            <?php while ($category = $categories_result->fetch_assoc()): 
                                $amenities = explode(',', $category['amenities'] ?? 'WiFi,TV,AC,Mini Bar,Private Bathroom');
                            ?>
                            <div class="room-card" onclick="showRoomDetails(<?= htmlspecialchars(json_encode($category)) ?>)">
                                <div class="room-image">
                                    <div class="room-price">
                                        <?= formatCurrency($category['base_price']) ?><span>/night</span>
                                    </div>
                                </div>
                                <div class="room-content">
                                    <h3 class="room-title"><?= htmlspecialchars($category['name']) ?></h3>
                                    <p class="room-description">
                                        <?= htmlspecialchars($category['description'] ?? 'Experience luxury and comfort in our beautifully appointed room.') ?>
                                    </p>
                                    
                                    <div class="room-features">
                                        <div class="feature-item">
                                            <i class="fas fa-user"></i>
                                            <span>Max <?= $category['max_capacity'] ?> Persons</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-expand"></i>
                                            <span><?= $category['size'] ?? '35' ?> m²</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-bed"></i>
                                            <span><?= $category['bed_type'] ?? 'King' ?> Bed</span>
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-eye"></i>
                                            <span><?= $category['view_type'] ?? 'City' ?> View</span>
                                        </div>
                                    </div>
                                    
                                    <div class="room-actions">
                                        <a href="new-booking.php" class="btn btn-primary" style="flex: 1;">
                                            <i class="fas fa-calendar-check"></i> Book Now
                                        </a>
                                        <button class="btn btn-secondary" onclick="event.stopPropagation(); showRoomDetails(<?= htmlspecialchars(json_encode($category)) ?>);">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: #aaa;">
                            <i class="fas fa-bed fa-3x" style="opacity: 0.5; margin-bottom: 20px;"></i>
                            <h3 style="margin: 15px 0 10px 0;">No Rooms Available</h3>
                            <p style="color: #777; margin-bottom: 20px;">Please check back later for available rooms.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Room Details Modal -->
    <div class="modal" id="roomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Room Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="roomModalBody">
                <!-- Room details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Show room details
        function showRoomDetails(room) {
            const amenities = room.amenities ? room.amenities.split(',') : ['WiFi', 'TV', 'AC', 'Mini Bar', 'Private Bathroom'];
            
            const html = `
                <div class="room-gallery">
                    <div class="gallery-item">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div class="gallery-item">
                        <i class="fas fa-bath"></i>
                    </div>
                    <div class="gallery-item">
                        <i class="fas fa-tv"></i>
                    </div>
                    <div class="gallery-item">
                        <i class="fas fa-wifi"></i>
                    </div>
                </div>
                
                <h3 style="color: white; margin-bottom: 15px;">${room.name}</h3>
                <p style="color: #ccc; line-height: 1.6; margin-bottom: 20px;">
                    ${room.description || 'Experience luxury and comfort in our beautifully appointed room.'}
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0;">
                    <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px;">
                        <div style="color: var(--blue); font-size: 1.2rem; font-weight: 600;">${formatCurrency(room.base_price)}</div>
                        <div style="color: #aaa; font-size: 0.9rem;">Per night</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px;">
                        <div style="color: white; font-size: 1.2rem; font-weight: 600;">${room.max_capacity || 2}</div>
                        <div style="color: #aaa; font-size: 0.9rem;">Max Persons</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px;">
                        <div style="color: white; font-size: 1.2rem; font-weight: 600;">${room.size || '35'} m²</div>
                        <div style="color: #aaa; font-size: 0.9rem;">Room Size</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px;">
                        <div style="color: white; font-size: 1.2rem; font-weight: 600;">${room.bed_type || 'King'}</div>
                        <div style="color: #aaa; font-size: 0.9rem;">Bed Type</div>
                    </div>
                </div>
                
                <h4 style="color: var(--blue); margin: 25px 0 15px 0;">Amenities</h4>
                <div class="amenities-grid">
                    ${amenities.map(amenity => `
                        <div class="amenity-item">
                            <i class="fas fa-check"></i>
                            <span>${amenity.trim()}</span>
                        </div>
                    `).join('')}
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <a href="new-booking.php" class="btn btn-primary" style="flex: 1; padding: 15px; font-size: 1.1rem;">
                        <i class="fas fa-calendar-check"></i> Book This Room
                    </a>
                    <button class="btn btn-secondary" onclick="closeModal()" style="padding: 15px; font-size: 1.1rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            document.getElementById('modalTitle').textContent = room.name;
            document.getElementById('roomModalBody').innerHTML = html;
            document.getElementById('roomModal').style.display = 'flex';
        }

        // Format currency
        function formatCurrency(amount) {
            return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Close modal
        function closeModal() {
            document.getElementById('roomModal').style.display = 'none';
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('roomModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Add hover effects to room cards
        document.addEventListener('DOMContentLoaded', function() {
            const roomCards = document.querySelectorAll('.room-card');
            
            roomCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const price = this.querySelector('.room-price');
                    if (price) {
                        price.style.transform = 'scale(1.1)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    const price = this.querySelector('.room-price');
                    if (price) {
                        price.style.transform = 'scale(1)';
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>