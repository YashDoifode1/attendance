-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2025 at 11:08 AM
-- Server version: 10.4.21-MariaDB
-- PHP Version: 8.0.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL DEFAULT 'Absent',
  `total_classes` int(11) DEFAULT 0,
  `attended_classes` int(11) DEFAULT 0,
  `faculty_id` int(11) NOT NULL,
  `subjects_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `schedule_id`, `date`, `status`, `total_classes`, `attended_classes`, `faculty_id`, `subjects_id`, `course_id`, `year_id`, `session_id`) VALUES
(63, 72, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(64, 73, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(65, 74, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(66, 75, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(67, 76, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(68, 77, 292, '2025-02-03', 'Absent', 0, 0, 6, 39, 1, 7, 1),
(69, 78, 292, '2025-02-03', 'Absent', 0, 0, 6, 39, 1, 7, 1),
(70, 79, 292, '2025-02-03', 'Absent', 0, 0, 6, 39, 1, 7, 1),
(71, 80, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1),
(72, 81, 292, '2025-02-03', 'Present', 0, 0, 6, 39, 1, 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `created_at`) VALUES
(1, 'BCA', '2025-02-15 11:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`id`, `course_id`, `year_id`, `session_id`, `subject_id`, `faculty_id`, `day`, `start_time`, `end_time`) VALUES
(110, 1, 1, 1, 1, 4, 'Monday', '10:00:00', '11:00:00'),
(111, 1, 1, 1, 2, 5, 'Monday', '11:00:00', '12:00:00'),
(112, 1, 1, 1, 3, 6, 'Monday', '12:00:00', '01:00:00'),
(113, 1, 1, 1, 4, 9, 'Monday', '02:00:00', '03:00:00'),
(114, 1, 1, 1, 5, 10, 'Monday', '03:00:00', '04:00:00'),
(115, 1, 1, 1, 6, 11, 'Tuesday', '10:00:00', '11:00:00'),
(116, 1, 1, 1, 1, 4, 'Tuesday', '11:00:00', '12:00:00'),
(117, 1, 1, 1, 2, 5, 'Tuesday', '12:00:00', '01:00:00'),
(118, 1, 1, 1, 3, 6, 'Tuesday', '02:00:00', '03:00:00'),
(119, 1, 1, 1, 4, 9, 'Tuesday', '03:00:00', '04:00:00'),
(120, 1, 1, 1, 5, 10, 'Wednesday', '10:00:00', '11:00:00'),
(121, 1, 1, 1, 6, 11, 'Wednesday', '11:00:00', '12:00:00'),
(122, 1, 1, 1, 1, 4, 'Wednesday', '12:00:00', '01:00:00'),
(123, 1, 1, 1, 2, 5, 'Wednesday', '02:00:00', '03:00:00'),
(124, 1, 1, 1, 3, 6, 'Wednesday', '03:00:00', '04:00:00'),
(125, 1, 1, 1, 4, 9, 'Thursday', '10:00:00', '11:00:00'),
(126, 1, 1, 1, 5, 10, 'Thursday', '11:00:00', '12:00:00'),
(127, 1, 1, 1, 6, 11, 'Thursday', '12:00:00', '01:00:00'),
(128, 1, 1, 1, 1, 4, 'Thursday', '02:00:00', '03:00:00'),
(129, 1, 1, 1, 2, 5, 'Thursday', '03:00:00', '04:00:00'),
(130, 1, 1, 1, 3, 6, 'Friday', '10:00:00', '11:00:00'),
(131, 1, 1, 1, 4, 9, 'Friday', '11:00:00', '12:00:00'),
(132, 1, 1, 1, 5, 10, 'Friday', '12:00:00', '01:00:00'),
(133, 1, 1, 1, 6, 11, 'Friday', '02:00:00', '03:00:00'),
(134, 1, 1, 1, 1, 4, 'Friday', '03:00:00', '04:00:00'),
(135, 1, 1, 1, 2, 5, 'Saturday', '10:00:00', '11:00:00'),
(136, 1, 1, 1, 3, 6, 'Saturday', '11:00:00', '12:00:00'),
(137, 1, 1, 1, 4, 9, 'Saturday', '12:00:00', '01:00:00'),
(138, 1, 1, 1, 5, 10, 'Saturday', '02:00:00', '03:00:00'),
(139, 1, 1, 1, 6, 11, 'Saturday', '03:00:00', '04:00:00'),
(140, 1, 2, 1, 7, 4, 'Monday', '10:00:00', '11:00:00'),
(141, 1, 2, 1, 8, 5, 'Monday', '11:00:00', '12:00:00'),
(142, 1, 2, 1, 9, 6, 'Monday', '12:00:00', '01:00:00'),
(143, 1, 2, 1, 10, 9, 'Monday', '02:00:00', '03:00:00'),
(144, 1, 2, 1, 11, 10, 'Monday', '03:00:00', '04:00:00'),
(145, 1, 2, 1, 12, 11, 'Tuesday', '10:00:00', '11:00:00'),
(146, 1, 2, 1, 7, 4, 'Tuesday', '11:00:00', '12:00:00'),
(147, 1, 2, 1, 8, 5, 'Tuesday', '12:00:00', '01:00:00'),
(148, 1, 2, 1, 9, 6, 'Tuesday', '02:00:00', '03:00:00'),
(149, 1, 2, 1, 10, 9, 'Tuesday', '03:00:00', '04:00:00'),
(150, 1, 2, 1, 11, 10, 'Wednesday', '10:00:00', '11:00:00'),
(151, 1, 2, 1, 12, 11, 'Wednesday', '11:00:00', '12:00:00'),
(152, 1, 2, 1, 7, 4, 'Wednesday', '12:00:00', '01:00:00'),
(153, 1, 2, 1, 8, 5, 'Wednesday', '02:00:00', '03:00:00'),
(154, 1, 2, 1, 9, 6, 'Wednesday', '03:00:00', '04:00:00'),
(155, 1, 2, 1, 10, 9, 'Thursday', '10:00:00', '11:00:00'),
(156, 1, 2, 1, 11, 10, 'Thursday', '11:00:00', '12:00:00'),
(157, 1, 2, 1, 12, 11, 'Thursday', '12:00:00', '01:00:00'),
(158, 1, 2, 1, 7, 4, 'Thursday', '02:00:00', '03:00:00'),
(159, 1, 2, 1, 8, 5, 'Thursday', '03:00:00', '04:00:00'),
(160, 1, 2, 1, 9, 6, 'Friday', '10:00:00', '11:00:00'),
(161, 1, 2, 1, 10, 9, 'Friday', '11:00:00', '12:00:00'),
(162, 1, 2, 1, 11, 10, 'Friday', '12:00:00', '01:00:00'),
(163, 1, 2, 1, 12, 11, 'Friday', '02:00:00', '03:00:00'),
(164, 1, 2, 1, 7, 4, 'Friday', '03:00:00', '04:00:00'),
(165, 1, 2, 1, 8, 5, 'Saturday', '10:00:00', '11:00:00'),
(166, 1, 2, 1, 9, 6, 'Saturday', '11:00:00', '12:00:00'),
(167, 1, 2, 1, 10, 9, 'Saturday', '12:00:00', '01:00:00'),
(168, 1, 2, 1, 11, 10, 'Saturday', '02:00:00', '03:00:00'),
(169, 1, 2, 1, 12, 11, 'Saturday', '03:00:00', '04:00:00'),
(170, 1, 3, 1, 13, 4, 'Monday', '10:00:00', '11:00:00'),
(171, 1, 3, 1, 14, 5, 'Monday', '11:00:00', '12:00:00'),
(172, 1, 3, 1, 15, 6, 'Monday', '12:00:00', '01:00:00'),
(173, 1, 3, 1, 16, 9, 'Monday', '02:00:00', '03:00:00'),
(174, 1, 3, 1, 17, 10, 'Monday', '03:00:00', '04:00:00'),
(175, 1, 3, 1, 18, 11, 'Tuesday', '10:00:00', '11:00:00'),
(176, 1, 3, 1, 13, 4, 'Tuesday', '11:00:00', '12:00:00'),
(177, 1, 3, 1, 14, 5, 'Tuesday', '12:00:00', '01:00:00'),
(178, 1, 3, 1, 15, 6, 'Tuesday', '02:00:00', '03:00:00'),
(179, 1, 3, 1, 16, 9, 'Tuesday', '03:00:00', '04:00:00'),
(180, 1, 3, 1, 17, 10, 'Wednesday', '10:00:00', '11:00:00'),
(181, 1, 3, 1, 18, 11, 'Wednesday', '11:00:00', '12:00:00'),
(182, 1, 3, 1, 13, 4, 'Wednesday', '12:00:00', '01:00:00'),
(183, 1, 3, 1, 14, 5, 'Wednesday', '02:00:00', '03:00:00'),
(184, 1, 3, 1, 15, 6, 'Wednesday', '03:00:00', '04:00:00'),
(185, 1, 3, 1, 16, 9, 'Thursday', '10:00:00', '11:00:00'),
(186, 1, 3, 1, 17, 10, 'Thursday', '11:00:00', '12:00:00'),
(187, 1, 3, 1, 18, 11, 'Thursday', '12:00:00', '01:00:00'),
(188, 1, 3, 1, 13, 4, 'Thursday', '02:00:00', '03:00:00'),
(189, 1, 3, 1, 14, 5, 'Thursday', '03:00:00', '04:00:00'),
(190, 1, 3, 1, 15, 6, 'Friday', '10:00:00', '11:00:00'),
(191, 1, 3, 1, 16, 9, 'Friday', '11:00:00', '12:00:00'),
(192, 1, 3, 1, 17, 10, 'Friday', '12:00:00', '01:00:00'),
(193, 1, 3, 1, 18, 11, 'Friday', '02:00:00', '03:00:00'),
(194, 1, 3, 1, 13, 4, 'Friday', '03:00:00', '04:00:00'),
(195, 1, 3, 1, 14, 5, 'Saturday', '10:00:00', '11:00:00'),
(196, 1, 3, 1, 15, 6, 'Saturday', '11:00:00', '12:00:00'),
(197, 1, 3, 1, 16, 9, 'Saturday', '12:00:00', '01:00:00'),
(198, 1, 3, 1, 17, 10, 'Saturday', '02:00:00', '03:00:00'),
(199, 1, 3, 1, 18, 11, 'Saturday', '03:00:00', '04:00:00'),
(200, 1, 4, 1, 19, 4, 'Monday', '10:00:00', '11:00:00'),
(201, 1, 4, 1, 20, 5, 'Monday', '11:00:00', '12:00:00'),
(202, 1, 4, 1, 21, 6, 'Monday', '12:00:00', '01:00:00'),
(203, 1, 4, 1, 22, 9, 'Monday', '02:00:00', '03:00:00'),
(204, 1, 4, 1, 23, 10, 'Monday', '03:00:00', '04:00:00'),
(205, 1, 4, 1, 24, 11, 'Tuesday', '10:00:00', '11:00:00'),
(206, 1, 4, 1, 19, 4, 'Tuesday', '11:00:00', '12:00:00'),
(207, 1, 4, 1, 20, 5, 'Tuesday', '12:00:00', '01:00:00'),
(208, 1, 4, 1, 21, 6, 'Tuesday', '02:00:00', '03:00:00'),
(209, 1, 4, 1, 22, 9, 'Tuesday', '03:00:00', '04:00:00'),
(210, 1, 4, 1, 23, 10, 'Wednesday', '10:00:00', '11:00:00'),
(211, 1, 4, 1, 24, 11, 'Wednesday', '11:00:00', '12:00:00'),
(212, 1, 4, 1, 19, 4, 'Wednesday', '12:00:00', '01:00:00'),
(213, 1, 4, 1, 20, 5, 'Wednesday', '02:00:00', '03:00:00'),
(214, 1, 4, 1, 21, 6, 'Wednesday', '03:00:00', '04:00:00'),
(215, 1, 4, 1, 22, 9, 'Thursday', '10:00:00', '11:00:00'),
(216, 1, 4, 1, 23, 10, 'Thursday', '11:00:00', '12:00:00'),
(217, 1, 4, 1, 24, 11, 'Thursday', '12:00:00', '01:00:00'),
(218, 1, 4, 1, 19, 4, 'Thursday', '02:00:00', '03:00:00'),
(219, 1, 4, 1, 20, 5, 'Thursday', '03:00:00', '04:00:00'),
(220, 1, 4, 1, 21, 6, 'Friday', '10:00:00', '11:00:00'),
(221, 1, 4, 1, 22, 9, 'Friday', '11:00:00', '12:00:00'),
(222, 1, 4, 1, 23, 10, 'Friday', '12:00:00', '01:00:00'),
(223, 1, 4, 1, 24, 11, 'Friday', '02:00:00', '03:00:00'),
(224, 1, 4, 1, 19, 4, 'Friday', '03:00:00', '04:00:00'),
(225, 1, 4, 1, 20, 5, 'Saturday', '10:00:00', '11:00:00'),
(226, 1, 4, 1, 21, 6, 'Saturday', '11:00:00', '12:00:00'),
(227, 1, 4, 1, 22, 9, 'Saturday', '12:00:00', '01:00:00'),
(228, 1, 4, 1, 23, 10, 'Saturday', '02:00:00', '03:00:00'),
(229, 1, 4, 1, 24, 11, 'Saturday', '03:00:00', '04:00:00'),
(230, 1, 5, 1, 25, 4, 'Monday', '10:00:00', '11:00:00'),
(231, 1, 5, 1, 26, 5, 'Monday', '11:00:00', '12:00:00'),
(232, 1, 5, 1, 27, 6, 'Monday', '12:00:00', '01:00:00'),
(233, 1, 5, 1, 28, 9, 'Monday', '02:00:00', '03:00:00'),
(234, 1, 5, 1, 29, 10, 'Monday', '03:00:00', '04:00:00'),
(235, 1, 5, 1, 30, 11, 'Tuesday', '10:00:00', '11:00:00'),
(236, 1, 5, 1, 25, 4, 'Tuesday', '11:00:00', '12:00:00'),
(237, 1, 5, 1, 26, 5, 'Tuesday', '12:00:00', '01:00:00'),
(238, 1, 5, 1, 27, 6, 'Tuesday', '02:00:00', '03:00:00'),
(239, 1, 5, 1, 28, 9, 'Tuesday', '03:00:00', '04:00:00'),
(240, 1, 5, 1, 29, 10, 'Wednesday', '10:00:00', '11:00:00'),
(241, 1, 5, 1, 30, 11, 'Wednesday', '11:00:00', '12:00:00'),
(242, 1, 5, 1, 25, 4, 'Wednesday', '12:00:00', '01:00:00'),
(243, 1, 5, 1, 26, 5, 'Wednesday', '02:00:00', '03:00:00'),
(244, 1, 5, 1, 27, 6, 'Wednesday', '03:00:00', '04:00:00'),
(245, 1, 5, 1, 28, 9, 'Thursday', '10:00:00', '11:00:00'),
(246, 1, 5, 1, 29, 10, 'Thursday', '11:00:00', '12:00:00'),
(247, 1, 5, 1, 30, 11, 'Thursday', '12:00:00', '01:00:00'),
(248, 1, 5, 1, 25, 4, 'Thursday', '02:00:00', '03:00:00'),
(249, 1, 5, 1, 26, 5, 'Thursday', '03:00:00', '04:00:00'),
(250, 1, 5, 1, 27, 6, 'Friday', '10:00:00', '11:00:00'),
(251, 1, 5, 1, 28, 9, 'Friday', '11:00:00', '12:00:00'),
(252, 1, 5, 1, 29, 10, 'Friday', '12:00:00', '01:00:00'),
(253, 1, 5, 1, 30, 11, 'Friday', '02:00:00', '03:00:00'),
(254, 1, 5, 1, 25, 4, 'Friday', '03:00:00', '04:00:00'),
(255, 1, 5, 1, 26, 5, 'Saturday', '10:00:00', '11:00:00'),
(256, 1, 5, 1, 27, 6, 'Saturday', '11:00:00', '12:00:00'),
(257, 1, 5, 1, 28, 9, 'Saturday', '12:00:00', '01:00:00'),
(258, 1, 5, 1, 29, 10, 'Saturday', '02:00:00', '03:00:00'),
(259, 1, 5, 1, 30, 11, 'Saturday', '03:00:00', '04:00:00'),
(260, 1, 6, 1, 31, 4, 'Monday', '10:00:00', '11:00:00'),
(261, 1, 6, 1, 32, 5, 'Monday', '11:00:00', '12:00:00'),
(262, 1, 6, 1, 33, 6, 'Monday', '12:00:00', '01:00:00'),
(263, 1, 6, 1, 34, 9, 'Monday', '02:00:00', '03:00:00'),
(264, 1, 6, 1, 35, 10, 'Monday', '03:00:00', '04:00:00'),
(265, 1, 6, 1, 36, 11, 'Tuesday', '10:00:00', '11:00:00'),
(266, 1, 6, 1, 31, 4, 'Tuesday', '11:00:00', '12:00:00'),
(267, 1, 6, 1, 32, 5, 'Tuesday', '12:00:00', '01:00:00'),
(268, 1, 6, 1, 33, 6, 'Tuesday', '02:00:00', '03:00:00'),
(269, 1, 6, 1, 34, 9, 'Tuesday', '03:00:00', '04:00:00'),
(270, 1, 6, 1, 35, 10, 'Wednesday', '10:00:00', '11:00:00'),
(271, 1, 6, 1, 36, 11, 'Wednesday', '11:00:00', '12:00:00'),
(272, 1, 6, 1, 31, 4, 'Wednesday', '12:00:00', '01:00:00'),
(273, 1, 6, 1, 32, 5, 'Wednesday', '02:00:00', '03:00:00'),
(274, 1, 6, 1, 33, 6, 'Wednesday', '03:00:00', '04:00:00'),
(275, 1, 6, 1, 34, 9, 'Thursday', '10:00:00', '11:00:00'),
(276, 1, 6, 1, 35, 10, 'Thursday', '11:00:00', '12:00:00'),
(277, 1, 6, 1, 36, 11, 'Thursday', '12:00:00', '01:00:00'),
(278, 1, 6, 1, 31, 4, 'Thursday', '02:00:00', '03:00:00'),
(279, 1, 6, 1, 32, 5, 'Thursday', '03:00:00', '04:00:00'),
(280, 1, 6, 1, 33, 6, 'Friday', '10:00:00', '11:00:00'),
(281, 1, 6, 1, 34, 9, 'Friday', '11:00:00', '12:00:00'),
(282, 1, 6, 1, 35, 10, 'Friday', '12:00:00', '01:00:00'),
(283, 1, 6, 1, 36, 11, 'Friday', '02:00:00', '03:00:00'),
(284, 1, 6, 1, 31, 4, 'Friday', '03:00:00', '04:00:00'),
(285, 1, 6, 1, 32, 5, 'Saturday', '10:00:00', '11:00:00'),
(286, 1, 6, 1, 33, 6, 'Saturday', '11:00:00', '12:00:00'),
(287, 1, 6, 1, 34, 9, 'Saturday', '12:00:00', '01:00:00'),
(288, 1, 6, 1, 35, 10, 'Saturday', '02:00:00', '03:00:00'),
(289, 1, 6, 1, 36, 11, 'Saturday', '03:00:00', '04:00:00'),
(290, 1, 7, 1, 37, 4, 'Monday', '10:00:00', '11:00:00'),
(291, 1, 7, 1, 38, 5, 'Monday', '11:00:00', '12:00:00'),
(292, 1, 7, 1, 39, 6, 'Monday', '12:00:00', '01:00:00'),
(293, 1, 7, 1, 40, 9, 'Monday', '02:00:00', '03:00:00'),
(294, 1, 7, 1, 41, 10, 'Monday', '03:00:00', '04:00:00'),
(295, 1, 7, 1, 42, 11, 'Tuesday', '10:00:00', '11:00:00'),
(296, 1, 7, 1, 37, 4, 'Tuesday', '11:00:00', '12:00:00'),
(297, 1, 7, 1, 38, 5, 'Tuesday', '12:00:00', '01:00:00'),
(298, 1, 7, 1, 39, 6, 'Tuesday', '02:00:00', '03:00:00'),
(299, 1, 7, 1, 40, 9, 'Tuesday', '03:00:00', '04:00:00'),
(300, 1, 7, 1, 41, 10, 'Wednesday', '10:00:00', '11:00:00'),
(301, 1, 7, 1, 42, 11, 'Wednesday', '11:00:00', '12:00:00'),
(302, 1, 7, 1, 37, 4, 'Wednesday', '12:00:00', '01:00:00'),
(303, 1, 7, 1, 38, 5, 'Wednesday', '02:00:00', '03:00:00'),
(304, 1, 7, 1, 39, 6, 'Wednesday', '03:00:00', '04:00:00'),
(305, 1, 7, 1, 40, 9, 'Thursday', '10:00:00', '11:00:00'),
(306, 1, 7, 1, 41, 10, 'Thursday', '11:00:00', '12:00:00'),
(307, 1, 7, 1, 42, 11, 'Thursday', '12:00:00', '01:00:00'),
(308, 1, 7, 1, 37, 4, 'Thursday', '02:00:00', '03:00:00'),
(309, 1, 7, 1, 38, 5, 'Thursday', '03:00:00', '04:00:00'),
(310, 1, 7, 1, 39, 6, 'Friday', '10:00:00', '11:00:00'),
(311, 1, 7, 1, 40, 9, 'Friday', '11:00:00', '12:00:00'),
(312, 1, 7, 1, 41, 10, 'Friday', '12:00:00', '01:00:00'),
(313, 1, 7, 1, 42, 11, 'Friday', '02:00:00', '03:00:00'),
(314, 1, 7, 1, 37, 4, 'Friday', '03:00:00', '04:00:00'),
(315, 1, 7, 1, 38, 5, 'Saturday', '10:00:00', '11:00:00'),
(316, 1, 7, 1, 39, 6, 'Saturday', '11:00:00', '12:00:00'),
(317, 1, 7, 1, 40, 9, 'Saturday', '12:00:00', '01:00:00'),
(318, 1, 7, 1, 41, 10, 'Saturday', '02:00:00', '03:00:00'),
(319, 1, 7, 1, 42, 11, 'Saturday', '03:00:00', '04:00:00'),
(320, 1, 8, 1, 43, 4, 'Monday', '10:00:00', '11:00:00'),
(321, 1, 8, 1, 44, 5, 'Monday', '11:00:00', '12:00:00'),
(322, 1, 8, 1, 45, 6, 'Monday', '12:00:00', '01:00:00'),
(323, 1, 8, 1, 46, 9, 'Monday', '02:00:00', '03:00:00'),
(324, 1, 8, 1, 47, 10, 'Monday', '03:00:00', '04:00:00'),
(325, 1, 8, 1, 48, 11, 'Tuesday', '10:00:00', '11:00:00'),
(326, 1, 8, 1, 43, 4, 'Tuesday', '11:00:00', '12:00:00'),
(327, 1, 8, 1, 44, 5, 'Tuesday', '12:00:00', '01:00:00'),
(328, 1, 8, 1, 45, 6, 'Tuesday', '02:00:00', '03:00:00'),
(329, 1, 8, 1, 46, 9, 'Tuesday', '03:00:00', '04:00:00'),
(330, 1, 8, 1, 47, 10, 'Wednesday', '10:00:00', '11:00:00'),
(331, 1, 8, 1, 48, 11, 'Wednesday', '11:00:00', '12:00:00'),
(332, 1, 8, 1, 43, 4, 'Wednesday', '12:00:00', '01:00:00'),
(333, 1, 8, 1, 44, 5, 'Wednesday', '02:00:00', '03:00:00'),
(334, 1, 8, 1, 45, 6, 'Wednesday', '03:00:00', '04:00:00'),
(335, 1, 8, 1, 46, 9, 'Thursday', '10:00:00', '11:00:00'),
(336, 1, 8, 1, 47, 10, 'Thursday', '11:00:00', '12:00:00'),
(337, 1, 8, 1, 48, 11, 'Thursday', '12:00:00', '01:00:00'),
(338, 1, 8, 1, 43, 4, 'Thursday', '02:00:00', '03:00:00'),
(339, 1, 8, 1, 44, 5, 'Thursday', '03:00:00', '04:00:00'),
(340, 1, 8, 1, 45, 6, 'Friday', '10:00:00', '11:00:00'),
(341, 1, 8, 1, 46, 9, 'Friday', '11:00:00', '12:00:00'),
(342, 1, 8, 1, 47, 10, 'Friday', '12:00:00', '01:00:00'),
(343, 1, 8, 1, 48, 11, 'Friday', '02:00:00', '03:00:00'),
(344, 1, 8, 1, 43, 4, 'Friday', '03:00:00', '04:00:00'),
(345, 1, 8, 1, 44, 5, 'Saturday', '10:00:00', '11:00:00'),
(346, 1, 8, 1, 45, 6, 'Saturday', '11:00:00', '12:00:00'),
(347, 1, 8, 1, 46, 9, 'Saturday', '12:00:00', '01:00:00'),
(348, 1, 8, 1, 47, 10, 'Saturday', '02:00:00', '03:00:00'),
(349, 1, 8, 1, 48, 11, 'Saturday', '03:00:00', '04:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `session_name`, `created_at`) VALUES
(1, 'BATCH_2022_2025', '2025-02-15 11:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `role` enum('student','faculty','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `email`, `password`, `course_id`, `year_id`, `session_id`, `role`, `created_at`) VALUES
(1, 'Rahul Sharma', 'rahul@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', 1, NULL, 1, 'student', '2025-02-15 11:13:17'),
(2, 'Priya Patel', 'priya@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'student', '2025-02-15 11:13:17'),
(3, 'Amit Verma', 'amit@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'student', '2025-02-15 11:13:17'),
(4, 'Dr. Sunita Mishra', 'sunita@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', 1, NULL, 1, 'faculty', '2025-02-15 11:14:14'),
(5, 'Prof. Rajesh Gupta', 'rajesh@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'faculty', '2025-02-15 11:14:14'),
(6, 'Ms. Neha Kapoor', 'neha@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'faculty', '2025-02-15 11:14:14'),
(7, 'Admin User', 'admin@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'admin', '2025-02-15 11:14:14'),
(8, 'Pranay B', 'example@gmail.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', 1, NULL, 1, 'student', '2025-02-15 11:16:35'),
(9, 'Faculty 7', 'faculty7@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'faculty', '2025-02-16 04:32:13'),
(10, 'Faculty 8', 'faculty8@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'faculty', '2025-02-16 04:32:13'),
(11, 'Faculty 9', 'faculty9@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', NULL, NULL, NULL, 'faculty', '2025-02-16 04:32:13'),
(12, 'Student1_Sem1', 'student1.sem1@example.com', '$2y$10$OqBXrwlblNtHbLGdb8CO4.HWYkkrQVVYMmzmB6enEfPjX.OIbH8r.', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(13, 'Student2_Sem1', 'student2.sem1@example.com', 'password2', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(14, 'Student3_Sem1', 'student3.sem1@example.com', 'password3', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(15, 'Student4_Sem1', 'student4.sem1@example.com', 'password4', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(16, 'Student5_Sem1', 'student5.sem1@example.com', 'password5', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(17, 'Student6_Sem1', 'student6.sem1@example.com', 'password6', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(18, 'Student7_Sem1', 'student7.sem1@example.com', 'password7', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(19, 'Student8_Sem1', 'student8.sem1@example.com', 'password8', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(20, 'Student9_Sem1', 'student9.sem1@example.com', 'password9', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(21, 'Student10_Sem1', 'student10.sem1@example.com', 'password10', 1, 1, 1, 'student', '2025-02-16 08:19:06'),
(22, 'Student1_Sem2', 'student1.sem2@example.com', 'password1', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(23, 'Student2_Sem2', 'student2.sem2@example.com', 'password2', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(24, 'Student3_Sem2', 'student3.sem2@example.com', 'password3', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(25, 'Student4_Sem2', 'student4.sem2@example.com', 'password4', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(26, 'Student5_Sem2', 'student5.sem2@example.com', 'password5', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(27, 'Student6_Sem2', 'student6.sem2@example.com', 'password6', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(28, 'Student7_Sem2', 'student7.sem2@example.com', 'password7', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(29, 'Student8_Sem2', 'student8.sem2@example.com', 'password8', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(30, 'Student9_Sem2', 'student9.sem2@example.com', 'password9', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(31, 'Student10_Sem2', 'student10.sem2@example.com', 'password10', 1, 2, 1, 'student', '2025-02-16 08:19:06'),
(32, 'Student1_Sem3', 'student1.sem3@example.com', 'password1', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(33, 'Student2_Sem3', 'student2.sem3@example.com', 'password2', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(34, 'Student3_Sem3', 'student3.sem3@example.com', 'password3', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(35, 'Student4_Sem3', 'student4.sem3@example.com', 'password4', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(36, 'Student5_Sem3', 'student5.sem3@example.com', 'password5', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(37, 'Student6_Sem3', 'student6.sem3@example.com', 'password6', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(38, 'Student7_Sem3', 'student7.sem3@example.com', 'password7', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(39, 'Student8_Sem3', 'student8.sem3@example.com', 'password8', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(40, 'Student9_Sem3', 'student9.sem3@example.com', 'password9', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(41, 'Student10_Sem3', 'student10.sem3@example.com', 'password10', 1, 3, 1, 'student', '2025-02-16 08:19:06'),
(42, 'Student1_Sem4', 'student1.sem4@example.com', 'password1', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(43, 'Student2_Sem4', 'student2.sem4@example.com', 'password2', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(44, 'Student3_Sem4', 'student3.sem4@example.com', 'password3', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(45, 'Student4_Sem4', 'student4.sem4@example.com', 'password4', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(46, 'Student5_Sem4', 'student5.sem4@example.com', 'password5', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(47, 'Student6_Sem4', 'student6.sem4@example.com', 'password6', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(48, 'Student7_Sem4', 'student7.sem4@example.com', 'password7', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(49, 'Student8_Sem4', 'student8.sem4@example.com', 'password8', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(50, 'Student9_Sem4', 'student9.sem4@example.com', 'password9', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(51, 'Student10_Sem4', 'student10.sem4@example.com', 'password10', 1, 4, 1, 'student', '2025-02-16 08:19:06'),
(52, 'Student1_Sem5', 'student1.sem5@example.com', 'password1', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(53, 'Student2_Sem5', 'student2.sem5@example.com', 'password2', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(54, 'Student3_Sem5', 'student3.sem5@example.com', 'password3', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(55, 'Student4_Sem5', 'student4.sem5@example.com', 'password4', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(56, 'Student5_Sem5', 'student5.sem5@example.com', 'password5', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(57, 'Student6_Sem5', 'student6.sem5@example.com', 'password6', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(58, 'Student7_Sem5', 'student7.sem5@example.com', 'password7', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(59, 'Student8_Sem5', 'student8.sem5@example.com', 'password8', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(60, 'Student9_Sem5', 'student9.sem5@example.com', 'password9', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(61, 'Student10_Sem5', 'student10.sem5@example.com', 'password10', 1, 5, 1, 'student', '2025-02-16 08:19:06'),
(62, 'Student1_Sem6', 'student1.sem6@example.com', 'password1', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(63, 'Student2_Sem6', 'student2.sem6@example.com', 'password2', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(64, 'Student3_Sem6', 'student3.sem6@example.com', 'password3', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(65, 'Student4_Sem6', 'student4.sem6@example.com', 'password4', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(66, 'Student5_Sem6', 'student5.sem6@example.com', 'password5', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(67, 'Student6_Sem6', 'student6.sem6@example.com', 'password6', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(68, 'Student7_Sem6', 'student7.sem6@example.com', 'password7', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(69, 'Student8_Sem6', 'student8.sem6@example.com', 'password8', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(70, 'Student9_Sem6', 'student9.sem6@example.com', 'password9', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(71, 'Student10_Sem6', 'student10.sem6@example.com', 'password10', 1, 6, 1, 'student', '2025-02-16 08:19:06'),
(72, 'Student1_Sem7', 'student1.sem7@example.com', 'password1', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(73, 'Student2_Sem7', 'student2.sem7@example.com', 'password2', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(74, 'Student3_Sem7', 'student3.sem7@example.com', 'password3', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(75, 'Student4_Sem7', 'student4.sem7@example.com', 'password4', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(76, 'Student5_Sem7', 'student5.sem7@example.com', 'password5', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(77, 'Student6_Sem7', 'student6.sem7@example.com', 'password6', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(78, 'Student7_Sem7', 'student7.sem7@example.com', 'password7', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(79, 'Student8_Sem7', 'student8.sem7@example.com', 'password8', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(80, 'Student9_Sem7', 'student9.sem7@example.com', 'password9', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(81, 'Student10_Sem7', 'student10.sem7@example.com', 'password10', 1, 7, 1, 'student', '2025-02-16 08:19:06'),
(82, 'Student1_Sem8', 'student1.sem8@example.com', 'password1', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(83, 'Student2_Sem8', 'student2.sem8@example.com', 'password2', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(84, 'Student3_Sem8', 'student3.sem8@example.com', 'password3', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(85, 'Student4_Sem8', 'student4.sem8@example.com', 'password4', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(86, 'Student5_Sem8', 'student5.sem8@example.com', 'password5', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(87, 'Student6_Sem8', 'student6.sem8@example.com', 'password6', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(88, 'Student7_Sem8', 'student7.sem8@example.com', 'password7', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(89, 'Student8_Sem8', 'student8.sem8@example.com', 'password8', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(90, 'Student9_Sem8', 'student9.sem8@example.com', 'password9', 1, 8, 1, 'student', '2025-02-16 08:19:06'),
(91, 'Student10_Sem8', 'student10.sem8@example.com', 'password10', 1, 8, 1, 'student', '2025-02-16 08:19:06');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `course_id`, `year_id`, `session_id`) VALUES
(27, 'Subject 1 - Sem 1', 1, 1, 1),
(28, 'Subject 2 - Sem 1', 1, 1, 1),
(29, 'Subject 3 - Sem 1', 1, 1, 1),
(30, 'Subject 4 - Sem 1', 1, 1, 1),
(31, 'Subject 5 - Sem 1', 1, 1, 1),
(32, 'Subject 6 - Sem 1', 1, 1, 1),
(33, 'Subject 1 - Sem 2', 1, 2, 1),
(34, 'Subject 2 - Sem 2', 1, 2, 1),
(35, 'Subject 3 - Sem 2', 1, 2, 1),
(36, 'Subject 4 - Sem 2', 1, 2, 1),
(37, 'Subject 5 - Sem 2', 1, 2, 1),
(38, 'Subject 6 - Sem 2', 1, 2, 1),
(39, 'Subject 1 - Sem 3', 1, 3, 1),
(40, 'Subject 2 - Sem 3', 1, 3, 1),
(41, 'Subject 3 - Sem 3', 1, 3, 1),
(42, 'Subject 4 - Sem 3', 1, 3, 1),
(43, 'Subject 5 - Sem 3', 1, 3, 1),
(44, 'Subject 6 - Sem 3', 1, 3, 1),
(45, 'Subject 1 - Sem 4', 1, 4, 1),
(46, 'Subject 2 - Sem 4', 1, 4, 1),
(47, 'Subject 3 - Sem 4', 1, 4, 1),
(48, 'Subject 4 - Sem 4', 1, 4, 1),
(49, 'Subject 5 - Sem 4', 1, 4, 1),
(50, 'Subject 6 - Sem 4', 1, 4, 1),
(51, 'Subject 1 - Sem 5', 1, 5, 1),
(52, 'Subject 2 - Sem 5', 1, 5, 1),
(53, 'Subject 3 - Sem 5', 1, 5, 1),
(54, 'Subject 4 - Sem 5', 1, 5, 1),
(55, 'Subject 5 - Sem 5', 1, 5, 1),
(56, 'Subject 6 - Sem 5', 1, 5, 1),
(57, 'Subject 1 - Sem 6', 1, 6, 1),
(58, 'Subject 2 - Sem 6', 1, 6, 1),
(59, 'Subject 3 - Sem 6', 1, 6, 1),
(60, 'Subject 4 - Sem 6', 1, 6, 1),
(61, 'Subject 5 - Sem 6', 1, 6, 1),
(62, 'Subject 6 - Sem 6', 1, 6, 1),
(63, 'Subject 1 - Sem 7', 1, 7, 1),
(64, 'Subject 2 - Sem 7', 1, 7, 1),
(65, 'Subject 3 - Sem 7', 1, 7, 1),
(66, 'Subject 4 - Sem 7', 1, 7, 1),
(67, 'Subject 5 - Sem 7', 1, 7, 1),
(68, 'Subject 6 - Sem 7', 1, 7, 1),
(69, 'Subject 1 - Sem 8', 1, 8, 1),
(70, 'Subject 2 - Sem 8', 1, 8, 1),
(71, 'Subject 3 - Sem 8', 1, 8, 1),
(72, 'Subject 4 - Sem 8', 1, 8, 1),
(73, 'Subject 5 - Sem 8', 1, 8, 1),
(74, 'Subject 6 - Sem 8', 1, 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `years`
--

CREATE TABLE `years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `years`
--

INSERT INTO `years` (`id`, `year_name`, `created_at`) VALUES
(1, 'FIRST YEAR 1 SEM', '2025-02-16 04:51:12'),
(2, 'FIRST YEAR 2 SEM', '2025-02-16 04:52:08'),
(3, 'SECOND YEAR 1 SEM', '2025-02-16 04:52:27'),
(4, 'SECOND YEAR 2 SEM', '2025-02-16 04:52:33'),
(5, 'THIRD YEAR 1 SEM', '2025-02-16 04:52:47'),
(6, 'THIRD YEAR 2 SEM', '2025-02-16 04:52:58'),
(7, 'FOURTH YEAR 1 SEM', '2025-02-16 04:53:11'),
(8, 'FOURTH YEAR 2 SEM', '2025-02-16 04:53:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_schedule` (`schedule_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_year` (`year_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_faculty` (`faculty_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_name` (`session_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `year_id` (`year_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `years`
--
ALTER TABLE `years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=350;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `years`
--
ALTER TABLE `years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
