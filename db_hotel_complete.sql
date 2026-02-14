-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 29 Jan 2026 pada 05.41
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_hotel_complete`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'checkin', 'Checked in booking #BOOK2026012621211761 for room ', NULL, NULL, '2026-01-26 14:21:21'),
(2, 2, 'checkin', 'Checked in booking #BOOK2026012621331785 for room ', NULL, NULL, '2026-01-26 14:33:21'),
(3, 2, 'checkin', 'Checked in booking #BOOK2026012621180569 for room 203', NULL, NULL, '2026-01-26 15:56:05'),
(4, 2, 'checkin', 'Checked in booking #BK20260127A1A8 for room 303', NULL, NULL, '2026-01-27 04:40:04'),
(5, 2, 'checkin', 'Checked in booking #BOOK2026012711533327 for room ', NULL, NULL, '2026-01-27 04:53:42'),
(6, 2, 'confirm_booking', 'Confirmed booking #BK20260127D6BE for room 501', NULL, NULL, '2026-01-27 05:50:49'),
(7, 2, 'checkin', 'Checked in booking #BK20260127D6BE to room 501', NULL, NULL, '2026-01-27 05:51:09'),
(8, 2, 'confirm_booking', 'Confirmed booking #BK20260127657B for room 301', NULL, NULL, '2026-01-27 06:07:09'),
(9, 2, 'confirm_booking', 'Confirmed booking #BK20260127EAB6 for room 302', NULL, NULL, '2026-01-27 06:22:58'),
(10, 2, 'checkin', 'Checked in booking #BK20260127EAB6 for room ', NULL, NULL, '2026-01-27 06:55:48'),
(11, 2, 'checkin', 'Checked in booking #BK20260127657B for room ', NULL, NULL, '2026-01-27 06:56:38'),
(12, 2, 'confirm_booking', 'Confirmed booking #BK2026012735AB for room 203', NULL, NULL, '2026-01-27 07:05:13'),
(13, 2, 'checkin', 'Checked in booking #BK2026012735AB for room ', NULL, NULL, '2026-01-27 07:05:38'),
(14, 2, 'confirm_booking', 'Confirmed booking #BK2026012777D3 for room 303', NULL, NULL, '2026-01-27 07:25:20'),
(15, 2, 'checkout', 'Checked out booking #BOOK2026012621180569 from room 203', NULL, NULL, '2026-01-27 07:26:03'),
(16, 2, 'confirm_booking', 'Confirmed booking #BK2026012749A8 for room 501', NULL, NULL, '2026-01-27 08:01:26'),
(17, 2, 'checkout', 'Checked out booking #BK20260127EAB6 from room 302', NULL, NULL, '2026-01-27 08:07:43'),
(18, 2, 'confirm_booking', 'Confirmed booking #BK20260129FB42 for room 203', NULL, NULL, '2026-01-29 04:27:50'),
(19, 2, 'checkin', 'Checked in booking #BOOK2026012911293699 for room ', NULL, NULL, '2026-01-29 04:31:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `total_nights` int(11) NOT NULL,
  `adults` int(11) DEFAULT 1,
  `children` int(11) DEFAULT 0,
  `total_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `booking_status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'pending',
  `payment_status` enum('pending','partial','paid','refunded','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `room_id`, `check_in`, `check_out`, `total_nights`, `adults`, `children`, `total_price`, `discount_amount`, `final_price`, `special_requests`, `booking_status`, `payment_status`, `payment_method`, `payment_proof`, `created_at`, `updated_at`) VALUES
(2, 'BK20260126772D', 3, 9, '2026-01-26', '2026-01-29', 3, 3, 2, 4500000.00, 0.00, 4500000.00, '', 'pending', 'pending', NULL, NULL, '2026-01-26 09:56:55', '2026-01-26 09:56:55'),
(4, 'BOOK2026012621211761', 3, 8, '2026-01-26', '2026-01-27', 1, 2, 0, 1200000.00, 0.00, 1200000.00, '', 'checked_out', 'pending', 'cash', NULL, '2026-01-26 14:21:17', '2026-01-26 14:28:01'),
(6, 'BK20260127EAB6', 7, 8, '2026-01-27', '2026-01-28', 1, 2, 1, 1200000.00, 0.00, 1200000.00, '', 'checked_out', 'paid', 'transfer', NULL, '2026-01-27 04:23:26', '2026-01-27 08:07:43'),
(7, 'BK20260127A1A8', 7, 15, '2026-01-27', '2026-01-28', 1, 2, 2, 1200000.00, 0.00, 1200000.00, '', 'checked_in', 'paid', 'transfer', NULL, '2026-01-27 04:26:18', '2026-01-27 04:40:04'),
(8, 'BK20260127D6BE', 7, 10, '2026-01-27', '2026-01-28', 1, 2, 0, 3500000.00, 0.00, 3500000.00, '', 'checked_in', 'paid', NULL, NULL, '2026-01-27 04:50:53', '2026-01-27 08:06:36'),
(9, 'BOOK2026012711533327', 3, 7, '2026-01-27', '2026-01-28', 1, 2, 0, 1200000.00, 0.00, 1200000.00, '', 'checked_out', 'pending', 'cash', NULL, '2026-01-27 04:53:33', '2026-01-27 05:07:28'),
(14, 'BK2026012749A8', 7, 10, '2026-01-30', '2026-01-31', 1, 3, 0, 3500000.00, 0.00, 3500000.00, '', 'confirmed', 'paid', 'cash', NULL, '2026-01-27 08:00:20', '2026-01-27 08:01:26'),
(15, 'BK20260129FB42', 7, 6, '2026-01-30', '2026-01-31', 1, 3, 0, 750000.00, 0.00, 750000.00, '', 'confirmed', 'paid', 'cash', NULL, '2026-01-29 04:25:35', '2026-01-29 04:27:50'),
(16, 'BOOK2026012911293699', 3, 7, '2026-01-29', '2026-01-30', 1, 2, 0, 1200000.00, 0.00, 1200000.00, '', 'checked_in', 'paid', 'cash', NULL, '2026-01-29 04:29:36', '2026-01-29 04:31:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking_services`
--

