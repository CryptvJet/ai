-- AI application database schema

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_token VARCHAR(64) NOT NULL,
  user_id INT NOT NULL,
  ip VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP,
  INDEX(session_token),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  meta JSON NULL
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  content TEXT NOT NULL,
  tokens INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  meta JSON NULL,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);

CREATE TABLE canned_responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255),
  body TEXT,
  tags VARCHAR(255)
);

CREATE TABLE analytics_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  meta JSON
);

CREATE TABLE browser_fingerprints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NULL,
  ip VARCHAR(45),
  user_agent VARCHAR(255),
  tz VARCHAR(64),
  lang VARCHAR(32),
  viewport_w INT,
  viewport_h INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pcore_write_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(64) NOT NULL,
  payload JSON NOT NULL,
  status ENUM('queued','processing','done','error') DEFAULT 'queued',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  error TEXT NULL
);

CREATE TABLE api_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255),
  params JSON,
  ip VARCHAR(45),
  ua VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed admin user
INSERT INTO users(email,password_hash) VALUES('admin@local','$2y$12$JpTgwyTsBS3hsPa56ZPRuOs3h1NxzwhLSCBaXRbqBM3ej4IYUvNBe');
