-- College Event Management System
-- Reconstructed schema based on application code
-- Compatible with MySQL 8 / Aiven

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    roll_number VARCHAR(50) DEFAULT NULL,
    role ENUM('student','admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    venue VARCHAR(200) DEFAULT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    max_participants INT DEFAULT NULL,
    registration_deadline DATETIME DEFAULT NULL,
    banner_color VARCHAR(20) DEFAULT NULL,
    poster_image VARCHAR(255) DEFAULT NULL,
    registration_link VARCHAR(255) DEFAULT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    contact_email VARCHAR(150) DEFAULT NULL,
    contact_phone VARCHAR(30) DEFAULT NULL,
    status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS outer_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    organizing_college VARCHAR(200) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    venue VARCHAR(200) DEFAULT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    registration_deadline DATETIME DEFAULT NULL,
    registration_link VARCHAR(255) DEFAULT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    contact_email VARCHAR(150) DEFAULT NULL,
    contact_phone VARCHAR(30) DEFAULT NULL,
    poster_image VARCHAR(255) DEFAULT NULL,
    status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    full_name VARCHAR(150) DEFAULT NULL,
    roll_number VARCHAR(50) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    year_of_study VARCHAR(20) DEFAULT NULL,
    team_name VARCHAR(150) DEFAULT NULL,
    event_type VARCHAR(20) DEFAULT 'campus',
    status ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS outer_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outer_event_id INT NOT NULL,
    full_name VARCHAR(150) DEFAULT NULL,
    roll_number VARCHAR(50) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    year_of_study VARCHAR(20) DEFAULT NULL,
    team_name VARCHAR(150) DEFAULT NULL,
    college_name VARCHAR(200) DEFAULT NULL,
    status ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_outer_registration (user_id, outer_event_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (outer_event_id) REFERENCES outer_events(id) ON DELETE CASCADE
);

-- Optional: create an admin account to log in with immediately
-- Password below is a bcrypt hash for: Admin@123
-- You can change the email/password after logging in
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@college.edu', '$2b$10$7JSGj05qT6Jg4gtWDSQu3OYS4Rge3PkqpvqTGImt4I5AzKuIyKJ2a', 'admin');
