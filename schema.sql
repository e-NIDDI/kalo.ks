-- ========================================================
-- Kalo.ks - Production MySQL Database Schema & Seed Data
-- Platformë për shkëmbimin e materialeve dhe provimeve (UP)
-- Charset: utf8mb4, Engine: InnoDB
-- ========================================================

CREATE DATABASE IF NOT EXISTS `kaloks` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kaloks`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Table structure for table `faculties`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `faculties`;
CREATE TABLE `faculties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(250) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `icon` VARCHAR(100) DEFAULT 'fa-university',
    `color_code` VARCHAR(50) DEFAULT '#f59e0b'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(250) NOT NULL,
    `email` VARCHAR(250) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'student',
    `faculty_id` INT DEFAULT NULL,
    `reputation_points` INT NOT NULL DEFAULT 10,
    `avatar_url` TEXT DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `courses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `name` VARCHAR(250) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `professor_name` VARCHAR(250) DEFAULT NULL,
    `semester` INT DEFAULT 1,
    CONSTRAINT `fk_courses_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `materials`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `title` VARCHAR(250) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL DEFAULT 'pdf',
    `file_size` VARCHAR(50) NOT NULL DEFAULT '1.0MB',
    `views_count` INT NOT NULL DEFAULT 0,
    `downloads_count` INT NOT NULL DEFAULT 0,
    `upvotes_count` INT NOT NULL DEFAULT 0,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_materials_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_materials_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `upvotes`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `upvotes`;
