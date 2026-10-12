CREATE DATABASE IF NOT EXISTS hotel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_booking;
SET FOREIGN_KEY_CHECKS=0;DROP TABLE IF EXISTS bookings;DROP TABLE IF EXISTS rooms;DROP TABLE IF EXISTS users;SET FOREIGN_KEY_CHECKS=1;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(160) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB;
CREATE TABLE rooms(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,room_type VARCHAR(80) NOT NULL,bed_type VARCHAR(80) NOT NULL,capacity INT NOT NULL DEFAULT 2,price_per_night DECIMAL(10,2) NOT NULL,short_description VARCHAR(255),description TEXT,amenities TEXT,image_url VARCHAR(500),status ENUM('available','maintenance') NOT NULL DEFAULT 'available',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB;
CREATE TABLE bookings(id INT AUTO_INCREMENT PRIMARY KEY,reference_no VARCHAR(30) NOT NULL UNIQUE,user_id INT NOT NULL,room_id INT NOT NULL,check_in DATE NOT NULL,check_out DATE NOT NULL,guests INT NOT NULL,nights INT NOT NULL,total_amount DECIMAL(10,2) NOT NULL,status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id)REFERENCES users(id),FOREIGN KEY(room_id)REFERENCES rooms(id))ENGINE=InnoDB;
INSERT INTO users(name,email,password,role,status) VALUES
('Hotel Administrator','admin@luxestay.test','$2y$12$172ZMGaOn4TAGHcMWW25W./p1E7eBaZoYbl8q/viyyQP6VQmhBwo2','admin','active'),
('Hotel Receptionist','staff@luxestay.test','$2y$12$172ZMGaOn4TAGHcMWW25W./p1E7eBaZoYbl8q/viyyQP6VQmhBwo2','staff','active');
INSERT INTO rooms(name,room_type,bed_type,capacity,price_per_night,short_description,description,amenities,image_url,status) VALUES
('Deluxe King Room','Deluxe','King Bed',2,3200,'Elegant room with king-bed comfort.','A refined room designed for couples and business travelers with a work desk, private bathroom and modern conveniences.','WiFi, Air Conditioning, Smart TV, Private Bathroom, Coffee Maker','https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80','available'),
('Premier Twin Room','Premier','2 Twin Beds',3,3900,'Bright room ideal for friends or small families.','Enjoy two comfortable twin beds, generous space and upgraded amenities.','WiFi, Air Conditioning, Smart TV, Mini Fridge, Private Bathroom','https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80','available'),
('Executive Suite','Suite','King Bed + Sofa',4,5800,'Premium suite with a separate lounge.','Our Executive Suite offers a separate living area and premium bedding.','WiFi, Air Conditioning, Smart TV, Living Area, Mini Bar, Bathtub','https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80','available'),
('Family Suite','Family','2 Queen Beds',5,6500,'Large family-friendly room with two queen beds.','A practical and comfortable choice for families with generous sleeping capacity.','WiFi, Air Conditioning, Smart TV, Refrigerator, Dining Table, Private Bathroom','https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=1200&q=80','available'),
('Superior Queen Room','Superior','Queen Bed',2,2800,'Clean modern room for comfortable stays.','A thoughtfully designed queen room with all essential amenities.','WiFi, Air Conditioning, TV, Private Bathroom','https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80','available'),
('Grand Suite','Luxury Suite','King Bed',2,7800,'High-end suite with premium finishes.','A luxurious stay featuring upscale interiors and comfortable lounge seating.','WiFi, Air Conditioning, 55-inch TV, Lounge, Mini Bar, Bathtub, Breakfast','https://images.unsplash.com/photo-1591088398332-8a7791972843?auto=format&fit=crop&w=1200&q=80','available');




-- Security controls: login throttling and administrator-visible audit events.
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_time (email_hash, attempted_at),
  INDEX idx_login_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(60) NOT NULL,
  user_id INT NULL,
  ip_hash CHAR(64) NOT NULL,
  details VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_security_created (created_at),
  INDEX idx_security_event_type (event_type),
  CONSTRAINT fk_security_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_otps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL UNIQUE,
  user_id INT NULL,
  otp_hash VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_sent_at DATETIME NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at DATETIME NULL,
  reset_token_hash CHAR(64) NULL,
  reset_expires_at DATETIME NULL,
  INDEX (user_id),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;




-- Email-based two-factor authentication and remembered devices (10 days).
CREATE TABLE IF NOT EXISTS login_2fa_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_login_2fa_challenge_user (user_id),
  CONSTRAINT fk_login_2fa_challenge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_2fa_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_2fa_devices_user (user_id),
  INDEX idx_login_2fa_devices_expiry (expires_at),
  CONSTRAINT fk_login_2fa_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
