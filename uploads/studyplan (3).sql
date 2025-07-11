-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 11:00 AM
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
-- Database: `studyplan`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `course_code` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `department_id`, `course_name`, `course_code`) VALUES
(1, 1, 'Bachelor of Science in Information Technology', 'BSIT'),
(2, 2, 'Bachelor of Science in Nursing', 'BSN'),
(3, 2, 'Bachelor of Science in Physical Therapy', 'BSPT');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'CET Department'),
(2, 'CHS Department');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `school_year`, `semester`) VALUES
(1, '2025-2026', '1st'),
(3, '2025-2026', '2nd'),
(4, '2025-2026', 'Summer');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `full_name`, `course_id`) VALUES
(1, '21-14-046', 'Erik Josef M. Pallasigue', 1),
(2, '21-14-014', 'Marvin Angelo Dela Cruz', 1),
(4, '21-14-010', 'Marjello Dela Cruz', 3);

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`id`, `student_id`, `subject_id`, `status`, `created_at`, `school_year`) VALUES
(1, 2, 9, 'Enrolled for Next Year', '2025-05-11 11:00:42', NULL),
(3, 2, 18, 'Enrolled', '2025-05-11 11:01:27', NULL),
(4, 2, 28, 'Enrolled', '2025-05-11 11:01:27', NULL),
(5, 2, 37, 'Enrolled', '2025-05-11 11:01:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_grades`
--

CREATE TABLE `student_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `status` enum('Passed','Failed') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_grades`
--

INSERT INTO `student_grades` (`id`, `student_id`, `subject_id`, `status`) VALUES
(1, 1, 1, 'Passed'),
(4, 1, 2, 'Failed'),
(6, 2, 4, 'Passed'),
(7, 2, 5, 'Passed'),
(8, 2, 6, 'Passed'),
(9, 2, 7, 'Passed'),
(12, 2, 8, 'Passed'),
(15, 2, 10, 'Passed'),
(16, 2, 11, 'Passed'),
(17, 2, 12, 'Passed'),
(18, 2, 13, 'Passed'),
(19, 2, 14, 'Passed'),
(20, 2, 17, 'Passed'),
(22, 2, 21, 'Passed'),
(23, 2, 22, 'Passed'),
(24, 2, 23, 'Passed'),
(25, 4, 62, 'Failed'),
(26, 2, 15, 'Passed'),
(27, 2, 16, 'Passed'),
(28, 2, 19, 'Passed'),
(29, 2, 20, 'Passed'),
(30, 2, 24, 'Passed'),
(31, 2, 25, 'Passed'),
(32, 2, 26, 'Passed'),
(33, 2, 27, 'Passed'),
(35, 2, 29, 'Passed'),
(41, 2, 32, 'Passed'),
(42, 1, 4, 'Passed'),
(43, 1, 5, 'Passed'),
(44, 1, 6, 'Passed'),
(45, 1, 7, 'Failed'),
(46, 1, 8, 'Failed'),
(47, 1, 10, 'Passed'),
(48, 1, 9, 'Passed'),
(49, 1, 11, 'Passed'),
(54, 2, 30, 'Failed'),
(55, 2, 60, 'Failed'),
(58, 2, 9, 'Failed');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `units` int(2) NOT NULL,
  `year_level` int(11) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `type` enum('Required','Elective') NOT NULL DEFAULT 'Required'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `course_id`, `code`, `name`, `units`, `year_level`, `semester`, `type`) VALUES