CREATE TABLE `upvotes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `material_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_material_unique` (`user_id`, `material_id`),
    CONSTRAINT `fk_upvotes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_upvotes_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `comments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `comment_text` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_comments_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table structure for table `saved_materials`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `saved_materials`;
CREATE TABLE `saved_materials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `material_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_saved_unique` (`user_id`, `material_id`),
    CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_saved_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table structure for table `live_chat_messages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `live_chat_messages`;
CREATE TABLE `live_chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chat_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table structure for table `notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Faculties
INSERT INTO `faculties` (`id`, `name`, `code`, `icon`, `color_code`) VALUES
(1, 'Fakulteti i Inxhinierisë Elektrike dhe Kompjuterike (FIEK)', 'FIEK', 'fa-microchip', '#f59e0b'),
(2, 'Fakulteti i Shkencave Matematiko-Natyrore (FNA)', 'FNA', 'fa-flask', '#0d9488'),
(3, 'Fakulteti Ekonomik (FE)', 'FE', 'fa-chart-line', '#10b981'),
(4, 'Fakulteti i Mjekësisë (FM)', 'FM', 'fa-stethoscope', '#3b82f6'),
(5, 'Fakulteti i Arteve (FK)', 'FK', 'fa-palette', '#ec4899');

-- Users (All demo users have password 'password123' hashed with BCRYPT)
-- Password Hash below is generated using password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `faculty_id`, `reputation_points`, `avatar_url`, `bio`, `created_at`) VALUES
(1, 'Alice Anderson', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 150, 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80', 'Studente e vitit të 3-të në Inxhinieri Kompjuterike. Më pëlqen të ndaj zgjidhje të detajuara provimesh.', '2024-01-15 10:00:00'),
(2, 'Bob Smith', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 2, 220, 'https://images.unsplash.com/photo-1534528741775-53994a6488df?auto=format&fit=crop&w=400&q=80', 'Student i apasionuar pas matematikës diskrete dhe programimit në C++.', '2024-02-10 11:30:00'),
(3, 'Carol Johnson', 'carol@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 3, 185, 'https://images.unsplash.com/photo-1438761681033-6461ffad8d81?auto=format&fit=crop&w=400&q=80', 'Studime në Menaxhment dhe Informatikë. Kontribuuese aktive me përmbledhje leksionesh.', '2024-02-18 14:15:00'),
(4, 'David Lee', 'david@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 520, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80', 'Administrator i platformës Kalo.ks dhe asistent mësimor.', '2023-11-01 09:00:00');

-- Courses
INSERT INTO `courses` (`id`, `faculty_id`, `name`, `code`, `professor_name`, `semester`) VALUES
(1, 1, 'Programim në C++', 'CS101', 'Prof. Dr. Blerim Rexha', 1),
(2, 1, 'Qarqet Elektrike & Elektronikë', 'EE201', 'Prof. Dr. Vjollca Komoni', 2),
(3, 1, 'Bazat e të Dhënave', 'CS301', 'Prof. Dr. Lule Ahmedi', 3),
(4, 1, 'Automatikë & Robotikë', 'AUT401', 'Prof. Dr. Zirije Hasani', 4),
(5, 2, 'Matematika Diskrete', 'MAT101', 'Prof. Dr. Qamil Kabashi', 1),
(6, 2, 'Algoritmet dhe Strukturat e të Dhënave', 'CS202', 'Prof. Dr. Kadri Sylejmani', 2),
(7, 3, 'Hyrje në Ekonomi & Mikroekonomi', 'ECON101', 'Prof. Dr. Besnik Bislimi', 1),
(8, 3, 'Menaxhimi Financiar', 'FIN301', 'Prof. Dr. Muhamet Mustafa', 3),
(9, 4, 'Anatomi e Përgjithshme Njerëzore', 'MED101', 'Prof. Dr. Suzana Manxhuka', 1),
(10, 5, 'Histori e Artit Modern', 'ART201', 'Prof. Dr. Mehmet Behluli', 2);

-- Materials
INSERT INTO `materials` (`id`, `user_id`, `course_id`, `title`, `description`, `file_path`, `file_type`, `file_size`, `views_count`, `downloads_count`, `upvotes_count`, `is_verified`, `created_at`) VALUES
(1, 1, 1, 'Afati i Qershorit 2024 - Provimi me Zgjidhje të Plota C++', 'Zgjidhja hap-pas-hapi e të gjitha detyrave me pointera, klasa dhe shabllone (templates) për provimin e Programimit në C++.', 'uploads/sample_cplusplus_2024.pdf', 'pdf', '2.4MB', 245, 180, 89, 1, '2024-06-25 14:00:00'),
(2, 1, 3, 'Përmbledhje SQL & Modelimi Relacionale (ER Diagramet)', 'Skriptë e përgatitur me shembuj të normalizimit (1NF, 2NF, 3NF, BCNF) dhe pyetje tipike të kolokviumit të parë.', 'uploads/sample_databases_notes.pdf', 'pdf', '3.1MB', 190, 142, 65, 1, '2024-07-02 09:30:00'),
(3, 2, 6, 'Përmbledhje Algoritmesh: Grafet & Pemët Binare', 'Shpjegime vizuale dhe zbatime në kod për algoritmet Dijkstra, BFS, DFS dhe pemët AVL.', 'uploads/sample_algorithms_guide.pdf', 'pdf', '4.2MB', 160, 115, 74, 1, '2024-07-10 16:45:00'),
(4, 2, 2, 'Raport Laboratori: Matjet në Qarqe të Rrymës Alternative', 'Rezultatet e plota eksperimentale, tabelat me të dhëna dhe llogaritjet e rezonancës me oshiloskop.', 'uploads/sample_ee_lab_reports.zip', 'zip', '12.5MB', 110, 84, 42, 0, '2024-08-15 11:20:00'),
(5, 3, 7, 'Zgjidhje Detyrash nga Mikroekonomia - Ekuilibri i Tregut', 'Analizë e kërkesës dhe ofertës, elasticiteti i çmimeve dhe ushtrime të zgjidhura nga testi i fundit.', 'uploads/sample_microeconomics_solved.pdf', 'pdf', '1.8MB', 135, 95, 53, 1, '2024-08-20 10:10:00'),
(6, 3, 8, 'Formulat Kryesore Financiare & Shembuj të Llogaritjes së NPV', 'Tabela e formulave për vlerën aktuale neto (NPV), normën e brendshme të kthimit (IRR) dhe analizën e rrezikut.', 'uploads/sample_finance_formulas.pdf', 'pdf', '1.2MB', 98, 68, 38, 1, '2024-09-01 13:00:00'),
(7, 4, 4, 'Skriptë në Automatikë: Rregullatorët PID dhe Diagramet Nyquist', 'Udhëzues i avancuar nga asistenti me shembuj praktike dhe modele në MATLAB/Simulink.', 'uploads/sample_robotics_pid.pdf', 'pdf', '5.6MB', 310, 240, 120, 1, '2024-06-10 08:30:00'),
(8, 1, 5, 'Detyra të Zgjidhura në Induksion Matematikor & Teori Grafesh', 'Set me mbi 30 detyra provimi me të gjitha hapat e vërtetimit të saktë matematik.', 'uploads/sample_discrete_math.pdf', 'pdf', '2.8MB', 85, 62, 31, 0, '2024-09-12 15:40:00');

-- Upvotes
INSERT INTO `upvotes` (`user_id`, `material_id`, `created_at`) VALUES
(1, 1, '2024-06-25 15:00:00'),
(2, 1, '2024-06-26 10:00:00'),
(3, 1, '2024-06-27 12:00:00'),
(4, 1, '2024-06-28 14:00:00'),
(1, 2, '2024-07-03 09:00:00'),
(2, 3, '2024-07-11 11:00:00'),
(3, 5, '2024-08-21 16:00:00'),
(1, 7, '2024-06-11 10:00:00');

-- Comments
INSERT INTO `comments` (`material_id`, `user_id`, `comment_text`, `created_at`) VALUES
(1, 2, 'Shumë faleminderit për këtë zgjidhje! Më ndihmoi jashtëzakonisht shumë te pyetja e tretë me polimorfizëm.', '2024-06-26 10:15:00'),
(1, 3, 'A ka mundësi të ngarkoni edhe afatin e Janarit nëse e keni të zgjidhur?', '2024-06-27 14:20:00'),
(1, 1, 'Faleminderit të gjithëve! Po, afati i Janarit është duke u përgatitur dhe do ta publikoj së shpejti.', '2024-06-27 16:00:00'),
(3, 1, 'Shpjegimi i pemëve AVL është më i miri që kam parë deri sot. Respekte!', '2024-07-12 18:30:00'),
(7, 2, 'Material i nivelit të lartë, përgëzime për punën e shkëlqyer!', '2024-06-12 11:00:00');

-- Saved Materials (Bookmarks)
INSERT INTO `saved_materials` (`user_id`, `material_id`, `created_at`) VALUES
(1, 3, '2024-07-11 12:00:00'),
(1, 7, '2024-07-15 08:30:00'),
(2, 1, '2024-06-26 10:30:00'),
(3, 2, '2024-07-05 13:00:00'),
(4, 1, '2024-06-29 17:00:00');

-- Live Chat Messages
INSERT INTO `live_chat_messages` (`faculty_id`, `user_id`, `message`, `created_at`) VALUES
(1, 1, 'Përshëndetje kolegë! A e ka dikush testin e kolokviumit të dytë në Qarqet Elektrike?', '2024-10-14 10:00:00'),
(1, 2, 'Po, e kam unë të fotografuar me të gjitha ushtrimet. Do ta ngarkoj te seksioni i materialeve për pak.', '2024-10-14 10:05:00'),
(1, 4, 'Mos harroni që nesër në orën 12:00 është konsultimi i fundit para afatit zyrtar!', '2024-10-14 10:12:00'),
(2, 2, 'Kush po përgatitet për provimin në Algoritme? Mund të organizojmë një grup studimi online.', '2024-10-14 11:30:00'),
(3, 3, 'Suksese të gjithëve në provimin e Mikroekonomisë! Skripta e ngarkuar është super e dobishme.', '2024-10-14 12:00:00');

-- Notifications
INSERT INTO `notifications` (`user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 'Mirëseerdhët në Kalo.ks! Keni fituar 10 pikë fillestare reputacioni.', 1, '2024-01-15 10:01:00'),
(1, 'Zgjidhja juaj \'Afati i Qershorit 2024 - C++\' u verifikua nga moderatori! +20 pikë reputacioni.', 0, '2024-06-26 09:00:00'),
(2, 'Bob Smith mori 5 pikë reputacioni nga votimi i materialit \'Përmbledhje Algoritmesh\'.', 0, '2024-07-11 11:05:00'),
(3, 'Kolegu juaj Alice komentoi në materialin tuaj.', 1, '2024-08-22 14:00:00');