CREATE TABLE `booking_services` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `payment_code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','e-wallet') NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `category_id` int(11) NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `view_type` enum('city','garden','pool','mountain','sea') DEFAULT 'city',
  `bed_type` enum('single','double','queen','king','twin') DEFAULT 'double',
  `smoking` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `status` enum('available','occupied','maintenance','cleaning','reserved') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `category_id`, `floor`, `view_type`, `bed_type`, `smoking`, `description`, `image`, `images`, `status`, `created_at`) VALUES
(6, '203', 2, '2', 'city', 'queen', 0, 'dinginn', 'room_1769482464_4181.jpg', 'room_1769456287_2004.jpg', 'reserved', '2026-01-20 06:50:07'),
(7, '301', 3, '3', 'city', 'king', 0, 'dinginn', 'room_1769458397_1799.jpg', NULL, 'occupied', '2026-01-20 06:50:07'),
(8, '302', 3, '3', 'sea', 'king', 0, '', NULL, NULL, 'available', '2026-01-20 06:50:07'),
(9, '401', 4, '4', 'city', 'twin', 0, '', NULL, NULL, 'available', '2026-01-20 06:50:07'),
(10, '501', 5, '5', 'mountain', 'king', 0, '', NULL, NULL, 'available', '2026-01-20 06:50:07'),
(12, '305', 4, '4', 'pool', 'queen', 0, '', NULL, NULL, 'available', '2026-01-24 10:23:53'),
(15, '303', 3, '2', 'city', 'double', 1, '', NULL, 'room_1769456330_9536.jpg', 'available', '2026-01-26 19:38:50'),
(16, '709', 4, '3', 'pool', 'king', 1, 'kamar dirimu', 'room_1769478573_1636.jpg', NULL, 'available', '2026-01-27 01:49:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `room_categories`
--

CREATE TABLE `room_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `max_capacity` int(11) DEFAULT 2,
  `amenities` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `room_categories`
