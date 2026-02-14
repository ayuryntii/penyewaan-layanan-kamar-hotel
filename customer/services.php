<?php
// customer/services.php - SERVICES PAGE
session_start();
require_once '../includes/config.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$page_title = 'Hotel Services';

// Get customer data
$customer_sql = "SELECT * FROM users WHERE id = ?";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();
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
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
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

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
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

        /* === Services Grid === */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .service-card {
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .service-card:hover {
            transform: translateY(-10px);
            border-color: var(--blue);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .service-icon {
            width: 70px;
            height: 70px;
            background: rgba(76, 201, 240, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--blue);
            margin-bottom: 20px;
        }

        .service-title {
            font-size: 1.3rem;
            color: white;
            margin-bottom: 15px;
        }

        .service-description {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .service-features {
            margin-bottom: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .feature-item i {
            color: var(--blue);
        }

        .service-price {
            color: var(--blue);
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: auto;
        }

        /* === Services Tabs === */
        .services-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 24px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #ccc;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn:hover,
        .tab-btn.active {
            background: var(--blue);
            color: var(--navy);
            border-color: var(--blue);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* === Booking Modal === */
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
            max-width: 500px;
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
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
            padding: 25px;
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
        }

        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .services-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 10px;
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
                    <a href="rooms.php" class="nav-item">
                        <i class="fas fa-bed"></i>
                        <span>View Rooms</span>
                    </a>
                    <a href="services.php" class="nav-item active">
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
                <!-- Services Tabs -->
                <div class="services-tabs">
                    <button class="tab-btn active" onclick="showTab('all')">All Services</button>
                    <button class="tab-btn" onclick="showTab('dining')">Dining</button>
                    <button class="tab-btn" onclick="showTab('spa')">Spa & Wellness</button>
                    <button class="tab-btn" onclick="showTab('activities')">Activities</button>
                    <button class="tab-btn" onclick="showTab('business')">Business</button>
                </div>

                <!-- All Services -->
                <div id="all" class="tab-content active">
                    <div class="services-grid">
                        <!-- Restaurant -->
                        <div class="service-card" data-category="dining">
                            <div class="service-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h3 class="service-title">Fine Dining Restaurant</h3>
                            <p class="service-description">
                                Experience gourmet cuisine at our award-winning restaurant. Our chefs prepare exquisite dishes using the finest local ingredients.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>6:00 AM - 11:00 PM</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Reservation Recommended</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Michelin Star Chef</span>
                                </div>
                            </div>
                            <div class="service-price">From <?= formatCurrency(350000) ?></div>
                        </div>

                        <!-- Spa -->
                        <div class="service-card" data-category="spa">
                            <div class="service-icon">
                                <i class="fas fa-spa"></i>
                            </div>
                            <h3 class="service-title">Luxury Spa</h3>
                            <p class="service-description">
                                Rejuvenate your mind and body with our exclusive spa treatments. Relax in our tranquil environment with expert therapists.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>8:00 AM - 10:00 PM</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Professional Therapists</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Organic Products</span>
                                </div>
                            </div>
                            <div class="service-price">From <?= formatCurrency(500000) ?></div>
                        </div>

                        <!-- Pool -->
                        <div class="service-card" data-category="activities">
                            <div class="service-icon">
                                <i class="fas fa-swimming-pool"></i>
                            </div>
                            <h3 class="service-title">Infinity Pool</h3>
                            <p class="service-description">
                                Enjoy breathtaking city views from our stunning rooftop infinity pool. Perfect for relaxation and stunning sunset views.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>24/7 Access for Guests</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Poolside Service</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Heated Pool</span>
                                </div>
                            </div>
                            <div class="service-price">Complimentary for Guests</div>
                        </div>

                        <!-- Gym -->
                        <div class="service-card" data-category="activities">
                            <div class="service-icon">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <h3 class="service-title">Fitness Center</h3>
                            <p class="service-description">
                                Stay fit during your stay with our state-of-the-art fitness equipment and personal training services.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>5:00 AM - 11:00 PM</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Personal Trainers</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Latest Equipment</span>
                                </div>
                            </div>
                            <div class="service-price">Complimentary for Guests</div>
                        </div>

                        <!-- Business Center -->
                        <div class="service-card" data-category="business">
                            <div class="service-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h3 class="service-title">Business Center</h3>
                            <p class="service-description">
                                Complete business services including meeting rooms, printing, scanning, and high-speed internet access.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>24/7 Access</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Secretarial Services</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>High-Speed WiFi</span>
                                </div>
                            </div>
                            <div class="service-price">From <?= formatCurrency(150000) ?>/hour</div>
                        </div>

                        <!-- Concierge -->
                        <div class="service-card" data-category="activities">
                            <div class="service-icon">
                                <i class="fas fa-concierge-bell"></i>
                            </div>
                            <h3 class="service-title">24/7 Concierge</h3>
                            <p class="service-description">
                                Our dedicated concierge team is available round the clock to assist with reservations, tours, and special requests.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Available 24/7</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Multilingual Staff</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Local Expertise</span>
                                </div>
                            </div>
                            <div class="service-price">Complimentary Service</div>
                        </div>

                        <!-- Room Service -->
                        <div class="service-card" data-category="dining">
                            <div class="service-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <h3 class="service-title">24-Hour Room Service</h3>
                            <p class="service-description">
                                Enjoy delicious meals and refreshments in the comfort of your room, available 24 hours a day.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>24/7 Service</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Extensive Menu</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Quick Delivery</span>
                                </div>
                            </div>
                            <div class="service-price">From <?= formatCurrency(85000) ?></div>
                        </div>

                        <!-- Laundry -->
                        <div class="service-card" data-category="activities">
                            <div class="service-icon">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <h3 class="service-title">Laundry Service</h3>
                            <p class="service-description">
                                Professional laundry and dry cleaning services with same-day return for your convenience.
                            </p>
                            <div class="service-features">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Same Day Service</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-user"></i>
                                    <span>Eco-Friendly</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-star"></i>
                                    <span>Premium Care</span>
                                </div>
                            </div>
                            <div class="service-price">From <?= formatCurrency(45000) ?>/item</div>
                        </div>
                    </div>
                </div>

                <!-- Dining Services -->
                <div id="dining" class="tab-content">
                    <div class="services-grid">
                        <!-- Restaurant Card (repeat from above) -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h3 class="service-title">Fine Dining Restaurant</h3>
                            <p class="service-description">
                                Experience gourmet cuisine at our award-winning restaurant. Our chefs prepare exquisite dishes using the finest local ingredients.
                            </p>
                            <div class="service-price">From <?= formatCurrency(350000) ?></div>
                        </div>

                        <!-- Room Service Card (repeat from above) -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <h3 class="service-title">24-Hour Room Service</h3>
                            <p class="service-description">
                                Enjoy delicious meals and refreshments in the comfort of your room, available 24 hours a day.
                            </p>
                            <div class="service-price">From <?= formatCurrency(85000) ?></div>
                        </div>

                        <!-- Bar -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-glass-martini-alt"></i>
                            </div>
                            <h3 class="service-title">Sky Lounge Bar</h3>
                            <p class="service-description">
                                Enjoy signature cocktails and premium spirits at our rooftop bar with panoramic city views.
                            </p>
                            <div class="service-price">From <?= formatCurrency(125000) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Spa & Wellness -->
                <div id="spa" class="tab-content">
                    <div class="services-grid">
                        <!-- Spa Card (repeat from above) -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-spa"></i>
                            </div>
                            <h3 class="service-title">Luxury Spa</h3>
                            <p class="service-description">
                                Rejuvenate your mind and body with our exclusive spa treatments. Relax in our tranquil environment with expert therapists.
                            </p>
                            <div class="service-price">From <?= formatCurrency(500000) ?></div>
                        </div>

                        <!-- Massage -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-hands"></i>
                            </div>
                            <h3 class="service-title">Therapeutic Massage</h3>
                            <p class="service-description">
                                Professional massage therapy to relieve stress and tension. Choose from various techniques including Swedish and deep tissue.
                            </p>
                            <div class="service-price">From <?= formatCurrency(350000) ?>/hour</div>
                        </div>

                        <!-- Sauna -->
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-fire"></i>
                            </div>
                            <h3 class="service-title">Sauna & Steam Room</h3>
                            <p class="service-description">
                                Detoxify and relax in our Finnish sauna and steam room facilities. Perfect for post-workout recovery.
                            </p>
                            <div class="service-price">Complimentary for Guests</div>
                        </div>
                    </div>
                </div>

                <!-- Service Request Modal -->
                <div class="modal" id="serviceModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="serviceModalTitle">Book Service</h3>
                            <button class="close-modal" onclick="closeServiceModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="serviceRequestForm">
                                <div class="form-group">
                                    <label class="form-label">Service</label>
                                    <input type="text" id="serviceName" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Date *</label>
                                    <input type="date" name="service_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Time *</label>
                                    <input type="time" name="service_time" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Number of Guests</label>
                                    <input type="number" name="guests" class="form-control" min="1" value="1">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Special Requests</label>
                                    <textarea name="requests" class="form-control" rows="3" placeholder="Any special requirements or preferences..."></textarea>
                                </div>
                                <div style="text-align: center; margin-top: 25px;">
                                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                        <i class="fas fa-paper-plane"></i> Submit Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Tab navigation
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            event.currentTarget.classList.add('active');
            
            // Filter services for specific tabs
            if (tabId !== 'all') {
                document.querySelectorAll('.service-card').forEach(card => {
                    if (card.getAttribute('data-category') === tabId) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            } else {
                document.querySelectorAll('.service-card').forEach(card => {
                    card.style.display = 'flex';
                });
            }
        }

        // Service booking modal
        function bookService(serviceName) {
            document.getElementById('serviceName').value = serviceName;
            document.getElementById('serviceModalTitle').textContent = 'Book: ' + serviceName;
            document.getElementById('serviceModal').style.display = 'flex';
        }

        // Close service modal
        function closeServiceModal() {
            document.getElementById('serviceModal').style.display = 'none';
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeServiceModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('serviceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeServiceModal();
            }
        });

        // Service request form submission
        document.getElementById('serviceRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const serviceName = document.getElementById('serviceName').value;
            
            // Simulate API call
            setTimeout(() => {
                alert(`Service request submitted successfully!\n\nService: ${serviceName}\nDate: ${formData.get('service_date')}\nTime: ${formData.get('service_time')}`);
                closeServiceModal();
                this.reset();
            }, 1000);
        });

        // Add booking buttons to service cards
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.service-card').forEach(card => {
                const title = card.querySelector('.service-title').textContent;
                const price = card.querySelector('.service-price').textContent;
                
                const bookBtn = document.createElement('button');
                bookBtn.className = 'btn btn-primary';
                bookBtn.style.marginTop = '15px';
                bookBtn.innerHTML = '<i class="fas fa-calendar-plus"></i> Book Now';
                bookBtn.onclick = function() { bookService(title); };
                
                card.appendChild(bookBtn);
            });
        });

        function bookService(serviceTitle) {
    let serviceType = "Other";

    const t = serviceTitle.toLowerCase();

    if (t.includes("dining") || t.includes("restaurant") || t.includes("food")) {
        serviceType = "Room Service";
    } else if (t.includes("spa")) {
        serviceType = "Other";
    } else if (t.includes("pool")) {
        serviceType = "Other";
    } else if (t.includes("fitness")) {
        serviceType = "Other";
    }

    window.location.href = "book-service.php?service=" + encodeURIComponent(serviceType);
}


        // Set minimum time for service booking
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const currentHour = now.getHours().toString().padStart(2, '0');
            const currentMinute = now.getMinutes().toString().padStart(2, '0');
            const currentTime = `${currentHour}:${currentMinute}`;
            
            const timeInput = document.querySelector('input[name="service_time"]');
            if (timeInput) {
                timeInput.min = currentTime;
            }
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