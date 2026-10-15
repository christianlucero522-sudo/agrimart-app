-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: agrimart
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,6,8,5,'fslksfksjgb','vskjsvsn','2026-09-29','2026-09-29','2026-10-09',NULL,'2026-10-11',2,18.34,220.00,0.00,'completed','2026-09-29 09:05:03','2026-10-11 10:42:56'),(2,1,8,3,'Farm Site','location','2026-10-05','2026-10-05','2026-10-06',NULL,'2026-10-12',0,0.00,3500.00,0.00,'completed','2026-10-05 05:22:14','2026-10-12 05:41:00'),(3,1,9,3,'Farm Site','flora','2026-10-05','2026-10-05','2026-10-06',NULL,'2026-10-12',6,874.98,3500.00,0.00,'completed','2026-10-05 06:10:46','2026-10-12 03:27:37'),(4,2,5,3,'Farm Site','flora','2026-10-05','2026-10-05','2026-10-22',NULL,'2026-10-12',0,0.00,122400.00,20400.00,'completed','2026-10-05 11:00:17','2026-10-12 05:41:00'),(5,2,5,3,'Angeles City, Pampanga','flora','2026-10-06','2026-10-06','2026-10-07',NULL,'2026-10-12',5,1500.00,7200.00,1200.00,'completed','2026-10-06 01:43:57','2026-10-12 05:38:19'),(6,8,10,5,'Flora, Apayao','marcela','2026-10-08','2026-10-17','2026-10-18',45,'2026-10-11',0,0.00,16199.46,2699.91,'completed','2026-10-08 08:09:12','2026-10-11 10:45:38'),(7,1,5,3,'San Fernando, Pampanga','marcela','2026-10-12','2026-10-12','2026-10-13',NULL,'2026-10-12',0,0.00,4200.00,700.00,'completed','2026-10-12 05:37:16','2026-10-12 05:38:15');
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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
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
  `description` text DEFAULT NULL,
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
INSERT INTO `categories` VALUES (1,'Grain & Cereal Seeds','seed','Paddy rice, yellow corn, sorghum, wheat, and certified grain seeds.','2026-08-25 08:40:28'),(2,'Vegetable Seeds','seed','Leafy vegetables, tomatoes, eggplants, bell peppers, and field legumes.','2026-08-25 08:40:28'),(3,'Fruit Seeds & Seedlings','seed','Tree fruits, citrus rootstocks, banana suckers, and tropical seedlings.','2026-08-25 08:40:28'),(4,'Root Crop Seeds','seed','Cassava cuttings, sweet potato tubers, ginger, yam, and root crops.','2026-08-25 08:40:28'),(5,'Organic Fertilizers & Soils','seed','Compost, organic foliar fertilizers, vermicast, and soil conditioners.','2026-08-25 08:40:28'),(6,'Tractors & Tillers','equipment','Two-wheel walking tillers, 4WD farm tractors, rotary tillers, and cultivators.','2026-08-25 08:40:28'),(7,'Harvesters & Threshers','equipment','Rice combine harvesters, multi-crop threshers, and mechanical reapers.','2026-08-25 08:40:28'),(8,'Irrigation & Water Pumps','equipment','High-flow diesel and electric irrigation pumps, discharge hoses, and spray systems.','2026-08-25 08:40:28'),(9,'Planters & Sprayers','equipment','Mechanical transplanters, seeders, and motorized knapsack chemical sprayers.','2026-08-25 08:40:28'),(10,'Hand Tools & Farm Implements','equipment','Hardened shovels, scythes, pruning tools, and manual harvesting implements.','2026-08-25 08:40:28');
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
  `status` enum('active','inactive','deleted','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `location_city` varchar(100) DEFAULT NULL,
  `location_province` varchar(100) DEFAULT NULL,
  `admin_deactivated` tinyint(1) NOT NULL DEFAULT 0,
  `deactivation_reason` varchar(255) DEFAULT NULL,
  `retrieval_message` text DEFAULT NULL,
  `retrieval_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `retrieval_requested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`equipment_id`),
  KEY `idx_equipment_user` (`user_id`),
  KEY `idx_equipment_category` (`category_id`),
  KEY `idx_equipment_name` (`equipment_name`),
  CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (1,3,6,'Kubota L3408 4WD Farm Tractor','Powerful 34HP compact diesel tractor equipped with rotary tiller and disc plow for field preparation.','Kubota','L3408 4WD','daily',3500.00,'available','images/equip-tractor.svg','active','2026-08-25 08:40:28','2026-10-12 04:57:03','San Fernando','Pampanga',0,NULL,NULL,'approved',NULL),(2,3,7,'Yanmar Rice Combine Harvester','Full-feed rubber track harvester suitable for soft and deep paddy fields. Fast threshing and low grain loss.','Yanmar','AW70V','daily',6000.00,'available','images/equip-harvester.svg','active','2026-08-25 08:40:28','2026-10-12 05:38:40','Angeles City','Pampanga',0,NULL,'i sent the wrong price','approved','2026-10-12 13:38:04'),(3,3,8,'Diesel High-Flow Irrigation Pump','4-inch self-priming centrifugal water pump powered by heavy-duty diesel engine. Includes intake hose.','Robin','PTD406','hourly',250.00,'available','images/equip-pump.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:44','Tarlac City','Tarlac',0,NULL,NULL,'none',NULL),(4,3,10,'Heavy Duty Hand Tiller Cultivator','Gasoline-powered walk-behind cultivator ideal for vegetable garden beds and nursery preparation.','Honda','FJ500','daily',950.00,'available','images/placeholder-equipment.svg','active','2026-08-25 08:40:28','2026-10-06 01:33:52','San Fernando','Pampanga',0,NULL,NULL,'none',NULL),(5,3,10,'Hardened Steel Farm Shovel & Hoe Set','Commercial-grade hand tool set featuring forged carbon steel blades and ergonomic fiberglass handles.','Tramontina','Pro-Farm','daily',150.00,'available','images/tool-shovel.svg','active','2026-08-25 08:40:28','2026-10-12 03:50:24','Mexico','Pampanga',0,NULL,NULL,'none',NULL),(6,5,10,'tooothpick','ahkdfhgf','kubota','kawayan','daily',20.00,'maintenance','uploads/equipment/equip_1791197039_448.jpg','deleted','2026-09-29 09:02:20','2026-10-11 10:50:30','Flora','Apayao',0,NULL,NULL,'approved',NULL),(8,5,8,'pump','','kubota','L364O','hourly',299.99,'maintenance','images/placeholder-equipment.svg','deleted','2026-10-08 07:57:49','2026-10-11 10:50:30','Flora','Apayao',0,NULL,NULL,'none',NULL),(11,5,7,'tracktor','','kubota','L364O','daily',4000.00,'available','images/placeholder-equipment.svg','active','2026-10-12 05:45:36','2026-10-12 05:45:36','Flora','Apayao',0,NULL,NULL,'none',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=207 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'Welcome to AgriMart','Your account has been successfully set up. You can now browse seeds, buy products, and rent farm equipment.','system',NULL,0,'2026-08-25 08:40:28'),(2,2,'Welcome Seller','Your farmer profile is active. You can start listing your harvests and seed supplies today.','system',NULL,1,'2026-08-25 08:40:28'),(3,3,'Welcome Equipment Owner','You can now list your machinery and manage rental requests on AgriMart.','system',NULL,0,'2026-08-25 08:40:28'),(4,2,'New Order #1 Received','A customer has placed an order for your product(s). Please review and process the order.','order',1,1,'2026-08-25 09:06:16'),(5,5,'Order #1 Placed Successfully','Thank you for your order! Total amount: ?1,700.00. Status: Pending.','order',1,1,'2026-08-25 09:06:16'),(6,2,'New Order #2 Received','A customer has placed an order for your product(s). Please review and process the order.','order',2,1,'2026-08-25 09:20:35'),(7,5,'Order #2 Placed Successfully','Thank you for your order! Total amount: ?17,500.00. Status: Pending.','order',2,1,'2026-08-25 09:20:35'),(8,5,'Order #2 Status Updated: Confirmed','The seller has updated your order status to \'Confirmed\'.','order',2,1,'2026-08-25 09:23:06'),(9,2,'New Order #3 Received','A customer has placed an order for your product(s). Please review and process the order.','order',3,1,'2026-08-25 09:41:44'),(10,5,'Order #3 Placed Successfully','Thank you for your order! Total amount: ?1,250.00. Status: Pending.','order',3,1,'2026-08-25 09:41:44'),(11,2,'New Order #4 Received','A customer has placed an order for your product(s). Please review and process the order.','order',4,1,'2026-08-27 02:01:59'),(12,5,'Order #4 Placed Successfully','Thank you for your order! Total amount: ?450.00. Status: Pending.','order',4,1,'2026-08-27 02:01:59'),(14,6,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',6,0,'2026-09-02 13:51:46'),(15,5,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',5,1,'2026-09-02 13:51:53'),(16,2,'New Order #6 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',6,1,'2026-09-02 14:03:54'),(17,5,'Order #6 Placed Successfully','Thank you for your order! Total amount: Γé▒1,250.00. Payment to seller via CASH','order',6,1,'2026-09-02 14:03:54'),(18,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:11'),(19,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:13'),(20,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:16'),(21,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:18'),(22,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:20'),(23,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:22'),(24,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:24'),(25,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:26'),(26,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:28'),(27,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:30'),(28,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:32'),(29,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:34'),(30,5,'Order #6 Status: Completed','Your order #6 has been marked as \'completed\' by the farmer/seller.','order',6,1,'2026-09-02 14:05:36'),(31,2,'New Order #7 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',7,1,'2026-09-02 14:15:53'),(32,5,'Order #7 Placed Successfully','Thank you for your order! Total amount: Γé▒2,500.00. Payment to seller via CASH','order',7,1,'2026-09-02 14:15:53'),(33,5,'Order #7 Status: Processing','Your order #7 has been marked as \'processing\' by the farmer/seller.','order',7,1,'2026-09-02 14:17:10'),(34,1,'New User Verification: Allen Mallari','Allen Mallari has registered with a Philippine National ID (No: 123456789). Please review their valid ID in the Admin Console.','system',7,0,'2026-09-02 14:25:58'),(35,2,'New Order #8 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',8,1,'2026-09-02 14:26:40'),(36,7,'Order #8 Placed Successfully','Thank you for your order! Total amount: Γé▒1,250.00. Payment to seller via CASH','order',8,0,'2026-09-02 14:26:40'),(37,6,'Identity Verification: Rejected','Your identity verification was marked as \'rejected\'. Please check your profile or contact admin.','system',6,0,'2026-09-29 05:47:47'),(38,6,'Identity Verification: Verified','Your identity and valid ID have been approved! You are now a verified member of AgriMart.','system',6,0,'2026-09-29 05:47:51'),(39,1,'New User Verification: Jemaica R. Bancud','Jemaica R. Bancud has registered with a Philippine National ID (No: 1325256). Please review their valid ID in the Admin Console.','system',8,0,'2026-09-29 08:52:11'),(40,5,'New Rental Booking #1','A user has requested to rent your tooothpick from 2026-09-29 to 2026-10-09. Please review the request and payment.','booking',1,1,'2026-09-29 09:05:03'),(41,8,'Rental Request #1 Submitted','Your booking request for tooothpick has been submitted. Total amount: Γé▒220.00','booking',1,1,'2026-09-29 09:05:03'),(42,2,'New Order #13 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',13,1,'2026-10-03 05:47:59'),(43,5,'Order #13 Placed Successfully','Thank you for your order! Total amount: Γé▒82,500.00. Payment to seller via CASH','order',13,1,'2026-10-03 05:47:59'),(44,1,'New User Registration: Christian lucero','Christian lucero has registered with a Philippine National ID (No: 1325256). Valid ID & Face Biometrics are ready for admin review.','system',9,0,'2026-10-03 05:50:02'),(45,8,'Rental Request #1 Confirmed','The equipment owner has updated your booking for tooothpick to \'Confirmed\'.','booking',1,1,'2026-10-03 06:23:39'),(46,8,'Rental Ending Soon: tooothpick','Your rental booking #1 ends on 2026-10-09. Please prepare the machine for return.','booking',1,1,'2026-10-03 06:23:44'),(47,8,'Rental Request #1 Ongoing','The equipment owner has updated your booking for tooothpick to \'Ongoing\'.','booking',1,1,'2026-10-03 06:24:00'),(48,9,'KYC & Face Verification: Verified','Your identity documents and live face biometrics have been approved! You now have full seller and rental privileges.','system',9,1,'2026-10-03 12:50:46'),(49,9,'KYC & Face Verification: Pending','Your verification status was updated to \'pending\'. Please check your profile or submit updated credentials.','system',9,1,'2026-10-03 12:50:53'),(50,3,'New Rental Booking #2','A user has requested to rent your Kubota L3408 4WD Farm Tractor from 2026-10-05 to 2026-10-06. Please review the request and payment.','booking',2,0,'2026-10-05 05:22:14'),(51,8,'Rental Request #2 Submitted','Your booking request for Kubota L3408 4WD Farm Tractor has been submitted. Total amount: Γé▒3,500.00','booking',2,1,'2026-10-05 05:22:14'),(52,8,'Rental Ending Soon: tooothpick','Your rental booking #1 ends on 2026-10-09. Please prepare the machine for return.','booking',1,1,'2026-10-05 05:34:57'),(53,1,'New User Registration: Christian lucero','Christian lucero has created an account. Email verification link has been dispatched.','system',10,0,'2026-10-05 06:09:04'),(54,3,'New Rental Booking #3','A user has requested to rent your Kubota L3408 4WD Farm Tractor from 2026-10-05 to 2026-10-06. Please review the request and payment.','booking',3,0,'2026-10-05 06:10:46'),(55,9,'Rental Request #3 Submitted','Your booking request for Kubota L3408 4WD Farm Tractor has been submitted. Total amount: Γé▒3,500.00','booking',3,1,'2026-10-05 06:10:46'),(56,9,'Rental Request #3 Confirmed','The equipment owner has updated your booking for Kubota L3408 4WD Farm Tractor to \'Confirmed\'.','booking',3,1,'2026-10-05 06:12:52'),(57,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:12:59'),(58,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:04'),(59,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:08'),(60,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:13:11'),(61,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:16:46'),(62,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:07'),(63,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:14'),(64,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:18'),(65,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:22'),(66,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:26'),(67,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:29'),(68,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:34'),(69,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:41'),(70,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:46'),(71,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:52'),(72,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:34:57'),(73,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 06:48:45'),(74,2,'New Order #14 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',14,1,'2026-10-05 07:12:03'),(75,5,'Order #14 Placed Successfully','Thank you for your order! Total amount: Γé▒4,050.00. Payment to seller via CASH','order',14,1,'2026-10-05 07:12:03'),(76,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 08:49:02'),(77,9,'Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental booking #3 ends on 2026-10-06. Please prepare the machine for return.','booking',3,1,'2026-10-05 10:35:10'),(78,3,'New Rental Booking #4','A user has requested to rent your Yanmar Rice Combine Harvester from 2026-10-05 to 2026-10-22. Total fee: Γé▒122,400.00 (Includes Γé▒20,400.00 20% security deposit for damage/loss). Please review the request.','booking',4,0,'2026-10-05 11:00:17'),(79,5,'Rental Request #4 Submitted','Your booking request for Yanmar Rice Combine Harvester has been submitted. Total amount: Γé▒122,400.00 (Includes Γé▒20,400.00 20% refundable damage deposit).','booking',4,1,'2026-10-05 11:00:17'),(80,2,'New Order #18 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',18,1,'2026-10-05 15:01:14'),(81,5,'Order #18 Placed Successfully','Thank you for your order! Total amount: Γé▒49,500.00. Payment to seller via CASH','order',18,1,'2026-10-05 15:01:14'),(82,2,'New Order #19 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',19,1,'2026-10-05 15:02:33'),(83,5,'Order #19 Placed Successfully','Thank you for your order! Total amount: Γé▒600.00. Payment to seller via CASH','order',19,1,'2026-10-05 15:02:33'),(84,3,'New Rental Booking #5','A user has requested to rent your Yanmar Rice Combine Harvester from 2026-10-06 to 2026-10-07. Total fee: Γé▒7,200.00 (Includes Γé▒1,200.00 20% security deposit for damage/loss). Please review the request.','booking',5,0,'2026-10-06 01:43:57'),(85,5,'Rental Request #5 Submitted','Your booking request for Yanmar Rice Combine Harvester has been submitted. Total amount: Γé▒7,200.00 (Includes Γé▒1,200.00 20% refundable damage deposit).','booking',5,1,'2026-10-06 01:43:57'),(86,5,'Rental Request #5 Confirmed','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Confirmed\'.','booking',5,1,'2026-10-06 01:45:14'),(87,5,'Rental Request #5 Ongoing','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Ongoing\'.','booking',5,1,'2026-10-06 01:45:27'),(88,5,'ΓÜá∩╕Å Rental Ending Soon: Yanmar Rice Combine Harvester','Your rental period for Yanmar Rice Combine Harvester (Booking #5) expires on October 07, 2026. An SMS reminder has been sent to your mobile phone (09999704103). Please coordinate equipment return with the owner.','booking',5,1,'2026-10-06 01:45:47'),(89,2,'New Order #20 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',20,1,'2026-10-06 02:07:29'),(90,5,'Order #20 Placed Successfully','Thank you for your order! Total amount: Γé▒14,000.00. Payment to seller via CASH','order',20,1,'2026-10-06 02:07:29'),(91,5,'Order #20 Status: Confirmed','Your order #20 has been marked as \'confirmed\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:24'),(92,5,'Order #20 Status: Confirmed','Your order #20 has been marked as \'confirmed\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:28'),(93,5,'Order #20 Status: Processing','Your order #20 has been marked as \'processing\' by the farmer/seller.','order',20,1,'2026-10-06 02:08:32'),(94,2,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',2,1,'2026-10-06 03:55:14'),(95,9,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',9,1,'2026-10-06 03:55:29'),(96,7,'Identity & Face Verification: Rejected','Your identity verification status was updated to \'rejected\'. Please check your profile.','system',7,0,'2026-10-06 03:55:48'),(97,1,'New Listing Report #2','A user reported a product listing (Reason: Misleading). Review required.','system',2,0,'2026-10-06 05:04:12'),(98,9,'ΓÜá∩╕Å Rental Ending Soon: Kubota L3408 4WD Farm Tractor','Your rental period for Kubota L3408 4WD Farm Tractor (Booking #3) expires on October 06, 2026. An SMS reminder has been sent to your mobile phone (09058642483). Please coordinate equipment return with the owner.','booking',3,1,'2026-10-06 07:12:00'),(99,5,'Rental Request #4 Confirmed','The equipment owner has updated your booking for Yanmar Rice Combine Harvester to \'Confirmed\'.','booking',4,1,'2026-10-06 07:23:59'),(100,5,'ΓÜá∩╕Å Rental Ending Soon: Yanmar Rice Combine Harvester','Your rental period for Yanmar Rice Combine Harvester (Booking #5) expires on October 07, 2026. An SMS reminder has been sent to your mobile phone (09999704103). Please coordinate equipment return with the owner.','booking',5,1,'2026-10-07 00:54:22'),(101,1,'New User Registered: jheradel Urganay','jheradel Urganay (allenkhurtmallari22@gmail.com) has created an account on AgriMart.','system',12,0,'2026-10-07 01:14:36'),(102,3,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',3,0,'2026-10-07 02:39:39'),(103,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 1 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒145.83) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-07 03:51:30'),(104,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (1 day(s) late). An accumulated 5% daily penalty of Γé▒145.83 has been recorded.','booking',3,0,'2026-10-07 03:51:30'),(105,8,'ΓÜá∩╕Å Rental Ending Soon: tooothpick','Your rental period for tooothpick (Booking #1) expires on October 09, 2026. An SMS reminder has been dispatched to your mobile phone (09605358372). Please return the machinery to avoid late penalties (5%/day).','booking',1,1,'2026-10-08 02:35:33'),(106,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 2 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒291.66) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-08 02:35:33'),(107,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (2 day(s) late). An accumulated 5% daily penalty of Γé▒291.66 has been recorded.','booking',3,0,'2026-10-08 02:35:33'),(108,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 1 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒300.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-08 02:35:33'),(109,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (1 day(s) late). An accumulated 5% daily penalty of Γé▒300.00 has been recorded.','booking',5,0,'2026-10-08 02:35:33'),(110,2,'New Order #21 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',21,1,'2026-10-08 04:54:55'),(111,5,'Order #21 Placed Successfully','Thank you for your order! Total amount: Γé▒450.00. Payment to seller via CASH','order',21,1,'2026-10-08 04:54:55'),(112,2,'New Order #22 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',22,1,'2026-10-08 07:42:55'),(113,5,'Order #22 Placed Successfully','Thank you for your order! Total amount: Γé▒75.00. Payment to seller via CASH','order',22,1,'2026-10-08 07:42:55'),(114,5,'New Rental Booking #6','A user has requested to rent your pump 2026-10-17 (45 hours). Total fee: Γé▒16,199.46 (Includes Γé▒2,699.91 20% security deposit for damage/loss). Please review the request.','booking',6,1,'2026-10-08 08:09:12'),(115,10,'Rental Request #6 Submitted','Your booking request for pump (2026-10-17 (45 hours)) has been submitted. Total amount: Γé▒16,199.46 (Includes Γé▒2,699.91 20% refundable damage deposit).','booking',6,0,'2026-10-08 08:09:12'),(116,10,'Rental Request #6 Confirmed','The equipment owner has updated your booking for pump to \'Confirmed\'.','booking',6,0,'2026-10-08 08:10:53'),(117,1,'New User Registered: Rhod herald pagaran','Rhod herald pagaran (rhod123@gmail.com) has created an account on AgriMart.','system',13,0,'2026-10-08 09:49:46'),(118,1,'New Listing Report #1','A user reported a product listing (Reason: Misleading). Review required.','system',1,0,'2026-10-08 14:05:04'),(119,8,'ΓÜá∩╕Å Rental Ending Soon: tooothpick','Your rental period for tooothpick (Booking #1) expires on October 09, 2026. An SMS reminder has been dispatched to your mobile phone (09605358372). Please return the machinery to avoid late penalties (5%/day).','booking',1,1,'2026-10-08 23:32:12'),(120,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 3 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒437.49) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-08 23:32:12'),(121,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (3 day(s) late). An accumulated 5% daily penalty of Γé▒437.49 has been recorded.','booking',3,0,'2026-10-08 23:32:12'),(122,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 2 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒600.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-08 23:32:12'),(123,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (2 day(s) late). An accumulated 5% daily penalty of Γé▒600.00 has been recorded.','booking',5,0,'2026-10-08 23:32:12'),(124,1,'New User Registered: Christian lucero','Christian lucero (christianlucero5@gmail.com) has created an account on AgriMart.','system',14,0,'2026-10-08 23:38:37'),(125,2,'New Order #23 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',23,1,'2026-10-09 01:23:44'),(126,10,'Order #23 Placed Successfully','Thank you for your order! Total amount: Γé▒14,850.00. Payment to seller via CASH','order',23,0,'2026-10-09 01:23:44'),(127,1,'New Listing Report #2','A user reported a product listing (Reason: Misleading). Review required.','system',2,0,'2026-10-09 01:38:31'),(128,1,'New User Registered: mark anthony tenepere','mark anthony tenepere (tenepermarkanthony@gmail.com) has created an account on AgriMart.','system',15,0,'2026-10-09 02:19:35'),(129,1,'New User Registered: mark anthony tenepere','mark anthony tenepere (teneperemarkanthony@gmail.com) has created an account on AgriMart.','system',16,0,'2026-10-09 02:22:10'),(130,2,'New Order #24 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',24,0,'2026-10-09 03:13:57'),(131,8,'Order #24 Placed Successfully','Thank you for your order! Total amount: Γé▒675.00. Payment to seller via CASH','order',24,1,'2026-10-09 03:13:57'),(132,8,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on tooothpick','Your rental for tooothpick (Booking #1) is 1 day(s) past the return date (October 09, 2026). A 5% daily late penalty (Γé▒9.17/day ΓÇó Total Accumulated: Γé▒9.17) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',1,1,'2026-10-10 01:38:39'),(133,5,'ΓÜá∩╕Å Renter Overdue: tooothpick (Booking #1)','Renter Jemaica R. Bancud has not returned tooothpick (Booking #1), which was due on October 09, 2026 (1 day(s) late). An accumulated 5% daily penalty of Γé▒9.17 has been recorded.','booking',1,1,'2026-10-10 01:38:39'),(134,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 4 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒583.32) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-10 01:38:39'),(135,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (4 day(s) late). An accumulated 5% daily penalty of Γé▒583.32 has been recorded.','booking',3,0,'2026-10-10 01:38:39'),(136,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 3 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒900.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-10 01:38:39'),(137,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (3 day(s) late). An accumulated 5% daily penalty of Γé▒900.00 has been recorded.','booking',5,0,'2026-10-10 01:38:39'),(138,1,'New User Registered: shane racel','shane racel (christianlucero658@gmail.com) has created an account on AgriMart.','system',17,0,'2026-10-10 01:49:56'),(139,2,'New Order #25 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',25,0,'2026-10-10 03:46:48'),(140,10,'Order #25 Placed Successfully','Thank you for your order! Total amount: Γé▒4,500.00. Payment to seller via CASH','order',25,0,'2026-10-10 03:46:48'),(141,8,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on tooothpick','Your rental for tooothpick (Booking #1) is 2 day(s) past the return date (October 09, 2026). A 5% daily late penalty (Γé▒9.17/day ΓÇó Total Accumulated: Γé▒18.34) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',1,1,'2026-10-11 02:09:52'),(142,5,'ΓÜá∩╕Å Renter Overdue: tooothpick (Booking #1)','Renter Jemaica R. Bancud has not returned tooothpick (Booking #1), which was due on October 09, 2026 (2 day(s) late). An accumulated 5% daily penalty of Γé▒18.34 has been recorded.','booking',1,1,'2026-10-11 02:09:52'),(143,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 5 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒729.15) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-11 02:09:52'),(144,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (5 day(s) late). An accumulated 5% daily penalty of Γé▒729.15 has been recorded.','booking',3,0,'2026-10-11 02:09:52'),(145,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 4 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒1,200.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-11 02:09:52'),(146,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (4 day(s) late). An accumulated 5% daily penalty of Γé▒1,200.00 has been recorded.','booking',5,0,'2026-10-11 02:09:52'),(147,8,'Rental Request #1 Confirmed','The equipment owner has updated your booking for tooothpick to \'Confirmed\'.','booking',1,1,'2026-10-11 02:10:59'),(148,2,'≡ƒ¢æ Product Listing Deactivated by Admin: Native Sweet Calamansi Seedlings','Your product listing \"Native Sweet Calamansi Seedlings\" has been deactivated by AgriMart Administration following a report investigation (Reason: Deactivated by administration due to report findings (Misleading).). Only an administrator can reactivate this listing. If you have resolved the issue, you may submit a retrieval appeal message directly in \"My Products\".','system',3,1,'2026-10-11 02:33:42'),(149,2,'Γ£à Product Listing Reactivated: Native Sweet Calamansi Seedlings','Your product listing \"Native Sweet Calamansi Seedlings\" has been reactivated by AgriMart Administration and is now active for buyers on the marketplace.','system',3,0,'2026-10-11 02:34:33'),(150,2,'New Order #26 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',26,0,'2026-10-11 02:42:00'),(151,5,'Order #26 Placed Successfully','Thank you for your order! Total amount: Γé▒300.00. Payment to seller via CASH','order',26,1,'2026-10-11 02:42:00'),(152,5,'Γ£à Equipment Reactivated by Admin: tooothpick','Your machinery \"tooothpick\" has been reactivated by administration and is now live for rental.','system',6,1,'2026-10-11 10:42:23'),(153,8,'Γ£à Rental Completed: tooothpick','Your rental for tooothpick (Booking #1) has been marked as returned and completed. A 5% daily late penalty of Γé▒18.34 (2 day(s) overdue) was deducted from your security deposit. Net deposit refund: Γé▒18.33. You can now rate and review this machinery.','booking',1,1,'2026-10-11 10:42:56'),(154,5,'Γ£à Machine Returned & Completed: tooothpick','Booking #1 for tooothpick has been finalized and marked returned by Jemaica R. Bancud. Late penalty applied: Γé▒18.34 (2 day(s) @ 5%/day). The machine is now marked available for new bookings.','booking',1,1,'2026-10-11 10:42:56'),(155,5,'≡ƒ¢æ Equipment Deactivated by Admin: tooothpick','Your machinery \"tooothpick\" was deactivated by administration. Only an administrator can reactivate it. Submit a retrieval appeal in My Equipment to appeal.','system',6,1,'2026-10-11 10:43:09'),(156,5,'Γ£à Equipment Reactivated by Admin: tooothpick','Your machinery \"tooothpick\" has been reactivated by administration and is now live for rental.','system',6,1,'2026-10-11 10:43:13'),(157,10,'Γ£à Rental Completed: pump','Your rental for pump (Booking #6) has been marked as returned and completed in good standing. Your Γé▒2,699.91 (20%) security deposit has been released. You can now rate and review this machinery.','booking',6,0,'2026-10-11 10:45:39'),(158,5,'Γ£à Machine Returned & Completed: pump','Booking #6 for pump has been finalized and marked returned by Christian lucero. The machine is now marked available for new bookings.','booking',6,1,'2026-10-11 10:45:39'),(159,2,'New Order #27 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',27,0,'2026-10-11 11:00:13'),(160,8,'Order #27 Placed Successfully','Thank you for your order! Total amount: Γé▒75.00. Payment to seller via CASH','order',27,1,'2026-10-11 11:00:13'),(161,19,'Identity & Face Verification: Rejected','Your identity verification status was updated to \'rejected\'. Please check your profile.','system',19,0,'2026-10-12 03:26:22'),(162,19,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',19,0,'2026-10-12 03:26:30'),(163,9,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is 6 day(s) past the return date (October 06, 2026). A 5% daily late penalty (Γé▒145.83/day ΓÇó Total Accumulated: Γé▒874.98) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',3,0,'2026-10-12 03:27:09'),(164,3,'ΓÜá∩╕Å Renter Overdue: Kubota L3408 4WD Farm Tractor (Booking #3)','Renter Christian lucero has not returned Kubota L3408 4WD Farm Tractor (Booking #3), which was due on October 06, 2026 (6 day(s) late). An accumulated 5% daily penalty of Γé▒874.98 has been recorded.','booking',3,0,'2026-10-12 03:27:09'),(165,5,'≡ƒÜ¿ OVERDUE: 5% Daily Penalty on Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) is 5 day(s) past the return date (October 07, 2026). A 5% daily late penalty (Γé▒300.00/day ΓÇó Total Accumulated: Γé▒1,500.00) is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.','booking',5,1,'2026-10-12 03:27:09'),(166,3,'ΓÜá∩╕Å Renter Overdue: Yanmar Rice Combine Harvester (Booking #5)','Renter Christian Lucero has not returned Yanmar Rice Combine Harvester (Booking #5), which was due on October 07, 2026 (5 day(s) late). An accumulated 5% daily penalty of Γé▒1,500.00 has been recorded.','booking',5,0,'2026-10-12 03:27:09'),(167,9,'Γ£à Rental Completed: Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) has been marked as returned and completed. A 5% daily late penalty of Γé▒874.98 (6 day(s) overdue) was deducted from your security deposit. Net deposit refund: Γé▒0.00. You can now rate and review this machinery.','booking',3,0,'2026-10-12 03:27:37'),(168,3,'Γ£à Machine Returned & Completed: Kubota L3408 4WD Farm Tractor','Booking #3 for Kubota L3408 4WD Farm Tractor has been finalized and marked returned by Christian lucero. Late penalty applied: Γé▒874.98 (6 day(s) @ 5%/day). The machine is now marked available for new bookings.','booking',3,0,'2026-10-12 03:27:37'),(169,1,'KYC Verification Submitted','Christian Lucero has updated their identification documents / face verification for review.','system',5,0,'2026-10-12 03:54:27'),(170,19,'KYC Verification Submitted','Christian Lucero has updated their identification documents / face verification for review.','system',5,0,'2026-10-12 03:54:27'),(172,5,'Identity & Face Verification: Verified','Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart.','system',5,1,'2026-10-12 03:54:54'),(173,2,'Γ£à Product Approved: Certified Inbred RC-222 Rice Seeds','Your product listing \"Certified Inbred RC-222 Rice Seeds\" has been approved by administration and is now live on the marketplace.','system',1,0,'2026-10-12 04:29:01'),(174,2,'Γ£à Product Listing Reactivated: Certified Inbred RC-222 Rice Seeds','Your product listing \"Certified Inbred RC-222 Rice Seeds\" has been reactivated by AgriMart Administration and is now active for buyers on the marketplace.','system',1,0,'2026-10-12 05:00:38'),(175,2,'≡ƒ¢æ Product Listing Rejected: Organic Vermicast Soil Enhancer','Your product \"Organic Vermicast Soil Enhancer\" was rejected by administration. Reason: Listing rejected by administrator','system',4,0,'2026-10-12 05:01:22'),(176,2,'Γ£à Product Approved: Organic Vermicast Soil Enhancer','Your product listing \"Organic Vermicast Soil Enhancer\" has been approved by administration and is now live on the marketplace.','system',4,0,'2026-10-12 05:04:53'),(177,1,'KYC Verification Submitted','Jemaica R. Bancud has updated their identification documents / face verification for review.','system',8,0,'2026-10-12 05:07:04'),(178,19,'KYC Verification Submitted','Jemaica R. Bancud has updated their identification documents / face verification for review.','system',8,0,'2026-10-12 05:07:04'),(180,8,'Identity & Face Verification: Rejected','Your identity verification status was updated to \'rejected\'. Please check your profile.','system',8,0,'2026-10-12 05:07:29'),(181,2,'≡ƒ¢æ Product Listing Rejected: Organic Vermicast Soil Enhancer','Your product \"Organic Vermicast Soil Enhancer\" was rejected by administration. Reason: Listing rejected by administrator','system',4,0,'2026-10-12 05:08:46'),(182,2,'Γ£à Product Approved: Organic Vermicast Soil Enhancer','Your product listing \"Organic Vermicast Soil Enhancer\" has been approved by administration and is now live on the marketplace.','system',4,0,'2026-10-12 05:08:50'),(183,3,'≡ƒ¢æ Equipment Listing Rejected: Yanmar Rice Combine Harvester','Your machinery \"Yanmar Rice Combine Harvester\" was rejected by administration. Reason: Equipment rejected by administrator','system',2,0,'2026-10-12 05:08:53'),(184,5,'New Order #28 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',28,1,'2026-10-12 05:24:39'),(185,8,'Order #28 Placed Successfully','Thank you for your order! Total amount: Γé▒30.00. Payment to seller via CASH','order',28,0,'2026-10-12 05:24:39'),(186,8,'Order #28 Status: Processing','Your order #28 has been marked as \'processing\' by the farmer/seller.','order',28,0,'2026-10-12 05:26:13'),(187,8,'Order #28 Status: Completed','Your order #28 has been marked as \'completed\' by the farmer/seller.','order',28,0,'2026-10-12 05:27:54'),(188,3,'New Rental Booking #7','A user has requested to rent your Kubota L3408 4WD Farm Tractor from 2026-10-12 to 2026-10-13 (1 day). Total fee: Γé▒4,200.00 (Includes Γé▒700.00 20% security deposit for damage/loss). Please review the request.','booking',7,0,'2026-10-12 05:37:16'),(189,5,'Rental Request #7 Submitted','Your booking request for Kubota L3408 4WD Farm Tractor (from 2026-10-12 to 2026-10-13 (1 day)) has been submitted. Total amount: Γé▒4,200.00 (Includes Γé▒700.00 20% refundable damage deposit).','booking',7,1,'2026-10-12 05:37:16'),(190,1,'≡ƒô⌐ Equipment Retrieval Appeal: Yanmar Rice Combine Harvester','Seller Pedro Penduko has submitted a retrieval appeal for deactivated machinery \"Yanmar Rice Combine Harvester\": \"i sent the wrong price\". Review in Admin Moderation.','system',2,0,'2026-10-12 05:38:04'),(191,19,'≡ƒô⌐ Equipment Retrieval Appeal: Yanmar Rice Combine Harvester','Seller Pedro Penduko has submitted a retrieval appeal for deactivated machinery \"Yanmar Rice Combine Harvester\": \"i sent the wrong price\". Review in Admin Moderation.','system',2,0,'2026-10-12 05:38:04'),(192,5,'Γ£à Rental Completed: Kubota L3408 4WD Farm Tractor','Your rental for Kubota L3408 4WD Farm Tractor (Booking #7) has been marked as returned and completed in good standing. Your Γé▒700.00 (20%) security deposit has been released. You can now rate and review this machinery.','booking',7,1,'2026-10-12 05:38:15'),(193,3,'Γ£à Machine Returned & Completed: Kubota L3408 4WD Farm Tractor','Booking #7 for Kubota L3408 4WD Farm Tractor has been finalized and marked returned by Christian Lucero. The machine is now marked available for new bookings.','booking',7,0,'2026-10-12 05:38:15'),(194,5,'Γ£à Rental Completed: Yanmar Rice Combine Harvester','Your rental for Yanmar Rice Combine Harvester (Booking #5) has been marked as returned and completed. A 5% daily late penalty of Γé▒1,500.00 (5 day(s) overdue) was deducted from your security deposit. Net deposit refund: Γé▒0.00. You can now rate and review this machinery.','booking',5,1,'2026-10-12 05:38:19'),(195,3,'Γ£à Machine Returned & Completed: Yanmar Rice Combine Harvester','Booking #5 for Yanmar Rice Combine Harvester has been finalized and marked returned by Christian Lucero. Late penalty applied: Γé▒1,500.00 (5 day(s) @ 5%/day). The machine is now marked available for new bookings.','booking',5,0,'2026-10-12 05:38:19'),(196,3,'Γ£à Listing Reinstated: Yanmar Rice Combine Harvester','Great news! AgriMart Administration has approved your retrieval appeal for \"Yanmar Rice Combine Harvester\". Your listing has been reactivated and is now visible in the marketplace again.','system',2,0,'2026-10-12 05:38:40'),(197,1,'New User Registered: Christian lucero','Christian lucero (wqj@gmail.com) has created an account on AgriMart.','system',20,0,'2026-10-12 05:44:26'),(198,19,'New User Registered: Christian lucero','Christian lucero (wqj@gmail.com) has created an account on AgriMart.','system',20,0,'2026-10-12 05:44:26'),(200,2,'New Order #29 Received','A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.','order',29,0,'2026-10-12 05:46:48'),(201,5,'Order #29 Placed Successfully','Thank you for your order! Total amount: Γé▒12,500.00. Payment to seller via CASH','order',29,0,'2026-10-12 05:46:48'),(202,5,'Order #29 Status: Completed','Your order #29 has been marked as \'completed\' by the farmer/seller.','order',29,0,'2026-10-12 05:47:18'),(203,2,'Γ£à Product Listing Reactivated: Certified Inbred RC-222 Rice Seeds','Your product listing \"Certified Inbred RC-222 Rice Seeds\" has been reactivated by administration and is now live on the marketplace.','system',1,0,'2026-10-12 05:48:09'),(204,1,'KYC Verification Submitted','Jemaica R. Bancud has updated their identification documents / face verification for review.','system',8,0,'2026-10-12 06:18:58'),(205,19,'KYC Verification Submitted','Jemaica R. Bancud has updated their identification documents / face verification for review.','system',8,0,'2026-10-12 06:18:58'),(206,9,'Identity & Face Verification: Pending','Your identity verification status was updated to \'pending\'. Please check your profile.','system',9,0,'2026-10-12 11:29:11');
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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,2,1,1250.00,1250.00),(2,1,2,2,1,450.00,450.00),(3,2,1,2,14,1250.00,17500.00),(4,3,1,2,1,1250.00,1250.00),(5,4,2,2,1,450.00,450.00),(7,6,1,2,1,1250.00,1250.00),(8,7,1,2,2,1250.00,2500.00),(9,8,1,2,1,1250.00,1250.00),(14,13,1,2,66,1250.00,82500.00),(15,14,2,2,9,450.00,4050.00),(19,18,2,2,110,450.00,49500.00),(20,19,3,2,8,75.00,600.00),(21,20,4,2,50,280.00,14000.00),(22,21,2,2,1,450.00,450.00),(23,22,3,2,1,75.00,75.00),(24,23,2,2,33,450.00,14850.00),(25,24,3,2,9,75.00,675.00),(26,25,2,2,10,450.00,4500.00),(27,26,3,2,4,75.00,300.00),(28,27,3,2,1,75.00,75.00),(29,28,8,5,1,30.00,30.00),(30,29,1,2,10,1250.00,12500.00);
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
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('buyer','seller','admin') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_order_buyer` (`buyer_id`),
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,5,1700.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','cancelled','2026-08-25 09:06:16','2026-08-25 09:06:41'),(2,5,17500.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','completed','2026-08-25 09:20:35','2026-10-12 05:41:00'),(3,5,1250.00,'malubibit norte,flora, apayao,3810','completed','2026-08-25 09:41:44','2026-10-12 05:41:00'),(4,5,450.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','completed','2026-08-27 02:01:58','2026-10-12 05:41:00'),(6,5,1250.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','completed','2026-09-02 14:03:54','2026-09-02 14:05:11'),(7,5,2500.00,'Maharlika Highway, Poblacion, Urdaneta City, Pangasinan, 2428','completed','2026-09-02 14:15:53','2026-10-12 05:41:00'),(8,7,1250.00,'6C6X+MPQ Malubibit Norte','completed','2026-09-02 14:26:40','2026-10-12 05:41:00'),(13,5,82500.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-03 05:47:59','2026-10-12 05:41:00'),(14,5,4050.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-05 07:12:03','2026-10-12 05:41:00'),(18,5,49500.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-05 15:01:14','2026-10-12 05:41:00'),(19,5,600.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-05 15:02:33','2026-10-12 05:41:00'),(20,5,14000.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-06 02:07:29','2026-10-12 05:41:00'),(21,5,450.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-08 04:54:55','2026-10-12 05:41:00'),(22,5,75.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-08 07:42:55','2026-10-12 05:41:00'),(23,10,14850.00,'6C6X+MPQ Malubibit Norte','completed','2026-10-09 01:23:44','2026-10-12 05:41:00'),(24,8,675.00,'Purok 1\r\n1358','completed','2026-10-09 03:13:57','2026-10-12 05:41:00'),(25,10,4500.00,'Purok 1\r\n1358','completed','2026-10-10 03:46:48','2026-10-12 05:41:00'),(26,5,300.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-11 02:42:00','2026-10-12 05:38:56'),(27,8,75.00,'6C6X+MPQ Malubibit Norte','completed','2026-10-11 11:00:13','2026-10-12 05:41:00'),(28,8,30.00,'Purok 1\r\n1358','completed','2026-10-12 05:24:39','2026-10-12 05:27:54'),(29,5,12500.00,'Purok 1, Malubibit Norte, Flora, Apayao, 3810','completed','2026-10-12 05:46:48','2026-10-12 05:47:18');
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,5,1,NULL,'cash',NULL,NULL,NULL,1700.00,'refunded','',NULL,NULL,'2026-08-25 09:06:16'),(2,5,2,NULL,'bank_transfer',NULL,NULL,NULL,17500.00,'paid','',NULL,'2026-08-25 11:20:35','2026-08-25 09:20:35'),(3,5,3,NULL,'cash',NULL,NULL,NULL,1250.00,'paid','',NULL,NULL,'2026-08-25 09:41:44'),(4,5,4,NULL,'cash',NULL,NULL,NULL,450.00,'paid','',NULL,NULL,'2026-08-27 02:01:59'),(5,5,6,NULL,'cash','GCash','','',1250.00,'paid','0',NULL,NULL,'2026-09-02 14:03:54'),(6,5,7,NULL,'cash','GCash','','',2500.00,'paid','0',NULL,NULL,'2026-09-02 14:15:53'),(7,7,8,NULL,'cash','GCash','','',1250.00,'paid','0',NULL,NULL,'2026-09-02 14:26:40'),(8,8,NULL,1,'cash','GCash','','',220.00,'paid','0',NULL,'2026-10-11 18:42:56','2026-09-29 09:05:03'),(9,5,13,NULL,'cash','GCash','','',82500.00,'paid','0',NULL,NULL,'2026-10-03 05:47:59'),(10,8,NULL,2,'cash','GCash','','',3500.00,'paid','0',NULL,NULL,'2026-10-05 05:22:14'),(11,9,NULL,3,'cash','GCash','','',3500.00,'paid','0',NULL,'2026-10-12 11:27:37','2026-10-05 06:10:46'),(12,5,14,NULL,'cash','GCash','','',4050.00,'paid','0',NULL,NULL,'2026-10-05 07:12:03'),(13,5,NULL,4,'cash','GCash','','',122400.00,'paid','0',NULL,NULL,'2026-10-05 11:00:17'),(14,5,18,NULL,'cash','GCash','','',49500.00,'paid','0',NULL,NULL,'2026-10-05 15:01:14'),(15,5,19,NULL,'cash','GCash','','',600.00,'paid','0',NULL,NULL,'2026-10-05 15:02:33'),(16,5,NULL,5,'cash','GCash','','',7200.00,'paid','0',NULL,'2026-10-12 13:38:19','2026-10-06 01:43:57'),(17,5,20,NULL,'cash','GCash','','',14000.00,'paid','0',NULL,NULL,'2026-10-06 02:07:29'),(18,5,21,NULL,'cash','GCash','','',450.00,'paid','0',NULL,NULL,'2026-10-08 04:54:55'),(19,5,22,NULL,'cash','GCash','','',75.00,'paid','0',NULL,NULL,'2026-10-08 07:42:55'),(20,10,NULL,6,'cash','GCash','','',16199.46,'paid','0',NULL,'2026-10-11 18:45:39','2026-10-08 08:09:12'),(21,10,23,NULL,'cash','GCash','','',14850.00,'paid','0',NULL,NULL,'2026-10-09 01:23:44'),(22,8,24,NULL,'cash','GCash','','',675.00,'paid','0',NULL,NULL,'2026-10-09 03:13:57'),(23,10,25,NULL,'cash','GCash','','',4500.00,'paid','0',NULL,NULL,'2026-10-10 03:46:48'),(24,5,26,NULL,'cash','GCash','','',300.00,'paid','0',NULL,NULL,'2026-10-11 02:42:00'),(25,8,27,NULL,'cash','GCash','','',75.00,'paid','0',NULL,NULL,'2026-10-11 11:00:13'),(26,8,28,NULL,'cash','GCash','','',30.00,'paid','0',NULL,NULL,'2026-10-12 05:24:39'),(27,5,NULL,7,'cash','GCash','','',4200.00,'paid','0',NULL,'2026-10-12 13:38:15','2026-10-12 05:37:16'),(28,5,29,NULL,'cash','GCash','','',12500.00,'paid','0',NULL,NULL,'2026-10-12 05:46:48');
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
  `location_city` varchar(100) DEFAULT NULL,
  `location_province` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','sold','deleted','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_deactivated` tinyint(1) NOT NULL DEFAULT 0,
  `deactivation_reason` varchar(255) DEFAULT NULL,
  `retrieval_message` text DEFAULT NULL,
  `retrieval_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `retrieval_requested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  KEY `idx_product_user` (`user_id`),
  KEY `idx_product_category` (`category_id`),
  KEY `idx_product_name` (`product_name`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_product_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,2,1,'Certified Inbred RC-222 Rice Seeds','High-yielding inbred rice variety resistant to pests and suitable for wet and dry planting seasons.',1250.00,0,'sack (20kg)','images/product-rice.svg','active','2026-08-25 08:40:28','2026-10-12 05:48:09',0,NULL,NULL,'approved',NULL),(2,2,2,'Diamante Max Hybrid Tomato Seeds','Heavy-bearing, heat-tolerant hybrid tomato seed packet with high germination rate and firm fruits.',450.00,10,'pack','images/product-tomato.svg','active','2026-08-25 08:40:28','2026-10-12 04:53:58',0,NULL,NULL,'none',NULL),(3,2,3,'Native Sweet Calamansi Seedlings','Grafted calamansi seedlings ready for orchard transplanting. Fast-growing and continuous bearer.',75.00,277,'seedling','images/product-calamansi.svg','active','2026-08-25 08:40:28','2026-10-11 11:00:13',0,NULL,NULL,'approved',NULL),(4,2,5,'Organic Vermicast Soil Enhancer','Pure organic vermicompost rich in micronutrients and beneficial microbes for soil revitalisation.',280.00,44,'bag (50kg)','images/placeholder-product.svg','active','2026-08-25 08:40:28','2026-10-12 05:08:50',0,NULL,NULL,'approved',NULL),(5,5,2,'Sweet Corn Hybrid F1 Seeds','Top-quality yellow sweet corn seeds with uniform ear size and outstanding sweetness.',520.00,65,'pack (1kg)','images/placeholder-product.svg','active','2026-08-25 08:40:28','2026-10-12 04:57:03',0,NULL,NULL,'approved',NULL),(8,5,4,'Diamante Max Hybrid Tomato Seeds','',30.00,93,'pack',NULL,'active','2026-10-12 05:10:55','2026-10-12 05:24:39',0,NULL,NULL,'none',NULL);
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
INSERT INTO `reports` VALUES (1,8,2,3,NULL,'product','misleading','price does not meet the posted price','dismissed','Report reviewed and dismissed. No violation detected.',1,'2026-10-08 14:05:04','2026-10-12 05:23:47'),(2,8,2,1,NULL,'product','misleading','over priced','dismissed','Report reviewed and dismissed. No violation detected.',1,'2026-10-09 01:38:31','2026-10-11 02:34:41');
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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
INSERT INTO `sms_logs` VALUES (1,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on 2026-10-09. Please prepare the machine for return at vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-03 14:23:44'),(2,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on 2026-10-09. Please prepare the machine for return at vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-05 13:34:57'),(3,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:12:59'),(4,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:04'),(5,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:08'),(6,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:13:11'),(7,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:16:46'),(8,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:07'),(9,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:14'),(10,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:18'),(11,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:22'),(12,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:26'),(13,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:29'),(14,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:34'),(15,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:41'),(16,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:46'),(17,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:52'),(18,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:34:57'),(19,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 14:48:45'),(20,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 16:49:02'),(21,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on 2026-10-06. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-05 18:35:10'),(22,5,'09999704103','AgriMart Alert: Your rental for Yanmar Rice Combine Harvester (Booking #5) is scheduled to end on October 07, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-06 09:45:47'),(23,9,'09058642483','AgriMart Alert: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is scheduled to end on October 06, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-06 15:12:00'),(24,5,'09999704103','AgriMart Alert: Your rental for Yanmar Rice Combine Harvester (Booking #5) is scheduled to end on October 07, 2026. Please prepare the machine for return at flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-07 08:54:22'),(25,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 1 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒145.83) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-07 11:51:30'),(26,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on October 09, 2026. Please prepare the machine for return at vskjsvsn to avoid a 5% daily late penalty. Owner: Christian Lucero (09999704103).','delivered','2026-10-08 10:35:33'),(27,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 2 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒291.66) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-08 10:35:33'),(28,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 1 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒300.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-08 10:35:33'),(29,8,'09605358372','AgriMart Alert: Your rental for tooothpick (Booking #1) is scheduled to end on October 09, 2026. Please prepare the machine for return at vskjsvsn to avoid a 5% daily late penalty. Owner: Christian Lucero (09999704103).','delivered','2026-10-09 07:32:12'),(30,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 3 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒437.49) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-09 07:32:12'),(31,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 2 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒600.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-09 07:32:12'),(32,8,'09605358372','AgriMart OVERDUE ALERT: Your rental for tooothpick (Booking #1) was due on October 09, 2026. You are currently 1 day(s) overdue. A 5% daily late penalty of Γé▒9.17/day (Total: Γé▒9.17) is being deducted from your deposit. Return machine immediately to vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-10 09:38:39'),(33,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 4 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒583.32) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-10 09:38:39'),(34,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 3 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒900.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-10 09:38:39'),(35,8,'09605358372','AgriMart OVERDUE ALERT: Your rental for tooothpick (Booking #1) was due on October 09, 2026. You are currently 2 day(s) overdue. A 5% daily late penalty of Γé▒9.17/day (Total: Γé▒18.34) is being deducted from your deposit. Return machine immediately to vskjsvsn. Owner: Christian Lucero (09999704103).','delivered','2026-10-11 10:09:52'),(36,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 5 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒729.15) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-11 10:09:52'),(37,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 4 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒1,200.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-11 10:09:52'),(38,2,'09189876543','AgriMart Alert: Your product \'Native Sweet Calamansi Seedlings\' was deactivated by administration (Deactivated by administration due to report findings (Misleading).). Only an admin can reactivate it. Submit a retrieval request in My Products to appeal.','delivered','2026-10-11 10:33:42'),(39,2,'09189876543','AgriMart Notice: Your listing \'Native Sweet Calamansi Seedlings\' has been reactivated by administration and is now live on the platform.','delivered','2026-10-11 10:34:33'),(40,5,'09999704103','AgriMart Notice: Your machinery \'tooothpick\' has been reactivated by administration and is now live.','delivered','2026-10-11 18:42:23'),(41,8,'09605358372','AgriMart Notice: Your rental for tooothpick (Booking #1) is completed and marked RETURNED. Late return penalty: Γé▒18.34 (2 day(s) @ 5%/day). Net security deposit refund: Γé▒18.33. Thank you for renting with AgriMart! Please visit your dashboard to rate and review.','delivered','2026-10-11 18:42:56'),(42,5,'09999704103','AgriMart Alert: Your machinery \'tooothpick\' was deactivated by administration. Only an admin can reactivate it. Submit a retrieval appeal in My Equipment.','delivered','2026-10-11 18:43:09'),(43,5,'09999704103','AgriMart Notice: Your machinery \'tooothpick\' has been reactivated by administration and is now live.','delivered','2026-10-11 18:43:13'),(44,10,'09058642483','AgriMart Notice: Your rental for pump (Booking #6) is completed and marked RETURNED on time. Full 20% security deposit (Γé▒2,699.91) has been released. Thank you! Please visit your dashboard to rate and review.','delivered','2026-10-11 18:45:39'),(45,9,'09058642483','AgriMart OVERDUE ALERT: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) was due on October 06, 2026. You are currently 6 day(s) overdue. A 5% daily late penalty of Γé▒145.83/day (Total: Γé▒874.98) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-12 11:27:09'),(46,5,'09999704103','AgriMart OVERDUE ALERT: Your rental for Yanmar Rice Combine Harvester (Booking #5) was due on October 07, 2026. You are currently 5 day(s) overdue. A 5% daily late penalty of Γé▒300.00/day (Total: Γé▒1,500.00) is being deducted from your deposit. Return machine immediately to flora. Owner: Pedro Penduko (09205554321).','delivered','2026-10-12 11:27:09'),(47,9,'09058642483','AgriMart Notice: Your rental for Kubota L3408 4WD Farm Tractor (Booking #3) is completed and marked RETURNED. Late return penalty: Γé▒874.98 (6 day(s) @ 5%/day). Net security deposit refund: Γé▒0.00. Thank you for renting with AgriMart! Please visit your dashboard to rate and review.','delivered','2026-10-12 11:27:37'),(48,2,'09189876543','AgriMart Notice: Your product \'Certified Inbred RC-222 Rice Seeds\' was approved and is now live on the marketplace!','delivered','2026-10-12 12:29:01'),(49,2,'09189876543','AgriMart Notice: Your listing \'Certified Inbred RC-222 Rice Seeds\' has been reactivated by administration and is now live on the platform.','delivered','2026-10-12 13:00:38'),(50,2,'09189876543','AgriMart Alert: Your product \'Organic Vermicast Soil Enhancer\' was rejected. Reason: Listing rejected by administrator','delivered','2026-10-12 13:01:22'),(51,2,'09189876543','AgriMart Notice: Your product \'Organic Vermicast Soil Enhancer\' was approved and is now live on the marketplace!','delivered','2026-10-12 13:04:53'),(52,2,'09189876543','AgriMart Alert: Your product \'Organic Vermicast Soil Enhancer\' was rejected. Reason: Listing rejected by administrator','delivered','2026-10-12 13:08:46'),(53,2,'09189876543','AgriMart Notice: Your product \'Organic Vermicast Soil Enhancer\' was approved and is now live on the marketplace!','delivered','2026-10-12 13:08:50'),(54,3,'09205554321','AgriMart Alert: Your machinery \'Yanmar Rice Combine Harvester\' was rejected. Reason: Equipment rejected by administrator','delivered','2026-10-12 13:08:53'),(55,5,'09999704103','AgriMart Notice: Your rental for Kubota L3408 4WD Farm Tractor (Booking #7) is completed and marked RETURNED on time. Full 20% security deposit (Γé▒700.00) has been released. Thank you! Please visit your dashboard to rate and review.','delivered','2026-10-12 13:38:15'),(56,5,'09999704103','AgriMart Notice: Your rental for Yanmar Rice Combine Harvester (Booking #5) is completed and marked RETURNED. Late return penalty: Γé▒1,500.00 (5 day(s) @ 5%/day). Net security deposit refund: Γé▒0.00. Thank you for renting with AgriMart! Please visit your dashboard to rate and review.','delivered','2026-10-12 13:38:19'),(57,3,'09205554321','AgriMart Notice: Your retrieval appeal for \'Yanmar Rice Combine Harvester\' has been APPROVED by administration! Your listing is now active and public again.','delivered','2026-10-12 13:38:40'),(58,2,'09189876543','AgriMart Notice: Your product \'Certified Inbred RC-222 Rice Seeds\' has been reactivated and is now live.','delivered','2026-10-12 13:48:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'AgriMart Administrator','admin@gmail.com','09171234567',NULL,NULL,NULL,'pending','$2y$10$G2txDeLF3w/fs3XlBj5s5O2FhtVw49udbmyRPno80VKVW0xj3s8ZC','admin','active','2026-08-25 08:40:28','2026-08-25 09:33:35',NULL,1,NULL,'pending'),(2,'Juan dela Cruz','juan@gmail.com','09189876543',NULL,NULL,NULL,'verified','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-10-06 03:55:14',NULL,1,NULL,'verified'),(3,'Pedro Penduko','pedro@gmail.com','09205554321',NULL,NULL,NULL,'verified','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-10-07 02:39:39',NULL,1,NULL,'verified'),(4,'Maria Santos','maria@gmail.com','09998887766',NULL,NULL,NULL,'pending','$2y$10$hSwCF7EAWlsEe6IrHSjBA.5fYMXQDo7Lnqx8yCjSMhcVv46.E51XS','user','active','2026-08-25 08:40:28','2026-08-29 04:30:28',NULL,1,NULL,'pending'),(5,'Christian Lucero','christianlucero522@gmail.com','09999704103','PhilSys National ID','1325256','uploads/identifications/id_5_1791777267.jpg','verified','$2y$10$m9jLWBXq1xKlijE.p.wSUe96FuT6BJDFCAKRbH1/QOFBR6WJgi.E6','user','active','2026-08-25 08:40:28','2026-10-12 03:54:54',NULL,1,'uploads/faces/face_5_1791777267.jpg','verified'),(6,'asleigh balthazar','balt@gmail.com','09098362456',NULL,NULL,NULL,'verified','$2y$10$Bmbhi56Dxz3vd42AQbYhf.C4pRl.CLCcnHw5RBywSCq9eOaJVvilW','user','banned','2026-08-29 04:35:37','2026-10-08 14:47:30',NULL,1,NULL,'pending'),(7,'Allen Mallari','allenmallari2000@gmail.com','09999704103','Philippine National ID','123456789','uploads/identifications/id_1788359158_3094.png','rejected','$2y$10$sTyrcp4.Sihnb5WUud3XiehLNo7cuUpHARneCiBsoORx97vHGF4pS','user','active','2026-09-02 14:25:58','2026-10-06 03:55:48',NULL,1,NULL,'rejected'),(8,'Jemaica R. Bancud','bancudjamaica@gmail.com','09605358372','PhilSys National ID','1325256','uploads/identifications/id_8_1791785938.jpg','pending','$2y$10$CN1G.PLu5bVfrMU4/B18geonAyX/jbZvXciClB6n6iOjg78rgQm9i','user','active','2026-09-29 08:52:11','2026-10-12 06:18:58',NULL,1,'uploads/faces/face_8_1791785938.jpg','pending'),(9,'Christian lucero','christianlucero538@gmail.com','09058642483','Philippine National ID','1325256','uploads/identifications/id_1791006597_1151.jpg','pending','$2y$10$7lq0hJ/aeEFYohu9d9Y7buMIoXU8vHv9ZXxPi3ymdYc/QK0BSLoce','user','active','2026-10-03 05:49:57','2026-10-12 11:29:11',NULL,1,'uploads/faces/face_1791006597_6501.jpg','pending'),(10,'Christian lucero','christianlucero2212@gmail.com','09058642483',NULL,NULL,NULL,'pending','$2y$10$a2LmVIzRsukd5Trae8qYXuPdd1pbTAQomDnOXNUldQHpug45JH4Yy','user','banned','2026-10-05 06:08:58','2026-10-11 10:59:20',NULL,1,NULL,''),(12,'jheradel Urganay','allenkhurtmallari22@gmail.com','09058642483',NULL,NULL,NULL,'pending','$2y$10$kPe7oVG8LevLwGuLyzVehOoQmRCHwswxJCNqzQSsd3tDemvBya3.u','user','active','2026-10-07 01:14:29','2026-10-07 01:14:29','dea33d377afadeaa2d1553e3d57d12c8b23039753e727cf9',0,NULL,'pending'),(13,'Rhod herald pagaran','rhod123@gmail.com','09999704103',NULL,NULL,NULL,'pending','$2y$10$yU2fX7bV9jvUUd/KWT5GguDzFYLI15AckoiI7sVdCfYgXLDQWDvoq','user','active','2026-10-08 09:49:40','2026-10-08 09:49:40','479ffdd4bce663d3e1f517ab09ee00999bd9eed0f92a5498',0,NULL,'pending'),(16,'mark anthony tenepere','teneperemarkanthony@gmail.com','09656683298',NULL,NULL,NULL,'pending','$2y$10$aowNtRrTXaeWELEr/O.kr..HDO3RjCyP4kmOUycn78a.V1n68uWtO','user','active','2026-10-09 02:22:10','2026-10-09 02:24:57','b016e37b631ee5bbb779163d29bdfd52d6c07f5982632596',0,NULL,'pending'),(19,'HACKER','admin1@gmail.com','09999704103',NULL,NULL,NULL,'verified','$2y$10$/mz7iYCvQNWLTXyGqdLBKeDAiRBYSUThG.lElISx627GKh76Rg6ai','admin','active','2026-10-11 10:41:17','2026-10-12 03:26:30',NULL,1,NULL,'verified'),(20,'Christian lucero','wqj@gmail.com','09999704103',NULL,NULL,NULL,'pending','$2y$10$mHwtouqnS3Go6opCW0X5cuSiJBwLwoouphkPVS3qhMoGceREI3pfK','user','banned','2026-10-12 05:44:21','2026-10-12 06:26:35','57c2d7c0e03a7161a043cd59ddbeba20584653f308115c4a',0,NULL,'pending');
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

-- Dump completed on 2026-10-12 20:29:59
