DROP DATABASE IF EXISTS campusconnect;
CREATE DATABASE campusconnect;
USE campusconnect;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- ------------------------
-- ADMINS
-- ------------------------
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO admins (username, password)
VALUES ('admin', '$2y$10$ocQQzzbnunRNiETEHRGVqOQ/vAoEZ/qKVOhsT2k9vadT/YTox.Av6');

-- ------------------------
-- STUDENTS
-- ------------------------
CREATE TABLE students (
  student_id VARCHAR(8) PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  course VARCHAR(20) NOT NULL,
  section VARCHAR(10) NOT NULL,
  age INT NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------
-- ORGANIZATIONS
-- ------------------------
CREATE TABLE organizations (
  org_id INT AUTO_INCREMENT PRIMARY KEY,
  org_name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  course VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  course_group VARCHAR(50),
  image VARCHAR(255),
  info TEXT
) ENGINE=InnoDB;

-- SAMPLE DATA (IMPORTANT)
INSERT INTO organizations (org_id, org_name, description, course, course_group, image) VALUES
(67, 'College of Arts and Sciences Student Council', 'CAS Student Council', '', 'CAS', 'img/Orgs/CAS/CAS.png'),
(68, 'Psychology Society', 'Psychology organization', '', 'CAS', 'img/Orgs/CAS/PSYCHSOC.jpg'),
(70, 'Young English Students’ Society', 'English org', '', 'CTE', NULL);

-- ------------------------
-- APPLICATIONS
-- ------------------------
CREATE TABLE applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(8),
  org_id INT,
  status ENUM('Pending','Approved','Rejected','Interview') DEFAULT 'Pending',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (student_id) REFERENCES students(student_id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  FOREIGN KEY (org_id) REFERENCES organizations(org_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------
-- POSTS
-- ------------------------
CREATE TABLE posts (
  post_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  tag VARCHAR(50) DEFAULT 'latest',
  image VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

COMMIT;