--

INSERT INTO `room_categories` (`id`, `name`, `description`, `base_price`, `max_capacity`, `amenities`, `image`, `created_at`) VALUES
(2, 'Deluxe Room', 'Spacious room with premium amenities', 750000.00, 3, 'AC, TV, WiFi, Bathub, Mini Bar', 'deluxe-room.jpg', '2026-01-20 06:50:07'),
(3, 'Executive Suite', 'Luxury suite with separate living area', 1200000.00, 2, 'AC, Smart TV, WiFi, Jacuzzi, Work Desk', 'executive-suite.jpg', '2026-01-20 06:50:07'),
(4, 'Family Room', 'Perfect for families with children', 1500000.00, 4, 'AC, 2x TV, WiFi, Kitchenette', 'family-room.jpg', '2026-01-20 06:50:07'),
(5, 'Presidential Suite', 'Ultimate luxury experience', 3500000.00, 3, 'AC, Smart TV, WiFi, Private Pool, Butler Service', 'presidential-suite.jpg', '2026-01-20 06:50:07'),
(6, 'kamarmu', '', 99999999.99, 2, 'Ac sangat amat dinginnn', '1769339052_6975f8ace33ff.jpg', '2026-01-25 11:04:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'per item',
  `category` enum('food','beverage','laundry','transport','spa','other') DEFAULT 'other',
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `unit`, `category`, `is_available`, `created_at`) VALUES
(1, 'Dinner', 'Dinner', 0.06, 'per item', 'food', 1, '2026-01-23 16:22:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `service_requests`
--

CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `service_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `room_number` varchar(10) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `service_requests`
--

INSERT INTO `service_requests` (`id`, `user_id`, `booking_id`, `service_type`, `description`, `priority`, `status`, `room_number`, `completed_by`, `completed_at`, `created_at`, `updated_at`) VALUES
(2, 3, NULL, 'Room Service', 'service', 'medium', 'pending', NULL, NULL, NULL, '2026-01-26 18:45:11', '2026-01-26 18:45:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `created_at`, `updated_at`) VALUES
(1, 'hotel_name', 'Grand Luxury Hotel', 'general', 'Hotel name', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(2, 'hotel_address', 'Jl. Sudirman No. 123, Jakarta', 'general', 'Hotel address', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(3, 'hotel_phone', '(021) 1234-5678', 'general', 'Hotel phone number', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(4, 'hotel_email', 'info@grandhotel.com', 'general', 'Hotel email', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(5, 'checkin_time', '14:00', 'booking', 'Check-in time', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(6, 'checkout_time', '12:00', 'booking', 'Check-out time', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(7, 'currency', 'IDR', 'finance', 'Currency', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(8, 'tax_rate', '10', 'finance', 'Tax percentage', '2026-01-20 06:27:10', '2026-01-20 06:27:10'),
(9, 'tax_percentage', '10', 'finance', 'Tax percentage', '2026-01-20 06:50:07', '2026-01-20 06:50:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('admin','receptionist','customer') DEFAULT 'customer',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `phone`, `profile_picture`, `role`, `status`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$G1qGIog/klfhzZpokGc/eOikyV7xpDznwjVciESpW2PcW8m5Nzk7y', 'admin@hotel.com', 'Administrator', NULL, NULL, 'admin', 'active', '2026-01-20 06:27:10', '2026-01-29 04:27:24'),
(2, 'receptionist', '$2y$10$G1qGIog/klfhzZpokGc/eOikyV7xpDznwjVciESpW2PcW8m5Nzk7y', 'reception@hotel.com', 'ayyy', '081234567891', NULL, 'receptionist', 'active', '2026-01-20 06:50:07', '2026-01-29 04:27:39'),
(3, 'ayuuu', '$2y$10$G1qGIog/klfhzZpokGc/eOikyV7xpDznwjVciESpW2PcW8m5Nzk7y', 'ayuuu@hotel.com', 'ayuuu', '081234567892', NULL, 'customer', 'active', '2026-01-20 06:50:07', '2026-01-26 20:19:34'),
(6, 'adminn', '$2y$10$IFVBn7f2m61Lc1jSiWJXV.lphzYZrC1qU1m5aRKHZyxMF4zg.RdQG', 'ayuu@gmail.com', 'ayuu', '0895358072344', NULL, '', 'active', '2026-01-25 09:47:02', NULL),
(7, 'customer', '$2a$12$/1ybbka7xlEU9Gk8jfUvt.lv/LUES9N5JqUz0J6xP6t5WjvD8wIn2', 'fikri@company.com', 'fikri', '0895358072789', NULL, 'customer', 'active', '2026-01-26 15:09:54', '2026-01-29 04:24:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Jakarta',
  `currency` varchar(10) NOT NULL DEFAULT 'IDR',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `language`, `timezone`, `currency`, `created_at`, `updated_at`) VALUES
(1, 1, 'en', 'Asia/Jakarta', 'IDR', '2026-01-26 19:12:31', '2026-01-26 19:12:31'),
(2, 6, 'en', 'Asia/Jakarta', 'IDR', '2026-01-26 19:12:31', '2026-01-26 19:12:31'),
(3, 3, 'id', 'Asia/Jakarta', 'IDR', '2026-01-26 19:12:31', '2026-01-26 20:23:18'),
(4, 7, 'en', 'Asia/Jakarta', 'IDR', '2026-01-26 19:12:31', '2026-01-26 19:12:31'),
(5, 2, 'en', 'Asia/Jakarta', 'IDR', '2026-01-26 19:12:31', '2026-01-26 19:12:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `promo_notifications` tinyint(1) DEFAULT 1,
  `dark_mode` tinyint(1) DEFAULT 1,
  `language` varchar(10) DEFAULT 'id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `newsletter` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `email_notifications`, `sms_notifications`, `promo_notifications`, `dark_mode`, `language`, `created_at`, `updated_at`, `newsletter`) VALUES
(1, 1, 1, 0, 1, 1, 'id', '2026-01-26 19:09:37', '2026-01-26 19:09:37', 0),
(2, 6, 1, 0, 1, 1, 'id', '2026-01-26 19:09:37', '2026-01-26 19:09:37', 0),
(3, 3, 1, 0, 1, 1, 'id', '2026-01-26 19:09:37', '2026-01-26 20:23:13', 1),
(4, 7, 1, 0, 1, 1, 'id', '2026-01-26 19:09:37', '2026-01-26 19:09:37', 0),
(5, 2, 1, 0, 1, 1, 'id', '2026-01-26 19:09:37', '2026-01-26 19:09:37', 0);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indeks untuk tabel `booking_services`
--
ALTER TABLE `booking_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indeks untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_code` (`payment_code`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indeks untuk tabel `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `room_number_idx` (`room_number`),
  ADD KEY `status_idx` (`status`),
  ADD KEY `category_idx` (`category_id`);

--
-- Indeks untuk tabel `room_categories`
--
ALTER TABLE `room_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name_idx` (`name`);

--
-- Indeks untuk tabel `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `username_idx` (`username`),
  ADD KEY `email_idx` (`email`);

--
-- Indeks untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_preferences_user` (`user_id`);

--
-- Indeks untuk tabel `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `booking_services`
--
ALTER TABLE `booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `room_categories`
--
ALTER TABLE `room_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Ketidakleluasaan untuk tabel `booking_services`
--
ALTER TABLE `booking_services`
  ADD CONSTRAINT `booking_services_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Ketidakleluasaan untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `room_categories` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_requests_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
