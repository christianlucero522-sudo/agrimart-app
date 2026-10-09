-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: agrimart
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addresses` (
  `address_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(150) NOT NULL,
  `city_municipality` varchar(150) DEFAULT NULL,
  `province` varchar(150) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `fk_address_user` (`user_id`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
INSERT INTO `addresses` VALUES (1,2,'Purok 3, Farm Road','San Jose','San Fernando','Pampanga','2000','2026-08-25 08:40:28'),(2,3,'Block 5 Lot 12, Agro Village','Santa Cruz','Tarlac City','Tarlac','2300','2026-08-25 08:40:28'),(3,4,'14 Green Valley St.','Maligaya','Cabanatuan City','Nueva Ecija','3100','2026-08-25 08:40:28'),(4,5,'Purok 1','Malubibit Norte','Flora','Apayao','3810','2026-08-25 08:40:28');
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `booking_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `equipment_id` int(10) unsigned NOT NULL,
  `renter_id` int(10) unsigned NOT NULL,
  `owner_id` int(10) unsigned NOT NULL,
  `pickup_location` varchar(255) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `rental_hours` int(11) DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `late_days` int(11) DEFAULT 0,
  `late_penalty` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `security_deposit` decimal(10,2) NOT NULL DEFAULT 0.00,
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,6,8,5,'fslksfksjgb','vskjsvsn','2026-09-29','2026-09-29','2026-10-09',NULL,NULL,0,0.00,220.00,0.00,'ongoing','2026-09-29 09:05:03','2026-10-03 06:24:00'),(2,1,8,3,'Farm Site','location','2026-10-05','2026-10-05','2026-10-06',NULL,NULL,0,0.00,3500.00,0.00,'pending','2026-10-05 05:22:14','2026-10-05 05:22:14'),(3,1,9,3,'Farm Site','flora','2026-10-05','2026-10-05','2026-10-06',NULL,NULL,3,437.49,3500.00,0.00,'confirmed','2026-10-05 06:10:46','2026-10-08 23:32:12'),(4,2,5,3,'Farm Site','flora','2026-10-05','2026-10-05','2026-10-22',NULL,NULL,0,0.00,122400.00,20400.00,'confirmed','2026-10-05 11:00:17','2026-10-06 07:23:59'),(5,2,5,3,'Angeles City, Pampanga','flora','2026-10-06','2026-10-06','2026-10-07',NULL,NULL,2,600.00,7200.00,1200.00,'ongoing','2026-10-06 01:43:57','2026-10-08 23:32:12'),(6,8,10,5,'Flora, Apayao','marcela','2026-10-08','2026-10-17','2026-10-18',45,NULL,0,0.00,16199.46,2699.91,'confirmed','2026-10-08 08:09:12','2026-10-08 08:10:53');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart` (
  `cart_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
INSERT INTO `cart` VALUES (1,5,'2026-08-25 09:05:09'),(2,6,'2026-08-29 04:36:38'),(3,1,'2026-09-02 13:56:35'),(4,7,'2026-09-02 14:26:27'),(5,8,'2026-10-05 05:16:34'),(6,10,'2026-10-09 01:22:48');
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `cart_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `unique_cart_product` (`cart_id`,`product_id`),
  KEY `fk_cartitem_product` (`product_id`),
  CONSTRAINT `fk_cartitem_cart` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cartitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (29,1,3,4,'2026-10-08 07:43:18');
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_type` enum('seed','equipment') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `unique_category` (`category_name`,`category_type`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Grain & Cereal Seeds','seed','2026-08-25 08:40:28'),(2,'Vegetable Seeds','seed','2026-08-25 08:40:28'),(3,'Fruit Seeds & Seedlings','seed','2026-08-25 08:40:28'),(4,'Root Crop Seeds','seed','2026-08-25 08:40:28'),(5,'Organic Fertilizers & Soils','seed','2026-08-25 08:40:28'),(6,'Tractors & Tillers','equipment','2026-08-25 08:40:28'),(7,'Harvesters & Threshers','equipment','2026-08-25 08:40:28'),(8,'Irrigation & Water Pumps','equipment','2026-08-25 08:40:28'),(9,'Planters & Sprayers','equipment','2026-08-25 08:40:28'),(10,'Hand Tools & Farm Implements','equipment','2026-08-25 08:40:28');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment` (
  `equipment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
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
  `location_city` varchar(100) DEFAULT NULL,
  `location_province` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`equipment_id`),
  KEY `idx_equipment_user` (`user_id`),
  KEY `idx_equipment_category` (`category_id`),
  KEY `idx_equipment_name` (`equipment_name`),
  CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (1,3,6,'Kubota L3408 4WD Farm Tractor','Powerful 34HP compact diesel tractor equipped with rotary tiller and disc plow for field preparation.','Kubota','L3408 4WD','daily',3500.00,'rented','images/equip-tractor.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:52','San Fernando','Pampanga'),(2,3,7,'Yanmar Rice Combine Harvester','Full-feed rubber track harvester suitable for soft and deep paddy fields. Fast threshing and low grain loss.','Yanmar','AW70V','daily',6000.00,'rented','images/equip-harvester.svg','active','2026-08-25 08:40:28','2026-10-06 01:45:14','Angeles City','Pampanga'),(3,3,8,'Diesel High-Flow Irrigation Pump','4-inch self-priming centrifugal water pump powered by heavy-duty diesel engine. Includes intake hose.','Robin','PTD406','hourly',250.00,'available','images/equip-pump.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:44','Tarlac City','Tarlac'),(4,3,10,'Heavy Duty Hand Tiller Cultivator','Gasoline-powered walk-behind cultivator ideal for vegetable garden beds and nursery preparation.','Honda','FJ500','daily',950.00,'available','images/placeholder-equipment.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:52','San Fernando','Pampanga'),(5,3,10,'Hardened Steel Farm Shovel & Hoe Set','Commercial-grade hand tool set featuring forged carbon steel blades and ergonomic fiberglass handles.','Tramontina','Pro-Farm','daily',150.00,'available','images/tool-shovel.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:52','Mexico','Pampanga'),(6,5,10,'tooothpick','ahkdfhgf','kubota','kawayan','daily',20.00,'rented','uploads/equipment/equip_1791197039_448.jpg','inactive','2026-09-29 09:02:20','2026-10-06 01:33:44','Flora','Apayao'),(8,5,8,'pump','','kubota','L364O','hourly',299.99,'rented','images/placeholder-equipment.svg','active','2026-10-08 07:57:49','2026-10-08 14:03:24','Flora','Apayao'),(9,5,6,'AKGCA','','kubota','L364O','daily',1000.00,'available','uploads/equipment/equip_1791452873_119.png','active','2026-10-08 09:47:53','2026-10-08 09:47:53','Flora','Apayao');
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('order','booking','payment','system') NOT NULL DEFAULT 'system',
  `related_id` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_user` (`user_id`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'Welcome to AgriMart','Your account has been successfully set up. You can now browse seeds, buy products, and rent farm equipment.','system',NULL,0,'2026-08-25 08:40:28'),(2,2,'Welcome Seller','Your farmer profile is active. You can start listing your harvests and seed supplies today.','system',NULL,1,'2026-08-25 08:40:28'),(3,3,'Welcome Equipment Owner','You can now list your machinery and manage rental requests on AgriMart.','system',NULL,0,'2026-08-25 08:40:28'),(4,2,'New Order #1 Received','A customer has placed an order for your product(s). Please review and process the order.','order',1,1,'2026-08-25 09:06:16'),(5,5,'Order #1 Placed Successfully','Thank you for your order! Total amount: ?1,700.00. Status: Pending.','order',1,1,'2026-08-25 09:06:16'),(6,2,'New Order #2 Received','A customer has placed an order for your product(s). Please review and process the order.','order',2,1,'2026-08-25 09:20:35'),(7,5,'Order #2 Placed Successfully','Thank you for your order! Total amount: ?17,500.00. Status: Pending.','order',2,1,'2026-08-25 09:20:35'),(8,5,'Order #2 Status Updated: Confirmed','The seller has updated your order status to \'Confirmed\'.','order',2,1,'2026-08-25 09:23:06'),(9,2,'New Order #3 Received','A customer has placed an order for your product(s). Please review and process the order.','order',3,1,'2026-08-25 09:41:44'),(10,5,'Order #3 Placed Successfully','Thank you for your order! Total amount: ?1,250.00. Status: Pending.','order',3,1,'2026-08-25 09:41:44'),(11,2,'New Order #4 Received','A customer has placed an order for your product(s). Please review and process the order.','order',4,1,'2026-08-27 02:01:59'),(12,5,'Order #4 Placed Successfully','Thank you for your order! Total amount: ?450.00. Status: Pending.','order',4,1,'2026-08-27 02:01:59'),(14,6,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',6,0,'2026-09-02 13:51:46'),(15,5,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',5,1,'2026-09-02 13:51:53'),(16,2,'New Order #6 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',6,1,'2026-09-02 14:03:54'),(17,5,'Order #6 Placed Successfully','Thank you for your order! Total amount: Γé▒1,250.00. Payment to seller via CASH','order',6,1,'2026-09-02 14:03:54'),(18,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:11'),(19,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:13'),(20,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:16'),(21,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:18'),(22,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:20'),(23,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:22'),(24,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:24'),(25,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:26'),(26,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:28'),(27,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:30'),(28,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:32'),(29,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:34'),(30,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:36'),(31,2,'New Order #7 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',7,1,'2026-09-02 14:15:53'),(32,5,'Order #7 Placed Successfully','Thank you for your order! Total amount: Γé▒2,500.00. Payment to seller via CASH','order',7,1,'2026-09-02 14:15:53'),(33,5,'Order #7 Status: Processing','Your order #7 has been marked as \'processing\' by the farmer/seller.','order',7,1,'2026-09-02 14:17:10'),(34,1,'New User Verification: Allen Mallari','Allen Mallari has registered with a Philippine National ID (No: 123456789). Please review their valid ID in the Admin Console.','system',7,0,'2026-09-02 14:25:58'),(35,2,'New Order #8 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',8,1,'2026-09-02 14:26:40'),(36,7,'Order #8 Placed Successfully','Thank you for your order! Total amount: Γé▒1,250.00. Payment to seller via CASH','order',8,0,'2026-09-02 14:26:40'),(37,6,'Identity Verification: Rejected','Your identity verification was marked as \'rejected\'. Please check your profile or contact admin.','system',6,0,'2026-09-29 05:47:47'),(38,6,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',6,0,'2026-09-29 05:47:51'),(39,1,'New User Verification: Jemaica R. Bancud','Jemaica R. Bancud has registered with a Philippine National ID (No: 1325256). Please review their valid ID in the Admin Console.','system',8,0,'2026-09-29 08:52:11'),(40,5,'New Rental Booking #1','A user has requested to rent your tooothpick from 2026-09-29 to 2026-10-09. Please review the request and payment.','booking',1,1,'2026-09-29 09:05:03'),(41,8,'Rental Request #1 Submitted','Your booking request for tooothpick has been submitted. Total amount: Γé▒220.00','booking',1,0,'2026-09-29 09:05:03'),(42,2,'New Order #13 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',13,1,'2026-10-03 05:47:59'),(43,5,'Order #13 Placed Successfully','Thank you for your order! Total amount: Γé▒82,500.00. Payment to seller via CASH','order',13,1,'2026-10-03 05:47:59'),(44,1,'New User Registration: Christian lucero','Christian lucero has registered with a Philippine National ID (No: 1325256). Valid ID & Face Biometrics are ready for admin review.','system',9,0,'2026-10-03 05:50:02'),(45,8,'Rental Request #1 Confirmed','The equipment owner has updated your booking for tooothpick to \'Confirmed\'.','booking',1,0,'2026-10-03 06:23:39'),(46,8,'Rental Ending Soon: tooothpick','Your rental booking #1 ends on 2026-10-09. Please prepare the machine for return.','booking',1,0,'2026-10-03 06:23:44'),(47,8,'Rental Request #1 Ongoing','The equipment owner has updated your booking for tooothpick to \'Ongoing\'.','booking',1,0,'2026-10-03 06:24:00'),(48,9,'KYC & Face Verification: Verified','Your identity documents and live face biometrics have been approved! You now have full seller and rental privileges.','system',9,1,'2026-10-03 12:50:46'),(49,9,'KYC & Face Verification: Pending','Your verification status was updated to \'pending\'. Please check your profile or submit updated credentials.','system',9,1,'2026-10-03 12:50:53'),(50,3,'New Rental Booking #2','A user has requested to rent your Kubota L3408 4WD Farm Tractor from 2026-10-05 to 2026-10-06. Please review the request and payment.','booking',2,0,'2026-10-05 05:22:14'),(51,8,'Rental Request #2 Submitted','Your booking request for Kubota L3408 4WD Farm Tractor has been submitted. Total amount: Γé▒3,500.00','booking',2,0,'2026-10-05 05:22:14'),(52,8,'Rental Ending Soon: tooothpick','Your rental booking #1 ends on 2026-10-09. Please prepare the machine for return.','booking',1,0,'2026-10-05 05:34:57'),(53,1,'New User Registration: Christian lucero','Christian lucero has created an account. Email verification link has been dispatched.','system',10,0,'2026-10-05 06:09:04'),(54,3,'New Rental Booking #3','A user has requested to rent your Kubota L3408 4WD Farm Tractor from 2026-10-05 to 2026-10-06. Please review the request and payment.','booking',3,0,'2026-10-05 06:10:46'),(55,9,'Rental Request #3 Submitted','Your booking request for Kubota L3408 4WD Farm Tractor has been submitted. Total amount: Γé▒3,500.00','booking',3,1,'2026-10-05 06:10:46'),(56,9,'Rental Request #3 Confirmed','The equipment owner has updated your booking for Kubota L3408 4WD Farm Tractor to \'Confirmed\'.','booking',3,1,'2026-10-05 06:12:52'),(57,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:12:59'),(58,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:04'),(59,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:08'),(60,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:11'),(61,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:16:46'),(62,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:07'),(63,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:14'),(64,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:18'),(65,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:22'),(66,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:26'),(67,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:29'),(68,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:34'),(69,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:41'),(70,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:46'),(71,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:52'),(72,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:57'),(73,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:48:45'),(74,2,'New Order #14 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',14,1,'2026-10-05 07:12:03'),(75,5,'Order #14 Placed Successfully','Thank you for your order! Total amount: Γé▒4,050.00. Payment to seller via CASH','order',14,1,'2026-10-05 07:12:03'),(76,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 08:49:02'),(77,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 10:35:10'),(78,3,'New Rental Booking #4','A user has requested to rent your Yanmar Rice Combine Harvester from 2026-10-05 to 2026-10-22. Total fee: Γé▒122,400.00 (Includes Γé▒20,400.00 20% security deposit for damage/loss). Please review the request.','booking',4,0,'2026-10-05 11:00:17'),(79,5,'Rental Request #4 Submitted','Your booking request for Yanmar Rice Combine Harvester has been submitted. Total amount: Γé▒122,400.00 (Includes Γé▒20,400.00 20% refundable damage deposit).','booking',4,1,'2026-10-05 11:00:17'),(80,2,'New Order #18 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',18,1,'2026-10-05 15:01:14'),(81,5,'Order #18 Placed Successfully','Thank you for your order! Total amount: Γé▒49,500.00. Payment to seller via CASH','order',18,1,'2026-10-05 15:01:14'),(82,2,'New Order #19 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',19,1,'2026-10-05 15:02:33'),(83,5,'Order #19 Placed Successfully','Thank you for your order! Total amount: Γé▒600.00. Payment to seller via CASH','order',19,1,'2026-10-05 15:02:33'),(84,3,'New Rental Booking #5','A user has requested to rent your Yanmar Rice Combine Harvester from 2026-10-06 to 2026-10-07. Total fee: Γé▒7,200.00 (Includes Γé▒1,200.00 20% security deposit for damage/loss). Please review the request.','booking',5,0,'2026-10-06 01:43:57'),(85,5,'Rental Request #5 Submitted','Your booking request for Yanmar Rice Combine Harvester has been submitted. Total amount: Γé▒7,200.00 (Includes Γé▒1,200.00 20% refundable damage deposit).','booking',5,1,'2026-10-06 01:43:57'),(86,5,'Rental Request #5 Confirmed','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Confirmed\'.','booking',5,1,'2026-10-06 01:45:14'),(87,5,'Rental Request #5 Ongoing','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Ongoing\'.','booking',5,1,'2026-10-06 01:45:27'),(88,5,'ΓÜá∩╕Å Rental Ending Soon: Yanmar Rice Combine Harvester','Your rental period for Yanmar Rice Combine Harvester (Booking #5) expires on October 07, 2026. An SMS reminder has been sent to your mobile phone (09999704103). Please coordinate equipment return with the owner.','booking',5,1,'2026-10-06 01:45:47'),(89,2,'New Order #20 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',20,1,'2026-10-06 02:07:29'),(90,5,'Order #20 Placed Successfully','Thank you for your order! Total amount: Γé▒14,000.00. Payment to seller via CASH','order',20,1,'2026-10-06 02:07:29'),(91,5,'Order #20 Status: Confirmed','Your order #20 has been marked as \'confirmed\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:24'),(92,5,'Order #20 Status: Confirmed','Your order #20 has been marked as \'confirmed\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:28'),(93,5,'Order #20 Status: Processing','Your order #20 has been marked as \'processing\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:32'),(94,2,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',2,1,'2026-10-06 03:55:14'),(95,9,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',9,1,'2026-10-06 03:55:29'),(96,7,'Identity & Face Verification: Rejected','Your identity verification status was updated to \'rejected\'. Please check your profile.','system',7,0,'2026-10-06 03:55:48'),(97,1,'New Listing Report #2','A user reported a product listing (Reason: Misleading). Review required.','system',2,0,'2026-10-06 05:04:12'),(98,9,'ΓÜá∩╕Å Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental period for Kubota L3408 4WD Farm Tractor (Booking #3) expires on October 06, 2026. An SMS reminder has been sent to your mobile phone (09058642483). Please coordinate equipment return with the owner.','booking',3,1,'2026-10-06 07:12:00'),(99,5,'Rental Request #4 Confirmed','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Confirmed\'.','booking',4,1,'2026-10-06 07:23:59'),(100,5,'ΓÜá∩╕Å Rental Ending Soon: Yanmar Rice Combine Harvester','Your rental period for Yanmar Rice Combine Harvester (Booking #5) expires on October 07, 2026. An SMS reminder has been sent to your mobile phone (09999704103). Please coordinate equipment return with the owner.','booking',5,1,'2026-10-07 00:54:22'),(101,1,'New User Registered: jheradel Urganay','jheradel Urganay (allenkhurtmallari22@gmail.com) has created an account on AgriMart.','system',12,0,'2026-10-07 01:14:36'),(102,3,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',3,0,'2026-10-07 02:39:39'),(103,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 1 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒145.83) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-07 03:51:30'),(104,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (1 day(s) late). An accumulated 5% daily penalty of Γé▒145.83 has been recorded.','booking',3,0,'2026-10-07 03:51:30'),(105,8,'ΓÜá∩╕Å Rental Ending Soon: tooothpick','Your rental period for tooothpick (Booking #1) expires on October 09, 2026. An SMS reminder has been dispatched to your mobile phone (09605358372). Please return the machinery to avoid late penalties (5%/day).','booking',1,0,'2026-10-08 02:35:33'),(106,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 2 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒291.66) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-08 02:35:33'),(107,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (2 day(s) late). An accumulated 5% daily penalty of Γé▒291.66 has been recorded.','booking',3,0,'2026-10-08 02:35:33'),(108,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 1 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒300.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-08 02:35:33'),(109,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (1 day(s) late). An accumulated 5% daily penalty of Γé▒300.00 has been recorded.','booking',5,0,'2026-10-08 02:35:33'),(110,2,'New Order #21 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',21,1,'2026-10-08 04:54:55'),(111,5,'Order #21 Placed Successfully','Thank you for your order! Total amount: Γé▒450.00. Payment to seller via CASH','order',21,1,'2026-10-08 04:54:55'),(112,2,'New Order #22 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',22,1,'2026-10-08 07:42:55'),(113,5,'Order #22 Placed Successfully','Thank you for your order! Total amount: Γé▒75.00. Payment to seller via CASH','order',22,1,'2026-10-08 07:42:55'),(114,5,'New Rental Booking #6','A user has requested to rent your pump 2026-10-17 (45 hours). Total fee: Γé▒16,199.46 (Includes Γé▒2,699.91 20% security deposit for damage/loss). Please review the request.','booking',6,1,'2026-10-08 08:09:12'),(115,10,'Rental Request #6 Submitted','Your booking request for pump (2026-10-17 (45 hours)) has been submitted. Total amount: Γé▒16,199.46 (Includes Γé▒2,699.91 20% refundable damage deposit).','booking',6,0,'2026-10-08 08:09:12'),(116,10,'Rental Request #6 Confirmed','The equipment owner has updated your booking for pump to \'Confirmed\'.','booking',6,0,'2026-10-08 08:10:53'),(117,1,'New User Registered: Rhod herald pagaran','Rhod herald pagaran (rhod123@gmail.com) has created an account on AgriMart.','system',13,0,'2026-10-08 09:49:46'),(118,1,'New Listing Report #1','A user reported a product listing (Reason: Misleading). Review required.','system',1,0,'2026-10-08 14:05:04'),(119,8,'ΓÜá∩╕Å Rental Ending Soon: tooothpick','Your rental period for tooothpick (Booking #1) expires on October 09, 2026. An SMS reminder has been dispatched to your mobile phone (09605358372). Please return the machinery to avoid late penalties (5%/day).','booking',1,0,'2026-10-08 23:32:12'),(120,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 3 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒437.49) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-08 23:32:12'),(121,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (3 day(s) late). An accumulated 5% daily penalty of Γé▒437.49 has been recorded.','booking',3,0,'2026-10-08 23:32:12'),(122,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 2 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒600.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,0,'2026-10-08 23:32:12'),(123,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (2 day(s) late). An accumulated 5% daily penalty of Γé▒600.00 has been recorded.','booking',5,0,'2026-10-08 23:32:12'),(124,1,'New User Registered: Christian lucero','Christian lucero (christianlucero5@gmail.com) has created an account on AgriMart.','system',14,0,'2026-10-08 23:38:37'),(125,2,'New Order #23 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',23,1,'2026-10-09 01:23:44'),(126,10,'Order #23 Placed Successfully','Thank you for your order! Total amount: Γé▒14,850.00. Payment to seller via CASH','order',23,0,'2026-10-09 01:23:44'),(127,1,'New Listing Report #2','A user reported a product listing (Reason: Misleading). Review required.','system',2,0,'2026-10-09 01:38:31'),(128,1,'New User Registered: mark anthony tenepere','mark anthony tenepere (tenepermarkanthony@gmail.com) has created an account on AgriMart.','system',15,0,'2026-10-09 02:19:35'),(129,1,'New User Registered: mark anthony tenepere','mark anthony tenepere (teneperemarkanthony@gmail.com) has created an account on AgriMart.','system',16,0,'2026-10-09 02:22:10');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `order_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `seller_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `fk_orderitem_product` (`product_id`),
  KEY `fk_orderitem_seller` (`seller_id`),
  KEY `idx_order_item_order` (`order_id`),
  CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,2,1,1250.00,1250.00),(2,1,2,2,1,450.00,450.00),(3,2,1,2,14,1250.00,17500.00),(4,3,1,2,1,1250.00,1250.00),(5,4,2,2,1,450.00,450.00),(7,6,1,2,1,1250.00,1250.00),(8,7,1,2,2,1250.00,2500.00),(9,8,1,2,1,1250.00,1250.00),(14,13,1,2,66,1250.00,82500.00),(15,14,2,2,9,450.00,4050.00),(19,18,2,2,110,450.00,49500.00),(20,19,3,2,8,75.00,600.00),(21,20,4,2,50,280.00,14000.00),(22,21,2,2,1,450.00,450.00),(23,22,3,2,1,75.00,75.00),(24,23,2,2,33,450.00,14850.00);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` int(10) unsigned NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_address` varchar(500) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_order_buyer` (`buyer_id`),
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,5,1700.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','cancelled','2026-08-25 09:06:16','2026-08-25 09:06:41'),(2,5,17500.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','confirmed','2026-08-25 09:20:35','2026-08-25 09:23:06'),(3,5,1250.00,'malubibit norte,flora, apayao,3810','pending','2026-08-25 09:41:44','2026-08-25 09:41:44'),(4,5,450.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','pending','2026-08-27 02:01:58','2026-08-27 02:01:58'),(6,5,1250.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','completed','2026-09-02 14:03:54','2026-09-02 14:05:11'),(7,5,2500.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','processing','2026-09-02 14:15:53','2026-09-02 14:17:10'),(8,7,1250.00,'6C6X+MPQ Malubibit Norte','pending','2026-09-02 14:26:40','2026-09-02 14:26:40'),(13,5,82500.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-03 05:47:59','2026-10-03 05:47:59'),(14,5,4050.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-05 07:12:03','2026-10-05 07:12:03'),(18,5,49500.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-05 15:01:14','2026-10-05 15:01:14'),(19,5,600.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-05 15:02:33','2026-10-05 15:02:33'),(20,5,14000.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','processing','2026-10-06 02:07:29','2026-10-06 02:08:32'),(21,5,450.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-08 04:54:55','2026-10-08 04:54:55'),(22,5,75.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','pending','2026-10-08 07:42:55','2026-10-08 07:42:55'),(23,10,14850.00,'6C6X+MPQ Malubibit Norte','confirmed','2026-10-09 01:23:44','2026-10-09 01:24:16');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,5,1,NULL,'cash',NULL,NULL,NULL,1700.00,'refunded','',NULL,NULL,'2026-08-25 09:06:16'),(2,5,2,NULL,'bank_transfer',NULL,NULL,NULL,17500.00,'paid','',NULL,'2026-08-25 11:20:35','2026-08-25 09:20:35'),(3,5,3,NULL,'cash',NULL,NULL,NULL,1250.00,'pending','',NULL,NULL,'2026-08-25 09:41:44'),(4,5,4,NULL,'cash',NULL,NULL,NULL,450.00,'pending','',NULL,NULL,'2026-08-27 02:01:59'),(5,5,6,NULL,'cash','GCash','','',1250.00,'pending','0',NULL,NULL,'2026-09-02 14:03:54'),(6,5,7,NULL,'cash','GCash','','',2500.00,'pending','0',NULL,NULL,'2026-09-02 14:15:53'),(7,7,8,NULL,'cash','GCash','','',1250.00,'pending','0',NULL,NULL,'2026-09-02 14:26:40'),(8,8,NULL,1,'cash','GCash','','',220.00,'pending','0',NULL,NULL,'2026-09-29 09:05:03'),(9,5,13,NULL,'cash','GCash','','',82500.00,'pending','0',NULL,NULL,'2026-10-03 05:47:59'),(10,8,NULL,2,'cash','GCash','','',3500.00,'pending','0',NULL,NULL,'2026-10-05 05:22:14'),(11,9,NULL,3,'cash','GCash','','',3500.00,'pending','0',NULL,NULL,'2026-10-05 06:10:46'),(12,5,14,NULL,'cash','GCash','','',4050.00,'pending','0',NULL,NULL,'2026-10-05 07:12:03'),(13,5,NULL,4,'cash','GCash','','',122400.00,'pending','0',NULL,NULL,'2026-10-05 11:00:17'),(14,5,18,NULL,'cash','GCash','','',49500.00,'pending','0',NULL,NULL,'2026-10-05 15:01:14'),(15,5,19,NULL,'cash','GCash','','',600.00,'pending','0',NULL,NULL,'2026-10-05 15:02:33'),(16,5,NULL,5,'cash','GCash','','',7200.00,'pending','0',NULL,NULL,'2026-10-06 01:43:57'),(17,5,20,NULL,'cash','GCash','','',14000.00,'pending','0',NULL,NULL,'2026-10-06 02:07:29'),(18,5,21,NULL,'cash','GCash','','',450.00,'pending','0',NULL,NULL,'2026-10-08 04:54:55'),(19,5,22,NULL,'cash','GCash','','',75.00,'pending','0',NULL,NULL,'2026-10-08 07:42:55'),(20,10,NULL,6,'cash','GCash','','',16199.46,'pending','0',NULL,NULL,'2026-10-08 08:09:12'),(21,10,23,NULL,'cash','GCash','','',14850.00,'paid','0',NULL,NULL,'2026-10-09 01:23:44');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `product_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(10) unsigned NOT NULL DEFAULT 0,
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,2,1,'Certified Inbred RC-222 Rice Seeds','High-yielding inbred rice variety resistant to pests and suitable for wet and dry planting seasons.',1250.00,10,'sack (20kg)','images/product-rice.svg','inactive','2026-08-25 08:40:28','2026-10-09 01:39:10'),(2,2,2,'Diamante Max Hybrid Tomato Seeds','Heavy-bearing, heat-tolerant hybrid tomato seed packet with high germination rate and firm fruits.',450.00,10,'pack','images/product-tomato.svg','active','2026-08-25 08:40:28','2026-10-09 01:26:26'),(3,2,3,'Native Sweet Calamansi Seedlings','Grafted calamansi seedlings ready for orchard transplanting. Fast-growing and continuous bearer.',75.00,291,'seedling','images/product-calamansi.svg','active','2026-08-25 08:40:28','2026-10-08 07:42:55'),(4,2,5,'Organic Vermicast Soil Enhancer','Pure organic vermicompost rich in micronutrients and beneficial microbes for soil revitalisation.',280.00,44,'bag (50kg)','images/placeholder-product.svg','active','2026-08-25 08:40:28','2026-10-06 04:15:07'),(5,5,2,'Sweet Corn Hybrid F1 Seeds','Top-quality yellow sweet corn seeds with uniform ear size and outstanding sweetness.',520.00,65,'pack (1kg)','images/placeholder-product.svg','active','2026-08-25 08:40:28','2026-08-25 08:40:28');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `report_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` int(10) unsigned NOT NULL,
  `reported_user_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `report_type` enum('product','equipment','user') NOT NULL DEFAULT 'product',
  `reason` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','reviewed','action_taken','actioned','dismissed') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `resolved_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reporter` (`reporter_id`),
  KEY `idx_reported_user` (`reported_user_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_equipment` (`equipment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (1,8,2,3,NULL,'product','misleading','price does not meet the posted price','dismissed','Report reviewed and dismissed. No violation detected.',1,'2026-10-08 14:05:04','2026-10-08 14:05:34'),(2,8,2,1,NULL,'product','misleading','over priced','action_taken','Listing deactivated by administration due to report findings.',1,'2026-10-09 01:38:31','2026-10-09 01:39:10');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `review_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reviewer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `equipment_id` int(10) unsigned DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,2,1,NULL,NULL,NULL,5,'Exceptional crop quality and fast local delivery! Highly recommended.','2026-10-05 15:56:12'),(2,2,NULL,3,NULL,NULL,5,'Heavy-duty tractor was well-maintained and completed our plowing on time.','2026-10-05 15:56:12'),(3,5,3,NULL,NULL,NULL,5,'','2026-10-06 01:49:47'),(4,1,2,NULL,NULL,NULL,5,'','2026-10-06 05:05:23');
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_logs`
--

DROP TABLE IF EXISTS `sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_logs` (
  `sms_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `phone_number` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(30) DEFAULT 'delivered',
  `sent_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`sms_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
INSERT INTO `sms_logs` VALUES (1,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on 2026-10-09. Please prepare the machine for return at vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-03 14:23:44'),(2,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on 2026-10-09. Please prepare the machine for return at vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-05 13:34:57'),(3,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:12:59'),(4,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:04'),(5,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:08'),(6,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:11'),(7,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:16:46'),(8,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:07'),(9,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:14'),(10,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:18'),(11,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:22'),(12,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:26'),(13,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:29'),(14,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:34'),(15,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:41'),(16,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:46'),(17,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:52'),(18,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:57'),(19,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:48:45'),(20,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 16:49:02'),(21,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 18:35:10'),(22,5,'09999704103','AgriMart Alert: Your rental for Yanmar Rice Combine Harvester (Booking #5) is scheduled to end on October 07, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-06 09:45:47'),(23,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on October 06, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-06 15:12:00'),(24,5,'09999704103','AgriMart Alert: Your rental for Yanmar Rice Combine Harvester (Booking #5) is scheduled to end on October 07, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-07 08:54:22'),(25,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 1 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒145.83) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-07 11:51:30'),(26,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on October 09, 2026. Please prepare the machine for return at vskjsvsn to avoid a 5% daily late penalty. Owner: Christian Lucero (09999704103).','delivered','2026-10-08 10:35:33'),(27,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 2 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒291.66) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-08 10:35:33'),(28,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 1 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒300.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-08 10:35:33'),(29,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on October 09, 2026. Please prepare the machine for return at vskjsvsn to avoid a 5% daily late penalty. Owner: Christian Lucero (09999704103).','delivered','2026-10-09 07:32:12'),(30,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 3 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒437.49) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-09 07:32:12'),(31,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 2 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒600.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-09 07:32:12');
/*!40000 ALTER TABLE `sms_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `id_type` varchar(100) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `id_card_image` varchar(255) DEFAULT NULL,
  `is_verified` enum('pending','verified','rejected') DEFAULT 'pending',
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verification_token` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `face_image` varchar(255) DEFAULT NULL,
  `face_verified` enum('pending','verified','rejected') DEFAULT 'pending',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'AgriMart Administrator','admin@gmail.com','09171234567',NULL,NULL,NULL,'pending','$2y$10$G2txDeLF3w/fs3XlBj5s5O2FhtVw49udbmyRPno80VKVW0xj3s8ZC','admin','active','2026-08-25 08:40:28','2026-08-25 09:33:35',NULL,1,NULL,'pending'),(2,'Juan dela Cruz','juan@gmail.com','09189876543',NULL,NULL,NULL,'verified','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-10-06 03:55:14',NULL,1,NULL,'verified'),(3,'Pedro Penduko','pedro@gmail.com','09205554321',NULL,NULL,NULL,'verified','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-10-07 02:39:39',NULL,1,NULL,'verified'),(4,'Maria Santos','maria@gmail.com','09998887766',NULL,NULL,NULL,'pending','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-08-29 04:30:28',NULL,1,NULL,'pending'),(5,'Christian Lucero','christianlucero522@gmail.com','09999704103',NULL,NULL,NULL,'verified','$2y$10$m9jLWBXq1xKlijE.p.wSUe96FuT6BJDFCAKRbH1/QOFBR6WJgi.E6','user','active','2026-08-25 08:40:28','2026-10-05 06:16:58',NULL,1,NULL,'pending'),(6,'asleigh balthazar','balt@gmail.com','09098362456',NULL,NULL,NULL,'verified','$2y$10$Bmbhi56Dxz3vd42AQbYhf.C4pRl.CLCcnHw5RBywSCq9eOaJVvilW','user','banned','2026-08-29 04:35:37','2026-10-08 14:47:30',NULL,1,NULL,'pending'),(7,'Allen Mallari','allenmallari2000@gmail.com','09999704103','Philippine National ID','123456789','uploads/identifications/id_1788359158_3094.png','rejected','$2y$10$sTyrcp4.Sihnb5WUud3XiehLNo7cuUpHARneCiBsoORx97vHGF4pS','user','active','2026-09-02 14:25:58','2026-10-06 03:55:48',NULL,1,NULL,'rejected'),(8,'Jemaica R. Bancud','bancudjamaica@gmail.com','09605358372','Philippine National ID','1325256','uploads/identifications/id_1790671931_5183.jpg','pending','$2y$10$CN1G.PLu5bVfrMU4/B18geonAyX/jbZvXciClB6n6iOjg78rgQm9i','user','active','2026-09-29 08:52:11','2026-09-29 08:52:11',NULL,1,NULL,'pending'),(9,'Christian lucero','christianlucero538@gmail.com','09058642483','Philippine National ID','1325256','uploads/identifications/id_1791006597_1151.jpg','verified','$2y$10$7lq0hJ/aeEFYohu9d9Y7buMIoXU8vHv9ZXxPi3ymdYc/QK0BSLoce','user','active','2026-10-03 05:49:57','2026-10-06 03:55:29',NULL,1,'uploads/faces/face_1791006597_6501.jpg','verified'),(10,'Christian lucero','christianlucero2212@gmail.com','09058642483',NULL,NULL,NULL,'pending','$2y$10$a2LmVIzRsukd5Trae8qYXuPdd1pbTAQomDnOXNUldQHpug45JH4Yy','user','active','2026-10-05 06:08:58','2026-10-05 15:40:14',NULL,1,NULL,''),(12,'jheradel Urganay','allenkhurtmallari22@gmail.com','09058642483',NULL,NULL,NULL,'pending','$2y$10$kPe7oVG8LevLwGuLyzVehOoQmRCHwswxJCNqzQSsd3tDemvBya3.u','user','active','2026-10-07 01:14:29','2026-10-07 01:14:29','dea33d377afadeaa2d1553e3d57d12c8b23039753e727cf9',0,NULL,'pending'),(13,'Rhod herald pagaran','rhod123@gmail.com','09999704103',NULL,NULL,NULL,'pending','$2y$10$yU2fX7bV9jvUUd/KWT5GguDzFYLI15AckoiI7sVdCfYgXLDQWDvoq','user','active','2026-10-08 09:49:40','2026-10-08 09:49:40','479ffdd4bce663d3e1f517ab09ee00999bd9eed0f92a5498',0,NULL,'pending'),(15,'mark anthony tenepere','tenepermarkanthony@gmail.com','09656683298',NULL,NULL,NULL,'pending','$2y$10$FA6uEBOnqXuWbb7ClA1lHODUqf/7OA5a3Ok/1bLjxDt7H2zMzpoDe','user','active','2026-10-09 02:19:35','2026-10-09 02:19:35','df4bc8b27ebfea8ec9a8b74b00ba0b1ae89e60672fb1b523',0,NULL,'pending'),(16,'mark anthony tenepere','teneperemarkanthony@gmail.com','09656683298',NULL,NULL,NULL,'pending','$2y$10$aowNtRrTXaeWELEr/O.kr..HDO3RjCyP4kmOUycn78a.V1n68uWtO','user','active','2026-10-09 02:22:10','2026-10-09 02:24:57','b016e37b631ee5bbb779163d29bdfd52d6c07f5982632596',0,NULL,'pending');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 11:10:49
