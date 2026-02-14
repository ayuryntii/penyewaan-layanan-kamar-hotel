<?php
session_start();
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel_name) ?> – Luxury Hotel Experience</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-hover: #3abde0;
            --light: #f8f9fa;
            --gray: #6c757d;
            --dark-bg: rgba(10, 25, 47, 0.95);
            --card-bg: rgba(20, 30, 50, 0.85);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--light);
            overflow-x: hidden;
        }

        /* === Navigation === */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 15px 5%;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: var(--blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 20px;
        }

        .logo-text {
            color: white;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links a {
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            font-size: 1.05rem;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--blue);
        }

        .btn-login {
            background: var(--blue);
            color: var(--navy);
            padding: 10px 28px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-login:hover {
            background: var(--blue-hover);
            transform: translateY(-2px);
        }

        /* === User Menu in Header === */
        .user-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(10, 25, 47, 0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            cursor: pointer;
            position: relative;
        }

        .user-menu-header:hover {
            background: rgba(76, 201, 240, 0.1);
        }

        .user-menu-header .user-avatar {
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

        .user-menu-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-menu-header .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-menu-header .user-role {
            font-size: 0.8rem;
            color: #aaa;
        }

        .logout-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(20, 30, 50, 0.85);
            border: 1px solid rgba(76, 201, 240, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .logout-menu.show {
            display: block;
        }

        .logout-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-menu a:hover {
            background: rgba(76, 201, 240, 0.1);
            color: var(--blue);
        }

        /* === Hero Carousel === */
        .hero-carousel {
            position: relative;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5%;
        }

        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1s ease;
            z-index: 0;
        }

        .hero-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 25, 47, 0.7);
            z-index: 1;
        }

        .hero-slide.active {
            opacity: 1;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            text-align: center;
            color: white;
            animation: fadeInUp 1s ease;
        }

        .hero-content h1 {
            font-size: 4.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.15;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
        }

        .hero-content p {
            font-size: 1.25rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 35px;
            max-width: 650px;
        }

        .hero-cta {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }

        .btn-hero {
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-hero-primary {
            background: var(--blue);
            color: var(--navy);
            border: none;
        }

        .btn-hero-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(76, 201, 240, 0.3);
        }

        .btn-hero-secondary:hover {
            background: white;
            color: var(--navy);
        }

        .hero-indicators {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }

        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: background 0.3s;
        }

        .indicator.active {
            background: white;
        }

        /* === Sections === */
        .section {
            padding: 100px 5%;
            background: var(--dark-bg);
        }

        .section-title {
            text-align: center;
            margin-bottom: 70px;
        }

        .section-title h2 {
            font-size: 2.8rem;
            color: white;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #aaa;
            font-size: 1.1rem;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* === Features === */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 35px;
        }

        .feature-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            border: 1px solid rgba(76, 201, 240, 0.1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--blue), #3a86ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 32px;
        }

        .feature-card h3 {
            font-size: 1.45rem;
            margin-bottom: 15px;
            color: white;
        }

        .feature-card p {
            color: #bbb;
            line-height: 1.6;
        }

        /* === Rooms === */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .room-card {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            border: 1px solid rgba(76, 201, 240, 0.1);
        }

        .room-card:hover {
            transform: translateY(-10px);
        }

        .room-img {
            height: 240px;
            position: relative;
            display: flex;
            align-items: flex-end;
            padding: 20px;
            color: white;
            background-size: cover;
            background-position: center;
        }

        .room-img::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 25, 47, 0.6);
            z-index: 1;
        }

        .room-price {
            background: rgba(255, 255, 255, 0.9);
            color: var(--navy);
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.1rem;
            z-index: 2;
            position: relative;
        }

        .room-content {
            padding: 28px;
        }

        .room-content h3 {
            font-size: 1.4rem;
            margin-bottom: 12px;
            color: white;
        }

        .room-meta {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
            font-size: 0.95rem;
            color: #bbb;
        }

        .room-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .room-btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-book {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-details {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-book:hover {
            background: var(--blue-hover);
        }

        .btn-details:hover {
            background: rgba(255,255,255,0.2);
        }

        /* === Booking CTA === */
        .booking-section {
            background: linear-gradient(135deg, var(--navy), #112240);
            color: white;
            padding: 100px 5%;
            position: relative;
        }

        .booking-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            border: 1px solid rgba(76, 201, 240, 0.1);
        }

        .booking-container .section-title {
            margin-bottom: 40px;
        }

        .booking-container .section-title h2 {
            color: white;
        }

        .booking-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .submit-btn {
            grid-column: 1 / -1;
            background: var(--blue);
            color: var(--navy);
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            background: var(--blue-hover);
            transform: translateY(-2px);
        }

        /* === Footer === */
        footer {
            background: var(--navy);
            color: #aaa;
            padding: 80px 5% 30px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }

        .footer-col h3 {
            color: var(--blue);
            font-size: 1.4rem;
            margin-bottom: 25px;
        }

        .footer-links a {
            display: block;
            color: #bbb;
            margin-bottom: 12px;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--blue);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: var(--blue);
            color: var(--navy);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
            color: #888;
        }

        /* === Select Dropdown Style === */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z' fill='%23666'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
            color: white;
        }

        select.form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
            background: rgba(255,255,255,0.1);
        }

        select.form-control option {
            color: #333;
            background: white;
            padding: 8px 15px;
        }

        select.form-control option:checked {
            background: var(--blue);
            color: white;
        }

        select.form-control option:hover {
            background: rgba(76, 201, 240, 0.1);
            color: #333;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2.5rem; }
            .hero-cta { flex-direction: column; }
            .btn-hero { width: 100%; justify-content: center; }
            .section { padding: 70px 5%; }
            
            /* Mobile user menu */
            .user-menu-header {
                padding: 8px 12px;
            }
            
            .logout-menu {
                min-width: 150px;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 { font-size: 2.1rem; }
            .section-title h2 { font-size: 2.2rem; }
            .booking-container { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav>
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-icon"><i class="fas fa-hotel"></i></div>
                <span class="logo-text"><?= htmlspecialchars($hotel_name) ?></span>
            </a>
            <div class="nav-links">
                <a href="#home">Home</a>
                <a href="#rooms">Rooms</a>
                <a href="#amenities">Amenities</a>
                <a href="#contact">Contact</a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- User Menu with Logout -->
                    <div class="user-menu-header" id="userMenuHeader">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
                            <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'guest') ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #aaa;"></i>
                    </div>
                    
                    <!-- Logout Menu -->
                    <div class="logout-menu" id="logoutMenu">
                        <?php if (isAdmin()): ?>
                        <a href="admin/index.php">
                            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                        </a>
                        <?php else: ?>
                        <a href="dashboard.php">
                            <i class="fas fa-user"></i> My Dashboard
                        </a>
                        <?php endif; ?>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Carousel -->
    <section class="hero-carousel" id="home">
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1596394516024-75ae04e6a3cc?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');"></div>

        <div class="hero-content">
            <h1>Serenity Meets Sophistication</h1>
            <p>Welcome to <?= htmlspecialchars($hotel_name) ?> — where modern elegance meets timeless comfort in the heart of the city.</p>
            
            <div class="hero-cta">
                <a href="#booking" class="btn-hero btn-hero-primary">
                    <i class="fas fa-calendar-check"></i> Book Now
                </a>
                <a href="#rooms" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-eye"></i> Explore Rooms
                </a>
            </div>
        </div>

        <div class="hero-indicators">
            <div class="indicator active" data-slide="0"></div>
            <div class="indicator" data-slide="1"></div>
            <div class="indicator" data-slide="2"></div>
        </div>
    </section>

    <!-- Amenities -->
    <section class="section" id="amenities">
        <div class="section-title">
            <h2>Why Choose Our Hotel</h2>
            <p>Experience the perfect blend of luxury, comfort, and exceptional service</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-crown"></i></div>
                <h3>Luxury Accommodation</h3>
                <p>Spacious rooms with premium amenities, elegant decor, and breathtaking views for ultimate comfort.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-utensils"></i></div>
                <h3>Fine Dining</h3>
                <p>Multiple restaurants offering international cuisine prepared by award-winning chefs.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-spa"></i></div>
                <h3>Spa & Wellness</h3>
                <p>Rejuvenate your senses with our world-class spa treatments and wellness facilities.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-concierge-bell"></i></div>
                <h3>Personalized Service</h3>
                <p>Dedicated concierge and 24/7 room service to cater to your every need.</p>
            </div>
        </div>
    </section>

    <!-- Rooms -->
    <section class="section" id="rooms">
        <div class="section-title">
            <h2>Our Luxury Rooms</h2>
            <p>Experience comfort in our carefully designed rooms and suites</p>
        </div>
        
        <div class="rooms-grid">
            <?php
            $rooms_query = "SELECT r.*, rc.name as category_name, rc.base_price, rc.description as category_desc, rc.max_capacity 
                           FROM rooms r 
                           JOIN room_categories rc ON r.category_id = rc.id 
                           WHERE r.status = 'available' 
                           LIMIT 4";
            $rooms_result = $conn->query($rooms_query);
            
            while ($room = $rooms_result->fetch_assoc()):
                // Parse JSON images (SAFE)
                $roomImages = json_decode($room['images'] ?? '[]', true);
                if (!is_array($roomImages)) {
                    $roomImages = [];
         }

    $firstImage = $roomImages[0] ?? null;

                // Fallback placeholder
                $placeholder = 'https://images.unsplash.com/photo-1596394516024-75ae04e6a3cc?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';
                $imageUrl = $firstImage ? htmlspecialchars($firstImage) : $placeholder;
            ?>
            <div class="room-card">
                <div class="room-img" style="background-image: url('<?= $imageUrl ?>');">
                    <div class="room-price">
                        <?= formatCurrency($room['base_price']) ?>/night
                    </div>
                </div>
                <div class="room-content">
                    <h3><?= htmlspecialchars($room['category_name']) ?></h3>
                    <p style="color: #bbb; margin-bottom: 20px; line-height: 1.6;">
                        <?= htmlspecialchars(substr($room['category_desc'], 0, 100)) ?>...
                    </p>
                    
                    <div class="room-meta">
                        <span><i class="fas fa-user-friends"></i> <?= $room['max_capacity'] ?> Guests</span>
                        <span><i class="fas fa-bed"></i> <?= ucfirst($room['bed_type']) ?> Bed</span>
                        <span><i class="fas fa-mountain"></i> <?= ucfirst($room['view_type']) ?> View</span>
                    </div>
                    
                    <div class="room-actions">
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="bookings.php?action=add" class="room-btn btn-book">Book Now</a>
                        <?php else: ?>
                        <a href="login.php" class="room-btn btn-book">Book Now</a>
                        <?php endif; ?>
                        <a href="#rooms" class="room-btn btn-details">Details</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- Booking Section -->
    <section class="booking-section" id="booking">
        <div class="section-title">
            <h2>Book Your Stay</h2>
            <p>Find the perfect room for your next getaway</p>
        </div>
        
        <div class="booking-container">
            <form action="<?= isset($_SESSION['user_id']) ? 'bookings.php?action=add' : 'login.php' ?>" method="GET">
                <div class="booking-form">
                    <div class="form-group">
                        <label>Check-in Date</label>
                        <input type="date" class="form-control" name="check_in" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Check-out Date</label>
                        <input type="date" class="form-control" name="check_out" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Guests</label>
                        <select class="form-control" name="guests" required>
                            <option value="1">1 Guest</option>
                            <option value="2" selected>2 Guests</option>
                            <option value="3">3 Guests</option>
                            <option value="4">4 Guests</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Room Type</label>
                        <select class="form-control" name="room_type" required>
                            <?php
                            $categories = $conn->query("SELECT * FROM room_categories");
                            while ($cat = $categories->fetch_assoc()) {
                                echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . ' (' . formatCurrency($cat['base_price']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-search"></i> Check Availability & Book
                </button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-col">
                <h3><?= htmlspecialchars($hotel_name) ?></h3>
                <p>Experience luxury and comfort at its finest. Your perfect getaway starts here.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-tripadvisor"></i></a>
                </div>
            </div>
            
            <div class="footer-col">
                <h3>Quick Links</h3>
                <div class="footer-links">
                    <a href="#home">Home</a>
                    <a href="#rooms">Rooms & Suites</a>
                    <a href="#amenities">Amenities</a>
                    <a href="#booking">Reservations</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= isAdmin() ? 'admin/index.php' : 'dashboard.php'; ?>">Dashboard</a>
                    <?php else: ?>
                    <a href="login.php">Guest Portal</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="footer-col">
                <h3>Contact Info</h3>
                <div class="footer-links">
                    <p><i class="fas fa-map-marker-alt" style="margin-right:8px;color:var(--blue);"></i> <?= htmlspecialchars($hotel_address) ?></p>
                    <p><i class="fas fa-phone" style="margin-right:8px;color:var(--blue);"></i> <?= htmlspecialchars($hotel_phone) ?></p>
                    <p><i class="fas fa-envelope" style="margin-right:8px;color:var(--blue);"></i> <?= htmlspecialchars($hotel_email) ?></p>
                </div>
            </div>
            
            <div class="footer-col">
                <h3>Awards</h3>
                <div class="footer-links">
                    <p>🏆 Luxury Hotel of the Year 2024</p>
                    <p>⭐ 5-Star Rating – TripAdvisor</p>
                    <p>🥇 Best Service – Travel & Leisure</p>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($hotel_name) ?>. All rights reserved.
        </div>
    </footer>

    <script>
        // Hero Carousel
        const slides = document.querySelectorAll('.hero-slide');
        const indicators = document.querySelectorAll('.indicator');
        let currentSlide = 0;

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
            currentSlide = index;
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        // Auto slide every 5 seconds
        setInterval(nextSlide, 5000);

        // Manual navigation
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => showSlide(index));
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Toggle Logout Menu
        const userMenuHeader = document.getElementById('userMenuHeader');
        const logoutMenu = document.getElementById('logoutMenu');
        
        if (userMenuHeader && logoutMenu) {
            userMenuHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                logoutMenu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenuHeader.contains(e.target) && !logoutMenu.contains(e.target)) {
                    logoutMenu.classList.remove('show');
                }
            });
        }

        // Set default dates
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.querySelector('[name="check_in"]').valueAsDate = today;
        document.querySelector('[name="check_out"]').valueAsDate = tomorrow;
    </script>
</body>
</html>