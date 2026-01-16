-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 04:34 AM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ppktdid`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_theme`
--

CREATE TABLE `active_theme` (
  `id` int(11) NOT NULL,
  `design_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `active_theme`
--

INSERT INTO `active_theme` (`id`, `design_name`, `created_at`) VALUES
(138, 'design3', '2025-11-25 03:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$BG6YHmkmW9kdrfSXA4w41uPUT8Qjv9gpTJWm8aXzghT920ZdlI9fy'),
(2, 'admin', '$2y$10$AhGHDDp7kNVRT4WifALSWOlDF3eaXzkLbFyVRGniKrnlWlym2UUIa'),
(3, 'admin11', '$2y$10$CbRpsxRvXKq2n6WgMWgYVOar8xDin6S47FrIfgAsM3B5caiOaL9Qy');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `text`, `created_at`) VALUES
(4, 'HATI HATI SEMUA ADA BANJIR JUGAK NIH HATI HATI GAISSS', '2025-11-25 03:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_hidden` tinyint(1) DEFAULT '0',
  `orientation` varchar(20) DEFAULT 'landscape'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `image_path`, `uploaded_at`, `is_hidden`, `orientation`) VALUES
(6, '1761700680_Screenshot 2025-09-29 144317.png', '2025-10-29 09:18:00', 0, 'landscape'),
(7, '1762485265_digital-solutions-agency-a4-poster-design-template-41c957e66355b7a13f1fb328ab902b25_screen.jpg', '2025-11-07 11:14:25', 0, 'portrait'),
(8, '1762485269_corporate-banner-design-template-0f426df169bd22e803e70c01b7801d76_screen.jpg', '2025-11-07 11:14:29', 0, 'portrait'),
(12, '1764040578_Orange_Black_and_White_Illustrated_Needs_Vs_Wants_Spending_Guide_Poster.png', '2025-11-25 11:16:18', 0, 'portrait');

-- --------------------------------------------------------

--
-- Table structure for table `duties`
--

CREATE TABLE `duties` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `duty_date_start` date NOT NULL,
  `duty_date_end` date NOT NULL,
  `duty_time_start` time NOT NULL,
  `duty_time_end` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `image_path`) VALUES
(14, 'SHARIFAH SHAKILA BINTI MD SAAD', '1763349363_staff1.jpeg'),
(15, 'JAMALIAH BINTI SHAFIE', '1763349401_staff2.jpeg'),
(16, 'SHARIZA BINTI SHAARI', '1763349460_staff3.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `footer_text`
--

CREATE TABLE `footer_text` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `footer_text`
--

INSERT INTO `footer_text` (`id`, `message`, `updated_at`) VALUES
(11, 'SELAMAT DATANG KE PTD SELAMAT MENJAMU SELERA HUBUNGI KAMI DI TALIAN 0484984', '2025-11-17 12:46:34');

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `youtube_url` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `videos`
--

INSERT INTO `videos` (`id`, `youtube_url`, `uploaded_at`) VALUES
(11, 'https://www.youtube.com/embed/KoWTfciXDz0', '2025-11-17 13:03:08'),
(12, 'https://www.youtube.com/embed/4M8uCxbYgXg', '2025-11-17 13:03:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_theme`
--
ALTER TABLE `active_theme`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `duties`
--
ALTER TABLE `duties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_duty_employee` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `footer_text`
--
ALTER TABLE `footer_text`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_theme`
--
ALTER TABLE `active_theme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `duties`
--
ALTER TABLE `duties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `footer_text`
--
ALTER TABLE `footer_text`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `duties`
--
ALTER TABLE `duties`
  ADD CONSTRAINT `fk_duty_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
