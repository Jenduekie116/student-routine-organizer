-- phpMyAdmin SQL Dump
-- Student Routine Organizer - Combined Schema (updated)
-- UCCD3243 Server-Side Web Applications Development

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `student_routine_db`
-- --------------------------------------------------------

-- UPDATED: added emoji + custom_activity_name so every activity type
-- (including free-choice "Other" sports) can be shown with an icon,
-- matching the pattern used by habit_records.
CREATE TABLE `exercise_records` (
  `exercise_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  `custom_activity_name` varchar(50) DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `calories_burned` int(11) NOT NULL,
  `exercise_date` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `habit_records` (
  `habit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `habit_name` varchar(100) NOT NULL,
  `emoji` varchar(10) DEFAULT '✅',
  `target_frequency` varchar(50) NOT NULL,
  `completion_status` enum('Pending','Completed') NOT NULL DEFAULT 'Pending',
  `habit_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `journal_entries` (
  `journal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `mood` enum('Happy','Sad','Neutral','Excited','Anxious') NOT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('Income','Expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seeded admin account for demo/grading purposes.
-- Login: admin@routinely.com / password: Admin@123
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('System Admin', 'admin@routinely.com', '$2b$10$vrHB/I.1V.G6ORi/IofEFOKJ.xmujJH3cv5o0z5KZ8PJu6yruBAUa', 'admin');

-- --------------------------------------------------------
-- Indexes
-- --------------------------------------------------------

ALTER TABLE `exercise_records`
  ADD PRIMARY KEY (`exercise_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `habit_records`
  ADD PRIMARY KEY (`habit_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`journal_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

-- --------------------------------------------------------
-- AUTO_INCREMENT
-- --------------------------------------------------------

ALTER TABLE `exercise_records`
  MODIFY `exercise_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `habit_records`
  MODIFY `habit_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `journal_entries`
  MODIFY `journal_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Constraints
-- --------------------------------------------------------

ALTER TABLE `habit_records`
  ADD CONSTRAINT `habit_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `exercise_records`
  ADD CONSTRAINT `exercise_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;