<?php
// index.php - HOMEPAGE CUSTOMER PORTAL
session_start();
require_once 'includes/config.php';

// Jika sudah login sebagai admin/resepsionis, redirect ke admin
if (isset($_SESSION['user_id']) && isAdmin()) {
    header('Location: admin/index.php');
    exit();
}

// Jika sudah login sebagai customer, redirect ke dashboard
if (isset($_SESSION['user_id']) && isCustomer()) {
    header('Location: customer/dashboard.php');
    exit();
}

// Jika tidak login, tampilkan homepage
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel_name) ?> - Luxury Hotel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a192f;
            --blue: #4cc9f0;
            --blue-dark: #3a86ff;
            --light: #f8f9fa;
            --dark-bg: #0a192f;
            --gold: #FFD700;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--light);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Header & Navigation */
        .header {
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(76, 201, 240, 0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 20px;
        }

        .logo-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-text p {
            font-size: 0.8rem;
            color: #aaa;
        }

        .nav-menu {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-menu a {
            color: #ccc;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-menu a:hover {
            color: var(--blue);
        }

        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--navy);
        }

        .btn-primary:hover {
            background: #3abde0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
        }

        .btn-secondary:hover {
            background: rgba(76, 201, 240, 0.1);
            transform: translateY(-2px);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(10, 25, 47, 0.9), rgba(10, 25, 47, 0.9)),
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 20px 80px;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #ccc;
            margin-bottom: 30px;
            max-width: 600px;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            margin: 40px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--blue);
            display: block;
        }

        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Booking Widget */
        .booking-widget {
            background: rgba(20, 30, 50, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 1000px;
            margin-top: 40px;
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        .booking-widget h3 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: white;
        }

        .booking-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.15);
        }

        /* Features Section */
        .features {
            padding: 100px 20px;
            background: var(--navy);
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #aaa;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: rgba(20, 30, 50, 0.85);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(76, 201, 240, 0.1);
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--blue);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(76, 201, 240, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 28px;
            color: var(--blue);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #aaa;
        }

        /* Rooms Preview */
        .rooms-preview {
            padding: 100px 20px;
            background: #0c1427;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .room-card {
            background: rgba(20, 30, 50, 0.85);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(76, 201, 240, 0.1);
            transition: all 0.3s;
        }

        .room-card:hover {
            transform: translateY(-10px);
            border-color: var(--blue);
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
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .room-content {
            padding: 25px;
        }

        .room-content h3 {
            font-size: 1.4rem;
            color: white;
            margin-bottom: 10px;
        }

        .room-meta {
            display: flex;
            gap: 15px;
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .room-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Footer */
        .footer {
            background: #0a0f1f;
            padding: 80px 20px 30px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-column h3 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .footer-column p, .footer-column a {
            color: #aaa;
            line-height: 2;
            text-decoration: none;
            display: block;
        }

        .footer-column a:hover {
            color: var(--blue);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            transition: all 0.3s;
        }

        .social-link:hover {
            background: var(--blue);
            color: var(--navy);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: #666;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .nav-menu {
                display: none;
                position: fixed;
                top: 80px;
                left: 0;
                width: 100%;
                background: var(--navy);
                flex-direction: column;
                padding: 20px;
                gap: 15px;
                border-top: 1px solid rgba(76, 201, 240, 0.1);
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .hero-title {
                font-size: 3rem;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 20px;
            }
            
            .booking-form {
                grid-template-columns: 1fr;
            }
            
            .booking-widget {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="logo-text">
                    <h2><?= htmlspecialchars($hotel_name) ?></h2>
                    <p>Luxury Experience</p>
                </div>
            </div>
            
            <nav class="nav-menu" id="navMenu">
                <a href="#"><i class="fas fa-home"></i> Home</a>
                <a href="#rooms"><i class="fas fa-bed"></i> Rooms</a>
                <a href="#features"><i class="fas fa-star"></i> Features</a>
                <a href="login.php"><i class="fas fa-user"></i> Login</a>
                <a href="register.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            </nav>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Experience Luxury Redefined</h1>
            <p class="hero-subtitle">
                Discover unparalleled comfort and world-class service at <?= htmlspecialchars($hotel_name) ?>. 
                Your perfect getaway awaits in the heart of the city.
            </p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number">120+</span>
                    <span class="stat-label">Luxury Rooms</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">4.9</span>
                    <span class="stat-label">Guest Rating</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Concierge</span>
                </div>
            </div>
            
            <a href="login.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                <i class="fas fa-sign-in-alt"></i> Book Your Stay
            </a>
            
            <!-- Booking Widget -->
            <div class="booking-widget" id="bookingWidget">
                <h3>Book Your Stay</h3>
                <form action="customer/new-booking.php" method="GET" class="booking-form">
                    <div class="form-group">
                        <label class="form-label">Check-in Date</label>
                        <input type="date" class="form-control" name="check_in" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Check-out Date</label>
                        <input type="date" class="form-control" name="check_out" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guests</label>
                        <select class="form-control" name="guests">
                            <option value="1">1 Guest</option>
                            <option value="2" selected>2 Guests</option>
                            <option value="3">3 Guests</option>
                            <option value="4">4 Guests</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="height: 48px;">
                            <i class="fas fa-search"></i> Check Availability
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-title">
            <h2>Why Choose Us</h2>
            <p>Experience world-class amenities and service that sets us apart</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3>High-Speed WiFi</h3>
                <p>Stay connected with complimentary high-speed internet access throughout the hotel.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3>Fine Dining</h3>
                <p>Experience gourmet cuisine at our award-winning restaurants and bars.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-spa"></i>
                </div>
                <h3>Spa & Wellness</h3>
                <p>Rejuvenate with our luxury spa treatments and state-of-the-art fitness center.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-swimming-pool"></i>
                </div>
                <h3>Infinity Pool</h3>
                <p>Relax in our stunning rooftop infinity pool with panoramic city views.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-concierge-bell"></i>
                </div>
                <h3>24/7 Concierge</h3>
                <p>Our dedicated concierge team is available round the clock to assist you.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Safety First</h3>
                <p>Your safety is our priority with advanced security systems and protocols.</p>
            </div>
        </div>
    </section>

    <!-- Rooms Preview -->
    <section class="rooms-preview" id="rooms">
        <div class="section-title">
            <h2>Our Luxury Rooms</h2>
            <p>Each room is designed with your comfort and luxury in mind</p>
        </div>
        
        <div class="rooms-grid">
            <div class="room-card">
                <div class="room-image">
                    <div class="room-price"><?= formatCurrency(1500000) ?>/night</div>
                </div>
                <div class="room-content">
                    <h3>Deluxe Suite</h3>
                    <div class="room-meta">
                        <span><i class="fas fa-user"></i> 2 Guests</span>
                        <span><i class="fas fa-bed"></i> King Bed</span>
                        <span><i class="fas fa-expand"></i> 45 m²</span>
                    </div>
                    <p>Spacious suite with panoramic city views, luxury amenities, and premium comfort.</p>
                </div>
            </div>
            
            <div class="room-card">
                <div class="room-image">
                    <div class="room-price"><?= formatCurrency(2500000) ?>/night</div>
                </div>
                <div class="room-content">
                    <h3>Executive Suite</h3>
                    <div class="room-meta">
                        <span><i class="fas fa-user"></i> 3 Guests</span>
                        <span><i class="fas fa-bed"></i> 2 Queen Beds</span>
                        <span><i class="fas fa-expand"></i> 60 m²</span>
                    </div>
                    <p>Luxurious suite with separate living area, executive lounge access, and premium services.</p>
                </div>
            </div>
            
            <div class="room-card">
                <div class="room-image">
                    <div class="room-price"><?= formatCurrency(5000000) ?>/night</div>
                </div>
                <div class="room-content">
                    <h3>Presidential Suite</h3>
                    <div class="room-meta">
                        <span><i class="fas fa-user"></i> 4 Guests</span>
                        <span><i class="fas fa-bed"></i> King & Queen</span>
                        <span><i class="fas fa-expand"></i> 120 m²</span>
                    </div>
                    <p>The ultimate luxury experience with private terrace, butler service, and exclusive amenities.</p>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 50px;">
            <a href="login.php" class="btn btn-primary" style="padding: 15px 50px; font-size: 1.1rem;">
                <i class="fas fa-eye"></i> View All Rooms
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3><?= htmlspecialchars($hotel_name) ?></h3>
                <p>Experience luxury redefined at our premier hotel destination in the heart of the city.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="footer-column">
                <h3>Quick Links</h3>
                <a href="#">Home</a>
                <a href="#rooms">Rooms & Suites</a>
                <a href="#features">Amenities</a>
                <a href="#">Dining</a>
                <a href="#">Spa & Wellness</a>
                <a href="#">Events</a>
            </div>
            
            <div class="footer-column">
                <h3>Contact Info</h3>
                <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($hotel_address) ?></p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($hotel_phone) ?></p>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($hotel_email) ?></p>
            </div>
            
            <div class="footer-column">
                <h3>Newsletter</h3>
                <p>Subscribe to get special offers and updates</p>
                <form style="margin-top: 15px;">
                    <input type="email" class="form-control" placeholder="Your email" style="margin-bottom: 10px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Subscribe</button>
                </form>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($hotel_name) ?>. All rights reserved.
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('navMenu').classList.toggle('active');
        });

        // Close mobile menu on click outside
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const mobileBtn = document.getElementById('mobileMenuBtn');
            
            if (!navMenu.contains(event.target) && !mobileBtn.contains(event.target)) {
                navMenu.classList.remove('active');
            }
        });

        // Set minimum checkout date
        const checkInInput = document.querySelector('input[name="check_in"]');
        const checkOutInput = document.querySelector('input[name="check_out"]');
        
        if (checkInInput && checkOutInput) {
            checkInInput.addEventListener('change', function() {
                const checkInDate = new Date(this.value);
                checkInDate.setDate(checkInDate.getDate() + 1);
                const minCheckOut = checkInDate.toISOString().split('T')[0];
                checkOutInput.min = minCheckOut;
                
                if (checkOutInput.value && checkOutInput.value <= this.value) {
                    checkOutInput.value = minCheckOut;
                }
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(10, 25, 47, 0.98)';
            } else {
                header.style.background = 'rgba(10, 25, 47, 0.95)';
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