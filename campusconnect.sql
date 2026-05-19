-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 04:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `campusconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$ocQQzzbnunRNiETEHRGVqOQ/vAoEZ/qKVOhsT2k9vadT/YTox.Av6', '2026-05-15 03:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `student_id` varchar(8) NOT NULL,
  `org_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Interview') DEFAULT 'Pending',
  `interview_date` date DEFAULT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_location` varchar(255) DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachment` varchar(255) DEFAULT NULL,
  `interview_status` varchar(50) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `interview_link` text DEFAULT NULL,
  `notification` text DEFAULT NULL,
  `response` varchar(20) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `org_id` int(11) NOT NULL,
  `org_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `course` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_group` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`org_id`, `org_name`, `description`, `course`, `created_at`, `course_group`, `image`, `info`) VALUES
(52, 'University Student Council', 'University-wide organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/USC.jpg', 'University Student Council stands as the highest student governing body of the Pamantasan.'),
(53, 'PLMun Center for Sports and Athletics Development (PLMun Marshalls)', 'Sports organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/sports and athletics.jpg', NULL),
(54, 'PLMun Euphoria', 'Performing arts organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/euphoria.jpg', NULL),
(55, 'PLMun Dulangsining', 'Cultural arts organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/dulangsining.jpg', NULL),
(56, 'PLMun Folkloric Dance Company', 'Dance organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/hiraya.jpg', NULL),
(57, 'PLMun Chorale', 'Singing group', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/chorale.jpg', NULL),
(58, 'PLMun Celestial Esports', 'Esports organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/esports.jpg', NULL),
(59, 'PLMun Emergency Response Team', 'Emergency volunteers', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/ert.jpg', NULL),
(62, 'PLMun Summit Team', 'Leadership organization', '', '2026-05-17 10:07:25', 'GENERAL', 'img/Orgs/GENERAL/summit.jpg', NULL),
(64, 'The Outlook', 'Campus publication', '', '2026-05-17 10:07:25', 'MEDIA', 'img/Orgs/GENERAL/outlook.jpg', NULL),
(65, 'Radyo Pilimon', 'Campus radio', '', '2026-05-17 10:07:25', 'MEDIA', 'img/Orgs/GENERAL/radyo.jpg', NULL),
(66, 'The Warden Publication', 'Publication organization', '', '2026-05-17 10:07:25', 'MEDIA', 'img/Orgs/GENERAL/warden.jpg', NULL),
(67, 'College of Arts and Sciences Student Council', 'CAS Student Council', '', '2026-05-17 10:07:25', 'CAS', 'img/Orgs/CAS/CAS.png', NULL),
(68, 'Psychology Society', 'Psychology organization', '', '2026-05-17 10:07:25', 'CAS', 'img/Orgs/CAS/PSYCHSOC.jpg', NULL),
(69, 'Mass Media Students Society', 'Media students org', '', '2026-05-17 10:07:25', 'CAS', 'img/Orgs/CAS/massmed.jpg', NULL),
(70, 'Young English Students’ Society', 'English org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(71, 'Mathematics Wizards’ Society', 'Math org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(72, 'Science Enthusiasts Society', 'Science org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(74, 'Kaalamang Tanglaw ng Galing sa Filipino', 'Filipino org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(75, 'Political Science Society', 'Political science org', '', '2026-05-17 10:07:25', 'CAS', 'img/Orgs/CAS/polsci.jpg', NULL),
(76, 'College of Business Administration Student Council', 'CBA Student Council', '', '2026-05-17 10:07:25', 'CBA', 'img/Orgs/CBA/CBA.png', NULL),
(77, 'Junior Philippine Institute of Accountants – PLMun', 'Accounting org', '', '2026-05-17 10:07:25', 'CBA', 'img/Orgs/CBA/jpia.png', NULL),
(78, 'Junior Marketing Association – PLMun', 'Marketing org', '', '2026-05-17 10:07:25', 'CBA', 'img/Orgs/CBA/jma.jpg', NULL),
(79, 'Junior People Management Association of the Philippines – PLMun', 'HR org', '', '2026-05-17 10:07:25', 'CBA', 'img/Orgs/CBA/jpma.jpg', NULL),
(80, 'Junior Philippine Society for Talent Development – PLMun Chapter', 'Talent development org', '', '2026-05-17 10:07:25', 'CBA', 'img/Orgs/CBA/jpstd.jpg', NULL),
(81, 'College of Criminal Justice Student Council', 'CCJ Student Council', '', '2026-05-17 10:07:25', 'CCJ', 'img/Orgs/CCJ/CCJ.png', NULL),
(82, 'College of Information Technology and Computer Studies Student Council', 'CITCS Student Council', '', '2026-05-17 10:07:25', 'CITCS', 'img/Orgs/IT/CITCS.png', NULL),
(84, 'BS Computer Science Society', 'CS organization', '', '2026-05-17 10:07:25', 'CITCS', 'img/Orgs/IT/cs.jpg', NULL),
(85, 'Associate in Computer Technology Society', 'ACT organization', '', '2026-05-17 10:07:25', 'CITCS', 'img/Orgs/IT/act.jpg', NULL),
(86, 'CITCS Cross-Devs Society', 'Developers organization', '', '2026-05-17 10:07:25', 'CITCS', 'img/Orgs/IT/crossdev.jpg', NULL),
(87, 'College of Teacher Education Student Council', 'CTE Student Council', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(88, 'Society of Physical Educators', 'PE organization', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(89, 'Bachelor of Elementary Education Society', 'BEED org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(90, 'Early Childhood Educators’ Society', 'ECE org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(91, 'Advent of Special Education Student Society', 'SPED org', '', '2026-05-17 10:07:25', 'CTE', NULL, NULL),
(92, 'Institute of Public Policy and Governance Student Government', 'Public policy org', '', '2026-05-17 10:07:25', 'INSTITUTE', NULL, NULL),
(93, 'Institute of Social Work Student Council', 'Social work org', '', '2026-05-17 10:07:25', 'INSTITUTE', NULL, NULL),
(94, 'Institute of Integrated Development Education – Freshmen Organization', 'Freshmen org', '', '2026-05-17 10:07:25', 'INSTITUTE', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `tag` varchar(50) DEFAULT 'latest',
  `image` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `title`, `content`, `tag`, `image`, `created_at`) VALUES
(3, 'Unstoppable Drive', 'Focus on the goal, heart in the game. 🏀 Whether it’s a fast break or a defensive stand, we leave it all on the court.\r\n\r\nProudly representing the green and gold. Let’s keep the energy high! 🙌🔥', 'latest', '1778843081_org4.jpg', '2026-05-15 11:04:41'),
(4, 'CITCS Community', 'Where collaboration meets computation. Great to see our CITCS community coming together to share knowledge and tackle new challenges at today’s meetup! #CITCS #CodingClub #TechLeaders', 'latest', '1779029186_org1.png', '2026-05-17 14:46:26');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(8) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `course` varchar(20) NOT NULL,
  `section` varchar(10) NOT NULL,
  `age` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`org_id`),
  ADD UNIQUE KEY `org_name` (`org_name`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `org_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
