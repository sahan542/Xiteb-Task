CREATE DATABASE IF NOT EXISTS med_prescriptions DEFAULT CHARACTER SET utf8mb4;
USE med_prescriptions;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  address TEXT,
  contact_no VARCHAR(30),
  dob DATE,
  role ENUM('user','pharmacy') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
);

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  note TEXT,
  delivery_address TEXT,
  delivery_time_slot VARCHAR(20), 
  status ENUM('pending','quoted','accepted','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescriptions_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_prescriptions_user (user_id),
  INDEX idx_prescriptions_status (status),
  INDEX idx_prescriptions_user_status (user_id, status)
);

CREATE TABLE IF NOT EXISTS prescription_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_images_rx
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
  INDEX idx_prescription_images_rx (prescription_id)
);

CREATE TABLE IF NOT EXISTS quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  pharmacy_id INT NOT NULL,               
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  expires_at DATETIME NULL,
  sent_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  accepted_flag TINYINT(1)
    AS (status = 'accepted') STORED,
  draft_flag TINYINT(1)
    AS (status = 'draft') STORED,
  CONSTRAINT fk_quotations_rx
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_quotations_pharmacy
    FOREIGN KEY (pharmacy_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_quotations_rx (prescription_id),
  INDEX idx_quotations_pharmacy (pharmacy_id),
  INDEX idx_quotations_status (status),
  INDEX idx_quotations_rx_status (prescription_id, status),
  UNIQUE KEY uq_quotation_one_accepted_per_rx (prescription_id, accepted_flag),
  UNIQUE KEY uq_quotation_one_draft_per_rx_pharmacy (prescription_id, pharmacy_id, draft_flag)
);

CREATE TABLE IF NOT EXISTS quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT NOT NULL,
  drug VARCHAR(255) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) UNSIGNED NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,      
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quotation_items_quote
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
  INDEX idx_quote_items_quote (quotation_id)
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(64) NOT NULL,
  entity_id INT NULL,
  message VARCHAR(255) NOT NULL,
  seen TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id),
  INDEX idx_notifications_seen (seen),
  INDEX idx_notifications_user_created (user_id, created_at)
);