(4, 1, 'GED101', 'Purposive Communication', 2, 1, '1st', 'Required'),
(5, 1, 'GED102', 'Mathematics in the Modern World', 0, 1, '1st', 'Required'),
(6, 1, 'GED104', 'Ethics', 0, 1, '1st', 'Required'),
(7, 1, 'ITE111', 'Introduction to Computing', 0, 1, '1st', 'Required'),
(8, 1, 'ITE112', 'Fundamentals of Programming', 0, 1, '1st', 'Required'),
(9, 1, 'PE101', 'Physical Activities towards Health and Fitness 1', 2, 1, '1st', 'Required'),
(10, 1, 'NST101', 'National Service Training Program 1', 0, 1, '1st', 'Required'),
(11, 1, 'UID101', 'University Identity 1: Manila Studies', 0, 1, '1st', 'Required'),
(12, 1, 'GED103', 'Science, Technology and Society', 0, 1, '2nd', 'Required'),
(13, 1, 'RZL101', 'The Life and Works of Rizal', 0, 1, '2nd', 'Required'),
(14, 1, 'MST101', 'Living in the IT Era', 0, 1, '2nd', 'Required'),
(15, 1, 'ITE113', 'Intermediate Programming', 0, 1, '2nd', 'Required'),
(16, 1, 'ITE114', 'Information Management', 0, 1, '2nd', 'Required'),
(17, 1, 'ITE115', 'Data Structures and Algorithm', 0, 1, '2nd', 'Required'),
(18, 1, 'PE102', 'Physical Activities towards Health and Fitness 2 ', 2, 1, '2nd', 'Required'),
(19, 1, 'NST102', 'National Service Training Program 2', 0, 1, '2nd', 'Required'),
(20, 1, 'UID102', 'University Identity 2: Ethics and Integrity', 0, 1, '2nd', 'Required'),
(21, 1, 'GED105', 'The Contemporary World', 0, 2, '1st', 'Required'),
(22, 1, 'GED106', 'Understanding the Self', 0, 2, '1st', 'Required'),
(23, 1, 'SSP101', 'The Entrepreneurial Mind', 0, 2, '1st', 'Required'),
(24, 1, 'ITE221', 'Database Management Systems 1', 0, 2, '1st', 'Required'),
(25, 1, 'ITE222', 'Discrete Mathematics', 0, 2, '1st', 'Required'),
(26, 1, 'ITE231', 'Object-Oriented Programming 1', 0, 2, '1st', 'Required'),
(27, 1, 'ITE232', 'Web Development 1', 0, 2, '1st', 'Required'),
(28, 1, 'PE103', 'Physical Activities towards Health and Fitness 3', 2, 2, '1st', 'Required'),
(29, 1, 'UID103', 'University Identity 3: Quality and Excellence', 0, 2, '1st', 'Required'),
(30, 1, 'GED107', 'Art Appreciation', 0, 3, '2nd', 'Required'),
(31, 1, 'GED108', 'Readings in Philippine History', 0, 2, '2nd', 'Required'),
(32, 1, 'AHM101', 'Philippine Popular Culture', 0, 2, '2nd', 'Required'),
(33, 1, 'ITE216', 'Applications Development and Emerging Technologies', 0, 2, '2nd', 'Required'),
(34, 1, 'ITE223', 'Integrative Programming and Technologies 1', 0, 2, '2nd', 'Required'),
(35, 1, 'ITE233', 'Operating System', 0, 2, '2nd', 'Required'),
(36, 1, 'ITE234', 'Database Management Systems 2', 0, 2, '2nd', 'Required'),
(37, 1, 'PE104', 'Physical Activities towards Health and Fitness 4', 2, 2, '2nd', 'Required'),
(38, 1, 'UID104', 'University Identity 4: Unity and Collaboration', 0, 2, '2nd', 'Required'),
(39, 1, 'ITE321', 'Information and Security Assurance 1', 0, 3, '1st', 'Required'),
(40, 1, 'ITE322', 'Networking 1', 0, 3, '1st', 'Required'),
(41, 1, 'ITE331', 'System Analysis and Design', 0, 3, '1st', 'Required'),
(42, 1, 'ITE332', 'Seminar in Information Technology', 0, 3, '1st', 'Required'),
(43, 1, 'SD351', 'Machine Learning', 0, 3, '1st', 'Required'),
(44, 1, 'ITE323', 'Human Computer Interaction', 0, 3, '1st', 'Required'),
(45, 1, 'ITE341', 'Statistics and Probability', 0, 3, '1st', 'Required'),
(46, 1, 'UID105', 'University Identity 5: Achievement and Passion', 0, 3, '1st', 'Required'),
(47, 1, 'ITE324', 'Information Security Assurance 2', 0, 3, '2nd', 'Required'),
(48, 1, 'ITE325', 'Networking 2', 0, 3, '2nd', 'Required'),
(49, 1, 'ITE326', 'Capstone Project and Research 1', 0, 3, '2nd', 'Required'),
(50, 1, 'SD352', 'Web Development 2', 0, 3, '2nd', 'Required'),
(51, 1, 'ITE327', 'Systems Admin and Maintenance', 0, 3, '2nd', 'Required'),
(52, 1, 'SD353', 'Software Development', 0, 3, '2nd', 'Required'),
(53, 1, 'ITE328', 'Quantitative Methods', 0, 3, '2nd', 'Required'),
(54, 1, 'UID106', 'University Identity 6: Leadership and Innovation', 0, 3, '2nd', 'Required'),
(55, 1, 'ITE431', 'Systems Integration and Architecture', 0, 4, '1st', 'Required'),
(56, 1, 'ITE421', 'Social and Professional Issues', 0, 4, '1st', 'Required'),
(57, 1, 'ITE422', 'Capstone Project and Research 2', 0, 4, '1st', 'Required'),
(58, 1, 'SD451', 'Platform Technologies', 0, 4, '1st', 'Required'),
(59, 1, 'ITE441', 'Technopreneurship', 0, 4, '1st', 'Required'),
(60, 1, 'UID107', 'University Identity 7: Workplace Preparation', 2, 4, '1st', 'Required'),
(61, 1, 'ITE423', 'Industry Immersion', 0, 4, '2nd', 'Required'),
(62, 2, 'HSI234', 'Introduction to Nursing', 0, 1, '1st', 'Required'),
(63, 2, 'HSI232', 'Introduction to Operation', 3, 1, '1st', 'Required'),
(65, 3, 'HSI230', 'Introduction to Massaging', 4, 1, '1st', 'Required');

-- --------------------------------------------------------

--
-- Table structure for table `subject_prerequisites`
--

CREATE TABLE `subject_prerequisites` (
  `subject_id` int(11) NOT NULL,
  `prerequisite_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_prerequisites`
--

INSERT INTO `subject_prerequisites` (`subject_id`, `prerequisite_id`) VALUES
(3, 2),
(15, 8),
(18, 9),
(19, 10),
(24, 16),
(26, 15),
(26, 17),
(28, 9),
(28, 18),
(33, 8),
(34, 26),
(36, 24),
(36, 26),
(37, 9),
(37, 18),
(37, 28),
(39, 34),
(40, 34),
(41, 24),
(41, 36),
(47, 39),
(48, 40),
(49, 39),
(49, 40),
(49, 41),
(50, 43),
(51, 39),
(51, 40),
(52, 43),
(53, 44),
(55, 51),
(57, 49),
(58, 50),
(58, 52),
(59, 23);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `password`) VALUES
(1, 'admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_year` (`school_year`,`semester`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`subject_id`);

--
-- Indexes for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `subject_prerequisites`
--
ALTER TABLE `subject_prerequisites`
  ADD PRIMARY KEY (`subject_id`,`prerequisite_id`),
  ADD KEY `prerequisite_id` (`prerequisite_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
