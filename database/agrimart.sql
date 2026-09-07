-- AgriMart Database Schema & Seed Data
-- Digital Market Platform on Agricultural Products
-- Generated for AgriMart Capstone Project

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `agrimart`
--

-- --------------------------------------------------------
-- Drop existing tables
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `cart`;
DROP TABLE IF EXISTS `equipment`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `id_type` varchar(100) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `id_card_image` varchar(255) DEFAULT NULL,
  `is_verified` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `addresses`
-- --------------------------------------------------------
CREATE TABLE `addresses` (
  `address_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(150) NOT NULL,
  `city_municipality` varchar(150) DEFAULT NULL,
  `province` varchar(150) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `fk_address_user` (`user_id`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_type` enum('seed','equipment') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `unique_category` (`category_name`,`category_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------
CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT 'kg',
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','sold') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `idx_product_user` (`user_id`),
  KEY `idx_product_category` (`category_id`),
  KEY `idx_product_name` (`product_name`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_product_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `equipment`
-- --------------------------------------------------------
CREATE TABLE `equipment` (
  `equipment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `equipment_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `rate_type` enum('hourly','daily') NOT NULL DEFAULT 'daily',
  `rate_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `availability` enum('available','rented','maintenance') NOT NULL DEFAULT 'available',
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`equipment_id`),
  KEY `idx_equipment_user` (`user_id`),
  KEY `idx_equipment_category` (`category_id`),
  KEY `idx_equipment_name` (`equipment_name`),
  CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `cart`
-- --------------------------------------------------------
CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `cart_items`
-- --------------------------------------------------------
CREATE TABLE `cart_items` (
  `cart_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `unique_cart_product` (`cart_id`,`product_id`),
  KEY `fk_cartitem_product` (`product_id`),
  CONSTRAINT `fk_cartitem_cart` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cartitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------
CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_address` varchar(500) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_order_buyer` (`buyer_id`),
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------
CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `fk_orderitem_product` (`product_id`),
  KEY `fk_orderitem_seller` (`seller_id`),
  KEY `idx_order_item_order` (`order_id`),
  CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `bookings`
-- --------------------------------------------------------
CREATE TABLE `bookings` (
  `booking_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` int(10) UNSIGNED NOT NULL,
  `renter_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `pickup_location` varchar(255) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','confirmed','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`booking_id`),
  KEY `idx_booking_renter` (`renter_id`),
  KEY `idx_booking_owner` (`owner_id`),
  KEY `idx_booking_equipment` (`equipment_id`),
  CONSTRAINT `fk_booking_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_renter` FOREIGN KEY (`renter_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `payments`
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `payment_method` enum('cash','gcash','maya','bank_transfer') NOT NULL DEFAULT 'cash',
  `buyer_bank_name` varchar(100) DEFAULT NULL,
  `buyer_account_name` varchar(150) DEFAULT NULL,
  `buyer_account_number` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(150) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_order` (`order_id`),
  KEY `fk_payment_booking` (`booking_id`),
  KEY `idx_payment_user` (`user_id`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------
CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `equipment_id` int(10) UNSIGNED DEFAULT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `fk_review_product` (`product_id`),
  KEY `fk_review_equipment` (`equipment_id`),
  KEY `fk_review_order` (`order_id`),
  KEY `fk_review_booking` (`booking_id`),
  KEY `idx_review_user` (`reviewer_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`equipment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_user` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('order','booking','payment','system') NOT NULL DEFAULT 'system',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_user` (`user_id`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- SEED DATA
-- --------------------------------------------------------

-- Default Categories (Seed & Equipment)
INSERT INTO `categories` (`category_id`, `category_name`, `category_type`) VALUES
(1, 'Grain & Cereal Seeds', 'seed'),
(2, 'Vegetable Seeds', 'seed'),
(3, 'Fruit Seeds & Seedlings', 'seed'),
(4, 'Root Crop Seeds', 'seed'),
(5, 'Organic Fertilizers & Soils', 'seed'),
(6, 'Tractors & Tillers', 'equipment'),
(7, 'Harvesters & Threshers', 'equipment'),
(8, 'Irrigation & Water Pumps', 'equipment'),
(9, 'Planters & Sprayers', 'equipment'),
(10, 'Hand Tools & Farm Implements', 'equipment');

-- Default Users
-- admin@agrimart.com (password: admin12345)
-- juan@agrimart.com (password: password123)
-- pedro@agrimart.com (password: password123)
-- maria@agrimart.com (password: password123)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
(1, 'AgriMart Administrator', 'admin@agrimart.com', '09171234567', '$2y$10$G2txDeLF3w/fs3XlBj5s5O2FhtVw49udbmyRPno80VKVW0xj3s8ZC', 'admin', 'active'),
(2, 'Juan dela Cruz', 'juan@agrimart.com', '09189876543', '$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS', 'user', 'active'),
(3, 'Pedro Penduko', 'pedro@agrimart.com', '09205554321', '$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS', 'user', 'active'),
(4, 'Maria Santos', 'maria@agrimart.com', '09998887766', '$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS', 'user', 'active'),
(5, 'Christian Lucero', 'christianlucero522@gmail.com', '09999704103', '$2y$10$xvfVo3HPDhJsLrbY/tpoGOCs0eW2cbHplMC/nGnM4/ww8WGPNRF8.', 'user', 'active');

-- Sample Addresses
INSERT INTO `addresses` (`address_id`, `user_id`, `street`, `barangay`, `city_municipality`, `province`, `postal_code`) VALUES
(1, 2, 'Purok 3, Farm Road', 'San Jose', 'San Fernando', 'Pampanga', '2000'),
(2, 3, 'Block 5 Lot 12, Agro Village', 'Santa Cruz', 'Tarlac City', 'Tarlac', '2300'),
(3, 4, '14 Green Valley St.', 'Maligaya', 'Cabanatuan City', 'Nueva Ecija', '3100'),
(4, 5, 'Maharlika Highway', 'Poblacion', 'Urdaneta City', 'Pangasinan', '2428');

-- Sample Products (Seeds & Agricultural Goods)
INSERT INTO `products` (`product_id`, `user_id`, `category_id`, `product_name`, `description`, `price`, `quantity`, `unit`, `image_url`, `status`) VALUES
(1, 2, 1, 'Certified Inbred RC-222 Rice Seeds', 'High-yielding inbred rice variety resistant to pests and suitable for wet and dry planting seasons.', 1250.00, 85, 'sack (20kg)', 'images/product-rice.svg', 'active'),
(2, 2, 2, 'Diamante Max Hybrid Tomato Seeds', 'Heavy-bearing, heat-tolerant hybrid tomato seed packet with high germination rate and firm fruits.', 450.00, 120, 'pack', 'images/product-tomato.svg', 'active'),
(3, 2, 3, 'Native Sweet Calamansi Seedlings', 'Grafted calamansi seedlings ready for orchard transplanting. Fast-growing and continuous bearer.', 75.00, 300, 'seedling', 'images/product-calamansi.svg', 'active'),
(4, 2, 5, 'Organic Vermicast Soil Enhancer', 'Pure organic vermicompost rich in micronutrients and beneficial microbes for soil revitalisation.', 280.00, 50, 'bag (50kg)', 'images/placeholder-product.svg', 'active'),
(5, 5, 2, 'Sweet Corn Hybrid F1 Seeds', 'Top-quality yellow sweet corn seeds with uniform ear size and outstanding sweetness.', 520.00, 65, 'pack (1kg)', 'images/placeholder-product.svg', 'active');

-- Sample Equipment (Machinery & Farm Tools)
INSERT INTO `equipment` (`equipment_id`, `user_id`, `category_id`, `equipment_name`, `description`, `brand`, `model`, `rate_type`, `rate_price`, `availability`, `image_url`, `status`) VALUES
(1, 3, 6, 'Kubota L3408 4WD Farm Tractor', 'Powerful 34HP compact diesel tractor equipped with rotary tiller and disc plow for field preparation.', 'Kubota', 'L3408 4WD', 'daily', 3500.00, 'available', 'images/equip-tractor.svg', 'active'),
(2, 3, 7, 'Yanmar Rice Combine Harvester', 'Full-feed rubber track harvester suitable for soft and deep paddy fields. Fast threshing and low grain loss.', 'Yanmar', 'AW70V', 'daily', 6000.00, 'available', 'images/equip-harvester.svg', 'active'),
(3, 3, 8, 'Diesel High-Flow Irrigation Pump', '4-inch self-priming centrifugal water pump powered by heavy-duty diesel engine. Includes intake hose.', 'Robin', 'PTD406', 'hourly', 250.00, 'available', 'images/equip-pump.svg', 'active'),
(4, 3, 10, 'Heavy Duty Hand Tiller Cultivator', 'Gasoline-powered walk-behind cultivator ideal for vegetable garden beds and nursery preparation.', 'Honda', 'FJ500', 'daily', 950.00, 'available', 'images/placeholder-equipment.svg', 'active'),
(5, 3, 10, 'Hardened Steel Farm Shovel & Hoe Set', 'Commercial-grade hand tool set featuring forged carbon steel blades and ergonomic fiberglass handles.', 'Tramontina', 'Pro-Farm', 'daily', 150.00, 'available', 'images/tool-shovel.svg', 'active');

-- Sample Notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `notification_type`, `related_id`, `is_read`) VALUES
(1, 4, 'Welcome to AgriMart', 'Your account has been successfully set up. You can now browse seeds, buy products, and rent farm equipment.', 'system', NULL, 0),
(2, 2, 'Welcome Seller', 'Your farmer profile is active. You can start listing your harvests and seed supplies today.', 'system', NULL, 0),
(3, 3, 'Welcome Equipment Owner', 'You can now list your machinery and manage rental requests on AgriMart.', 'system', NULL, 0);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
