CREATE DATABASE IF NOT EXISTS `dormitory_db`;
USE `dormitory_db`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- --------------------------------------------------------
-- Table structure for table `admins`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admins` VALUES (1,'admin','$2y$10$kckd8mWBtWDIcKqh7s37e.vvEoOG7firWTLa/ybsrWwQR0eOBymja','admin@yasmin-aliarose.com');

-- --------------------------------------------------------
-- Table structure for table `rooms`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_no` int(11) NOT NULL,
  `room_no` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('Available','Full') DEFAULT 'Available',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rooms` VALUES 
(1,2,'1',4,'Available'),(2,2,'2',4,'Available'),(3,2,'3',4,'Available'),(4,2,'4',4,'Available'),(5,2,'5',5,'Available'),(6,2,'6',4,'Available'),
(7,3,'1',4,'Available'),(8,3,'2',4,'Available'),(9,3,'3',4,'Available'),(10,3,'4',4,'Available'),(11,3,'5',4,'Available'),(12,3,'6',4,'Available'),
(13,4,'1',2,'Available'),(14,4,'2',2,'Available'),(15,4,'3',2,'Available'),(16,4,'4',2,'Available'),(17,4,'5',2,'Available'),(18,4,'6',3,'Available');

-- --------------------------------------------------------
-- Table structure for table `beds`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `beds`;
CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `bed_no` int(11) NOT NULL,
  `status` enum('Available','Reserved','Occupied') DEFAULT 'Available',
  `reserved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_beds_room` (`room_id`),
  CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_beds_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `beds` VALUES 
(1,6,2,1,'Available',NULL),(2,6,3,2,'Available',NULL),(3,1,2,3,'Available',NULL),(4,1,2,4,'Available',NULL),
(7,1,2,3,'Available',NULL),(8,1,2,4,'Available',NULL),(9,2,2,1,'Available',NULL),(10,2,2,2,'Available',NULL),
(11,2,2,3,'Available',NULL),(12,2,2,4,'Available',NULL),(13,3,2,1,'Available',NULL),(14,3,2,2,'Available',NULL),
(15,3,2,3,'Available',NULL),(16,3,2,4,'Available',NULL),(18,7,3,1,'Available',NULL),(19,7,3,2,'Available',NULL),
(20,4,2,1,'Available',NULL),(21,7,3,3,'Available',NULL),(23,4,2,2,'Available',NULL),(24,4,2,3,'Available',NULL),
(25,4,2,4,'Available',NULL),(26,5,2,1,'Available',NULL),(27,5,2,2,'Available',NULL),(28,5,2,3,'Available',NULL),
(29,5,2,4,'Available',NULL),(30,5,2,5,'Available',NULL),(31,6,2,3,'Available',NULL),(32,6,2,4,'Available',NULL),
(38,7,3,4,'Available',NULL),(39,8,3,1,'Available',NULL),(40,8,3,2,'Available',NULL),(41,8,3,3,'Available',NULL),
(42,8,3,4,'Available',NULL),(43,9,3,1,'Available',NULL),(44,9,3,2,'Available',NULL),(45,9,3,3,'Available',NULL),
(46,9,3,4,'Available',NULL),(47,10,3,1,'Available',NULL),(48,10,3,2,'Available',NULL),(49,10,3,3,'Available',NULL),
(50,10,3,4,'Available',NULL),(51,11,3,1,'Available',NULL),(52,11,3,2,'Available',NULL),(53,11,3,3,'Available',NULL),
(54,11,3,4,'Available',NULL),(55,12,3,1,'Available',NULL),(56,12,3,2,'Available',NULL),(57,12,3,3,'Available',NULL),
(58,12,3,4,'Available',NULL),(59,13,4,1,'Available',NULL),(60,13,4,2,'Available',NULL),(63,14,4,1,'Available',NULL),
(64,14,4,2,'Available',NULL),(65,15,4,1,'Available',NULL),(66,15,4,2,'Available',NULL),(67,16,4,1,'Available',NULL),
(68,16,4,2,'Available',NULL),(69,17,4,1,'Available',NULL),(70,17,4,2,'Available',NULL),(71,18,4,1,'Available',NULL),
(72,18,4,2,'Available',NULL),(73,18,4,3,'Available',NULL);

-- --------------------------------------------------------
-- Table structure for table `bookings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_ref` varchar(20) DEFAULT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `category` enum('Reviewer','College','High School') DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `payment_method` enum('GCash Online','Cash In') DEFAULT NULL,
  `payment_status` enum('Pending','Confirmed') DEFAULT 'Pending',
  `booking_status` enum('Active','Completed','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `move_in_date` date DEFAULT NULL,
  `move_out_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `monthly_rent` decimal(10,2) DEFAULT 1500.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `reserve_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `receipt_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_ref` (`booking_ref`),
  KEY `bookings_ibfk_1` (`bed_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `payments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  `receipt_path` varchar(255) DEFAULT NULL,
  `payment_method` enum('GCash','Cash','Bank') DEFAULT 'GCash',
  `status` enum('Pending','Confirmed') DEFAULT 'Confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` VALUES (1,'price_per_bed','1500'),(2,'gcash_number','0912-345-6789');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
