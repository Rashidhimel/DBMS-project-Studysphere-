-- studysphere_schema.sql (simplified)
-- StudySphere - MySQL 8

CREATE DATABASE IF NOT EXISTS studysphere;
USE studysphere;

SET @@sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS finance_types_tbl;

-- 1. LOOKUP TABLES
CREATE TABLE degree_levels (
  degree_level_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE test_types (
  test_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE achievement_types (
  achievement_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE application_statuses (
  application_status_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE application_stages (
  application_stage_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  order_index INT NOT NULL DEFAULT 0,
  entry_status_id INT UNSIGNED NULL,
  exit_status_id INT UNSIGNED NULL,
  is_decision_point TINYINT(1) NOT NULL DEFAULT 0,
  allowed_roles JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_stage_order (order_index),
  KEY idx_application_stages_entry (entry_status_id),
  KEY idx_application_stages_exit (exit_status_id)
);

ALTER TABLE application_stages ADD CONSTRAINT chk_order_positive CHECK (order_index >= 0);

CREATE TABLE finance_types (
  finance_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification_types (
  notification_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE countries (
  iso2 CHAR(2) NOT NULL PRIMARY KEY,
  iso3 CHAR(3) NULL,
  name VARCHAR(255) NOT NULL,
  region VARCHAR(100) NULL,
  flag_url VARCHAR(1024) NULL,
  phone_code VARCHAR(16) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_countries_name (name)
);

CREATE TABLE subjects (
  subject_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tags (
  tag_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exchange_rates (
  rate_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  from_currency CHAR(3) NOT NULL,
  to_currency CHAR(3) NOT NULL,
  rate DECIMAL(20,8) NOT NULL,
  effective_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_rate (from_currency, to_currency, effective_date)
);

-- 2. RBAC
CREATE TABLE roles (
  role_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
  permission_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(150) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
);

-- 3. CORE AUTH & USERS
CREATE TABLE users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  default_role_id INT UNSIGNED NOT NULL DEFAULT 1,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_disabled TINYINT(1) NOT NULL DEFAULT 0,
  preferred_language VARCHAR(10) DEFAULT 'en',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY (default_role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
  KEY idx_users_email (email),
  KEY idx_users_username (username)
);

CREATE TABLE user_profiles (
  profile_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  gender ENUM('male','female','other') DEFAULT NULL,
  dob DATE NULL,
  nationality CHAR(2) NULL,
  profile_photo_url VARCHAR(2048) NULL,
  bio TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (nationality) REFERENCES countries(iso2) ON DELETE SET NULL,
  KEY idx_user_profiles_nationality (nationality)
);

CREATE TABLE addresses (
  address_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  address_type ENUM('home','permanent','mailing','other') DEFAULT 'home',
  country_code CHAR(2) NULL,
  region VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  address_line1 VARCHAR(255) NULL,
  address_line2 VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE SET NULL,
  KEY idx_addresses_user (user_id),
  KEY idx_addresses_country (country_code)
);

CREATE TABLE phones (
  phone_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  country_code VARCHAR(6) DEFAULT NULL,
  phone_number VARCHAR(50) NOT NULL,
  phone_type ENUM('mobile','work','home','other') DEFAULT 'mobile',
  is_primary TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_phones_user (user_id),
  KEY idx_phones_number (country_code, phone_number)
);

CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

-- 4. SECURITY & SESSIONS
CREATE TABLE email_verifications (
  verify_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code VARCHAR(128) NULL,
  token_uuid CHAR(36) NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_email_verifications_user (user_id),
  KEY idx_email_verifications_code (code)
);

CREATE TABLE password_resets (
  reset_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token CHAR(36) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_password_resets_user (user_id),
  KEY idx_password_resets_token (token)
);

CREATE TABLE login_attempts (
  attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(512) NULL,
  status ENUM('success','failed','locked') NOT NULL,
  reason VARCHAR(255) NULL,
  attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_login_attempts_user (user_id),
  KEY idx_login_attempts_ip (ip_address)
);

CREATE TABLE sessions (
  session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  session_token CHAR(36) NOT NULL,
  refresh_token_hash CHAR(128) NOT NULL,
  device_info VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY ux_sessions_token (session_token),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_expires (expires_at)
);

-- 5. STUDENTS & PREFERENCES
CREATE TABLE students (
  student_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  degree_level_id INT UNSIGNED NOT NULL,
  country_from CHAR(2) NULL,
  budget_min DECIMAL(12,2) NULL,
  budget_max DECIMAL(12,2) NULL,
  subject_area_id INT UNSIGNED NULL,
  part_time_needed TINYINT(1) DEFAULT 0,
  scholarship_needed TINYINT(1) DEFAULT 0,
  preferred_job_type VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (degree_level_id) REFERENCES degree_levels(degree_level_id) ON DELETE RESTRICT,
  FOREIGN KEY (country_from) REFERENCES countries(iso2) ON DELETE SET NULL,
  FOREIGN KEY (subject_area_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_students_user (user_id),
  KEY idx_students_degree (degree_level_id),
  KEY idx_students_subject (subject_area_id)
);

CREATE TABLE student_preferred_countries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  country_code CHAR(2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE CASCADE,
  UNIQUE KEY uq_student_country (student_id, country_code),
  KEY idx_student_pref_country (student_id, country_code)
);

-- 6. ATTACHMENTS (main)
CREATE TABLE attachments (
  attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uploader_user_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  file_url VARCHAR(2048) NOT NULL,
  file_type VARCHAR(100) NULL,
  file_size INT UNSIGNED NULL,
  checksum VARCHAR(128) NULL,
  verified_by_admin_id INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (uploader_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by_admin_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_attachments_uploader (uploader_user_id),
  KEY idx_attachments_verified_by (verified_by_admin_id),
  KEY idx_attachments_created_at (created_at),
  KEY idx_attachments_file_type (file_type)
);

-- 7. ACADEMIC & TESTS
CREATE TABLE education_records (
  record_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  level ENUM('High School','UG','GRAD','PHD','Other') NOT NULL,
  institution_name VARCHAR(255) NULL,
  country_code CHAR(2) NULL,
  degree_name VARCHAR(255) NULL,
  major_id INT UNSIGNED NULL,
  gpa DECIMAL(6,3) NULL,
  grading_scale DECIMAL(6,3) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  medium VARCHAR(50) NULL,
  ceeb_code VARCHAR(50) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE SET NULL,
  FOREIGN KEY (major_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
  KEY idx_education_student (student_id),
  KEY idx_education_major (major_id),
  KEY idx_education_country (country_code)
);

CREATE TABLE education_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  education_record_id INT UNSIGNED NOT NULL,
  attachment_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  FOREIGN KEY (education_record_id) REFERENCES education_records(record_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_edu_docs_record (education_record_id),
  KEY idx_edu_docs_attachment (attachment_id)
);

CREATE TABLE test_scores (
  score_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  test_type_id INT UNSIGNED NOT NULL,
  test_date DATE NULL,
  valid_until DATE NULL,
  report_attachment_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (test_type_id) REFERENCES test_types(test_type_id) ON DELETE RESTRICT,
  FOREIGN KEY (report_attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
  KEY idx_test_scores_student (student_id),
  KEY idx_test_scores_type (test_type_id),
  KEY idx_test_scores_date (test_date)
);

CREATE TABLE test_score_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  score_id INT UNSIGNED NOT NULL,
  attachment_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT UNSIGNED NULL,
  FOREIGN KEY (score_id) REFERENCES test_scores(score_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_test_score_docs_score (score_id),
  KEY idx_test_score_docs_attachment (attachment_id)
);

CREATE TABLE test_score_sections (
  section_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  score_id INT UNSIGNED NOT NULL,
  section_name VARCHAR(100) NOT NULL,
  score_value DECIMAL(6,2) NULL,
  max_score DECIMAL(6,2) NULL,
  percentile DECIMAL(5,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (score_id) REFERENCES test_scores(score_id) ON DELETE CASCADE,
  KEY idx_test_score_sections_score (score_id),
  KEY idx_test_score_sections_name (section_name)
);

-- 8. ACHIEVEMENTS & PART-TIME
CREATE TABLE achievements (
  achievement_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  achievement_type_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NULL,
  description TEXT NULL,
  organization VARCHAR(255) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  attachment_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_type_id) REFERENCES achievement_types(achievement_type_id) ON DELETE RESTRICT,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
  KEY idx_achievements_student (student_id),
  KEY idx_achievements_type (achievement_type_id)
);

CREATE TABLE achievement_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  achievement_id INT UNSIGNED NOT NULL,
  attachment_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  FOREIGN KEY (achievement_id) REFERENCES achievements(achievement_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_achievement_docs_achievement (achievement_id),
  KEY idx_achievement_docs_attachment (attachment_id)
);

CREATE TABLE part_time_preferences (
  preference_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  job_sector VARCHAR(255) NULL,
  job_type VARCHAR(255) NULL,
  worked_before TINYINT(1) DEFAULT 0,
  experience_years DECIMAL(4,1) DEFAULT 0,
  expected_wage DECIMAL(12,2) NULL,
  expected_work_hours INT NULL,
  visa_required TINYINT(1) DEFAULT 0,
  references_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  KEY idx_parttime_student (student_id)
);

-- 9. UNIVERSITIES & PROGRAMS
CREATE TABLE universities (
  university_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  country_code CHAR(2) NULL,
  city VARCHAR(100) NULL,
  website VARCHAR(512) NULL,
  logo_url VARCHAR(1024) NULL,
  qs_ranking INT NULL,
  approx_tuition DECIMAL(12,2) NULL,
  student_count INT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_universities_name (name),
  KEY idx_universities_country (country_code)
);

CREATE TABLE programs (
  program_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  university_id INT UNSIGNED NOT NULL,
  program_name VARCHAR(255) NOT NULL,
  degree_level_id INT UNSIGNED NOT NULL,
  department VARCHAR(255) NULL,
  duration_months INT NULL,
  tuition_fee DECIMAL(12,2) NULL,
  currency CHAR(3) DEFAULT 'USD',
  intake_months VARCHAR(100) NULL,
  application_deadline DATE NULL,
  early_deadline DATE NULL,
  regular_deadline DATE NULL,
  rolling_admission TINYINT(1) DEFAULT 0,
  decision_release_weeks INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
  FOREIGN KEY (degree_level_id) REFERENCES degree_levels(degree_level_id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_programs_university (university_id),
  KEY idx_programs_degree (degree_level_id),
  KEY idx_programs_deadline (application_deadline)
);

CREATE TABLE program_subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
  KEY idx_program_subjects (program_id, subject_id)
);

CREATE TABLE program_requirements (
  req_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  requirement_type VARCHAR(50) NOT NULL,
  minimum_value VARCHAR(255) NULL,
  is_mandatory TINYINT(1) DEFAULT 1,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  KEY idx_program_requirements_program (program_id)
);

CREATE TABLE program_requirement_tests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  req_id INT UNSIGNED NOT NULL,
  test_type_id INT UNSIGNED NOT NULL,
  minimum_score VARCHAR(64) NULL,
  is_mandatory TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (req_id) REFERENCES program_requirements(req_id) ON DELETE CASCADE,
  FOREIGN KEY (test_type_id) REFERENCES test_types(test_type_id) ON DELETE RESTRICT,
  KEY idx_prt_req (req_id),
  KEY idx_prt_test (test_type_id)
);

CREATE TABLE program_requirement_docs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  req_id INT UNSIGNED NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_mandatory TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (req_id) REFERENCES program_requirements(req_id) ON DELETE CASCADE,
  KEY idx_prd_req (req_id)
);

CREATE TABLE university_tags (
  university_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (university_id, tag_id),
  FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE
);

CREATE TABLE program_tags (
  program_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (program_id, tag_id),
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE
);

-- 10. APPLICATIONS & WORKFLOW
CREATE TABLE applications (
  application_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  program_id INT UNSIGNED NOT NULL,
  university_id INT UNSIGNED NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  application_status_id INT UNSIGNED NOT NULL,
  application_stage_id INT UNSIGNED NULL,
  stage_order INT DEFAULT 0,
  priority_deadline_type ENUM('early','regular','rolling') NULL,
  estimated_decision_date DATE NULL,
  status_reason VARCHAR(255) NULL,
  fee_amount DECIMAL(10,2) DEFAULT 0,
  deadline DATE NULL,
  submitted_at DATETIME NULL,
  decision_date DATETIME NULL,
  decision_by INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
  FOREIGN KEY (application_status_id) REFERENCES application_statuses(application_status_id) ON DELETE RESTRICT,
  FOREIGN KEY (application_stage_id) REFERENCES application_stages(application_stage_id) ON DELETE SET NULL,
  FOREIGN KEY (decision_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_applications_student (student_id),
  KEY idx_applications_program (program_id),
  KEY idx_applications_status (application_status_id),
  KEY idx_applications_stage (application_stage_id),
  KEY idx_applications_university (university_id),
  KEY idx_applications_applied_at (applied_at)
);

CREATE TABLE application_events (
  event_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  event_data JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_application_events_app_created (application_id, created_at)
);

CREATE TABLE application_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  attachment_id INT UNSIGNED NOT NULL,
  required_for VARCHAR(100) NULL,
  submitted TINYINT(1) DEFAULT 0,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_application_attachments_app_required (application_id, required_for),
  KEY idx_application_attachments_attachment (attachment_id)
);

CREATE TABLE application_reviews (
  review_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED,
  review_status VARCHAR(50) NOT NULL,
  review_comment TEXT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_application_reviews_app (application_id),
  KEY idx_application_reviews_admin (admin_id)
);

-- 11. RECOMMENDERS & RECOMMENDATIONS
CREATE TABLE recommenders (
  recommender_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  relationship VARCHAR(255) NULL,
  organization VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  KEY idx_recommenders_student (student_id),
  KEY idx_recommenders_email (email)
);

CREATE TABLE recommendations (
  recommendation_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  recommender_id INT UNSIGNED NOT NULL,
  application_id INT UNSIGNED NULL,
  recommendation_text TEXT NULL,
  letter_attachment_id INT UNSIGNED NULL,
  submitted TINYINT(1) DEFAULT 0,
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (recommender_id) REFERENCES recommenders(recommender_id) ON DELETE CASCADE,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE SET NULL,
  FOREIGN KEY (letter_attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
  KEY idx_recommendations_recommender (recommender_id),
  KEY idx_recommendations_application (application_id)
);

-- 12. NOTIFICATIONS & RESOURCES
CREATE TABLE notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  notification_type_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NULL,
  message TEXT NULL,
  data JSON NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (notification_type_id) REFERENCES notification_types(notification_type_id) ON DELETE RESTRICT,
  KEY idx_notifications_user_read (user_id, is_read),
  KEY idx_notifications_type (notification_type_id)
);

CREATE TABLE resource_collections (
  collection_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_resource_collections_created_by (created_by)
);

CREATE TABLE resource_files (
  resource_file_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  collection_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NULL,
  attachment_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES resource_collections(collection_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  KEY idx_resource_files_collection (collection_id)
);

-- 13. FINANCE
CREATE TABLE finance_accounts (
  account_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  account_name VARCHAR(255) NULL,
  currency CHAR(3) DEFAULT 'USD',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_finance_accounts_user (user_id)
);

CREATE TABLE finance_categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  parent_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES finance_categories(category_id) ON DELETE SET NULL,
  UNIQUE KEY ux_finance_categories_parent_name (parent_id, name)
);

CREATE TABLE finance_transactions (
  transaction_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  finance_type_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'USD',
  exchange_rate DECIMAL(20,8) NULL,
  base_currency CHAR(3) DEFAULT 'USD',
  converted_amount DECIMAL(12,2) NULL,
  description TEXT NULL,
  transaction_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES finance_accounts(account_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES finance_categories(category_id) ON DELETE SET NULL,
  FOREIGN KEY (finance_type_id) REFERENCES finance_types(finance_type_id) ON DELETE RESTRICT,
  KEY idx_finance_transactions_account (account_id),
  KEY idx_finance_transactions_date (transaction_date),
  KEY idx_finance_transactions_type (finance_type_id)
);

CREATE TABLE budgets (
  budget_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  month_year DATE NOT NULL,
  planned_income DECIMAL(12,2) DEFAULT 0,
  planned_expense DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY ux_budgets_user_month (user_id, month_year),
  KEY idx_budgets_user (user_id),
  KEY idx_budgets_month (month_year)
);

CREATE TABLE application_fees (
  fee_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'USD',
  waiver_criteria JSON NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_application_fees_program (program_id)
);

CREATE TABLE fee_payments (
  payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'USD',
  payment_method VARCHAR(50) NULL,
  transaction_id VARCHAR(255) NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(50) DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_fee_payments_application (application_id),
  KEY idx_fee_payments_transaction (transaction_id)
);

-- 14. SCHOLARSHIPS
CREATE TABLE scholarships (
  scholarship_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) DEFAULT 'USD',
  criteria JSON NULL,
  deadline DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_scholarships_program (program_id)
);

CREATE TABLE student_scholarships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  scholarship_id INT UNSIGNED NOT NULL,
  applied_at DATETIME NULL,
  status ENUM('applied','under_review','awarded','rejected') DEFAULT 'applied',
  awarded_amount DECIMAL(12,2) NULL,
  awarded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  KEY idx_student_scholarships_student (student_id),
  KEY idx_student_scholarships_scholarship (scholarship_id)
);

-- 15. COUNSELOR & SESSIONS
CREATE TABLE counselor_assignments (
  assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  counselor_id INT UNSIGNED,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_by INT UNSIGNED NULL,
  ended_at DATETIME NULL,
  end_reason VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (counselor_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_counselor_assignments_student (student_id),
  KEY idx_counselor_assignments_counselor (counselor_id)
);

CREATE TABLE counseling_sessions (
  session_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  duration_minutes INT NULL,
  session_type ENUM('call','meeting','email','video') DEFAULT 'call',
  notes TEXT NULL,
  follow_up_tasks JSON NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES counselor_assignments(assignment_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_counseling_sessions_assignment (assignment_id),
  KEY idx_counseling_sessions_scheduled (scheduled_at)
);

-- 16. TODOS & MILESTONES
CREATE TABLE student_todos (
  todo_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  due_date DATE NULL,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  completed TINYINT(1) DEFAULT 0,
  completed_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_student_todos_student (student_id),
  KEY idx_student_todos_due (due_date)
);

CREATE TABLE student_milestones (
  milestone_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  milestone_type VARCHAR(100) NOT NULL,
  achieved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata JSON NULL,
  created_by INT UNSIGNED NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_student_milestones_student (student_id),
  KEY idx_student_milestones_type (milestone_type)
);

-- 17. UNIVERSITY COMMUNICATION & PROGRAM NOTES
CREATE TABLE university_communications (
  comm_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  university_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  contact_person VARCHAR(255) NULL,
  contact_email VARCHAR(255) NULL,
  contact_phone VARCHAR(50) NULL,
  last_contacted DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_university_communications_university (university_id),
  KEY idx_university_communications_admin (admin_id)
);

CREATE TABLE program_notes (
  note_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  program_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note_type ENUM('internal','public') DEFAULT 'internal',
  content TEXT NOT NULL,
  is_pinned TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_program_notes_program (program_id),
  KEY idx_program_notes_admin (admin_id)
);

-- 18. VALIDATION & GDPR & AUDIT
CREATE TABLE validation_rules (
  rule_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rule_type ENUM('application','student','university','finance','program') NOT NULL,
  field_name VARCHAR(255) NOT NULL,
  `condition` JSON NOT NULL,
  error_message VARCHAR(512) NOT NULL,
  severity ENUM('error','warning') DEFAULT 'error',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_validation_rules_type (rule_type),
  KEY idx_validation_rules_field (field_name)
);

CREATE TABLE data_consents (
  consent_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  consent_type ENUM('marketing','data_sharing','cookies','other') NOT NULL,
  granted TINYINT(1) NOT NULL DEFAULT 0,
  granted_at DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  version VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_data_consents_user (user_id),
  KEY idx_data_consents_type (consent_type)
);

CREATE TABLE data_deletion_requests (
  request_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  status ENUM('pending','in_progress','completed','rejected') DEFAULT 'pending',
  admin_notes TEXT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  KEY idx_data_deletion_user (user_id),
  KEY idx_data_deletion_status (status)
);

CREATE TABLE audit_logs (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  object_type VARCHAR(100) NULL,
  object_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action)
);

-- 19. VISA & IMMIGRATION
CREATE TABLE visa_types (
  visa_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  country_code CHAR(2) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  requirements JSON NULL,
  processing_time_days INT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_visa_types_country (country_code),
  KEY idx_visa_types_name (name)
);

CREATE TABLE embassy_consulate_records (
  embassy_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  country_code CHAR(2) NOT NULL,
  name VARCHAR(255) NOT NULL,
  city VARCHAR(100) NULL,
  address TEXT NULL,
  contact_person VARCHAR(255) NULL,
  contact_email VARCHAR(255) NULL,
  contact_phone VARCHAR(50) NULL,
  website VARCHAR(512) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE CASCADE,
  KEY idx_embassy_country (country_code),
  KEY idx_embassy_city (city)
);

CREATE TABLE student_visas (
  visa_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  visa_type_id INT UNSIGNED NOT NULL,
  program_id INT UNSIGNED NULL,
  application_date DATE NULL,
  status ENUM('not_started','applied','processing','approved','rejected','expired','withdrawn') NOT NULL DEFAULT 'not_started',
  approval_date DATE NULL,
  expiry_date DATE NULL,
  embassy_id INT UNSIGNED NULL,
  document_attachment_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (visa_type_id) REFERENCES visa_types(visa_type_id) ON DELETE RESTRICT,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
  FOREIGN KEY (embassy_id) REFERENCES embassy_consulate_records(embassy_id) ON DELETE SET NULL,
  FOREIGN KEY (document_attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_student_visas_student (student_id),
  KEY idx_student_visas_type (visa_type_id),
  KEY idx_student_visas_status (status),
  KEY idx_student_visas_approval (approval_date),
  KEY idx_student_visas_expiry (expiry_date)
);

CREATE TABLE visa_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visa_id INT UNSIGNED NOT NULL,
  attachment_id INT UNSIGNED NOT NULL,
  document_type VARCHAR(100) NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT UNSIGNED NULL,
  FOREIGN KEY (visa_id) REFERENCES student_visas(visa_id) ON DELETE CASCADE,
  FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_visa_documents_visa (visa_id),
  KEY idx_visa_documents_type (document_type)
);

CREATE TABLE visa_status_history (
  history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visa_id INT UNSIGNED NOT NULL,
  old_status ENUM('not_started','applied','processing','approved','rejected','expired','withdrawn') NULL,
  new_status ENUM('not_started','applied','processing','approved','rejected','expired','withdrawn') NOT NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note TEXT NULL,
  FOREIGN KEY (visa_id) REFERENCES student_visas(visa_id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_visa_status_history_visa_changed (visa_id, changed_at)
);

CREATE TABLE visa_fee_payments (
  payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visa_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'USD',
  payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method VARCHAR(100) NULL,
  transaction_id VARCHAR(255) NULL,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  recorded_by INT UNSIGNED NULL,
  FOREIGN KEY (visa_id) REFERENCES student_visas(visa_id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  KEY idx_visa_fee_payments_visa (visa_id),
  KEY idx_visa_fee_payments_tx (transaction_id)
);

-- 20. Other Tables
-- 1. Add scholarship_document_requirements table
CREATE TABLE scholarship_document_requirements (
    requirement_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    scholarship_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_mandatory TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    KEY idx_scholarship_doc_requirements (scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add attachment_verifications table for admin comments
CREATE TABLE attachment_verifications (
    verification_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    attachment_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    status ENUM('pending','verified','rejected','needs_correction') DEFAULT 'pending',
    admin_comment TEXT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
    KEY idx_attachment_verifications (attachment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add university_document_requirements table (if not exists in required_documents field)
-- Instead of storing in text field, create a proper table
CREATE TABLE university_document_requirements (
    requirement_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    university_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_mandatory TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
    KEY idx_university_doc_requirements (university_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add application_requirements_status table for admin comments
CREATE TABLE application_requirements_status (
    status_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    requirement_type VARCHAR(100) NOT NULL,
    requirement_id INT UNSIGNED NULL, -- can be university/program/scholarship requirement id
    admin_id INT UNSIGNED NULL,
    status ENUM('pending','submitted','verified','rejected','needs_correction') DEFAULT 'pending',
    admin_comment TEXT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL,
    KEY idx_app_requirements_status (application_id, requirement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jobs table to store job listings from various countries
CREATE TABLE jobs (
    job_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    country_code CHAR(2) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    company VARCHAR(255),
    website VARCHAR(512),
    job_type ENUM('full-time','part-time','internship','contract') DEFAULT 'part-time',
    sector VARCHAR(255), -- e.g., IT, Hospitality, Retail
    posted_date DATE,
    application_deadline DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    FOREIGN KEY (country_code) REFERENCES countries(iso2) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    KEY idx_jobs_country (country_code),
    KEY idx_jobs_sector (sector),
    KEY idx_jobs_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: track student job applications
CREATE TABLE job_applications (
    application_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('applied','interviewing','offered','rejected','withdrawn') DEFAULT 'applied',
    notes TEXT,
    FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    KEY idx_job_applications_job (job_id),
    KEY idx_job_applications_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource library for exam preparation (question sets, test papers, guides)
CREATE TABLE exam_resources (
    resource_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    test_type_id INT UNSIGNED NOT NULL, -- links to test_types (IELTS, SAT, etc.)
    title VARCHAR(255) NOT NULL,
    description TEXT,
    resource_type ENUM('question_set','test_paper','guide','video','other') NOT NULL,
    file_url VARCHAR(2048), -- if file is stored externally
    attachment_id INT UNSIGNED NULL, -- if file is stored in attachments
    is_free TINYINT(1) DEFAULT 1,
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'intermediate',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    FOREIGN KEY (test_type_id) REFERENCES test_types(test_type_id) ON DELETE CASCADE,
    FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    KEY idx_exam_resources_type (test_type_id, resource_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: track user progress (e.g., completed tests)
CREATE TABLE user_exam_attempts (
    attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NULL,
    time_taken INT NULL, -- in minutes
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES exam_resources(resource_id) ON DELETE CASCADE,
    KEY idx_user_exam_attempts_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    message_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    parent_id INT UNSIGNED NULL, -- for threading/replies
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_by_sender TINYINT(1) DEFAULT 0,
    deleted_by_receiver TINYINT(1) DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    KEY idx_messages_sender (sender_id, is_read),
    KEY idx_messages_receiver (receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_templates (
    template_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE, -- e.g., 'application_received', 'interview_scheduled'
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL, -- HTML or plain text with placeholders like {{user_name}}
    variables JSON, -- list of placeholders used
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE essays (
    essay_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT, -- could store plain text or HTML
    attachment_id INT UNSIGNED NULL, -- if submitted as file
    status ENUM('draft','submitted','under_review','reviewed') DEFAULT 'draft',
    submitted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (attachment_id) REFERENCES attachments(attachment_id) ON DELETE SET NULL,
    KEY idx_essays_student (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE essay_feedback (
    feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    essay_id INT UNSIGNED NOT NULL,
    counselor_id INT UNSIGNED NOT NULL,
    feedback TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (essay_id) REFERENCES essays(essay_id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    KEY idx_essay_feedback_essay (essay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Define milestone types (optional, for better organization)
CREATE TABLE milestone_definitions (
    milestone_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'profile_completed', 'first_application'
    name VARCHAR(100) NOT NULL,
    description TEXT,
    badge_icon VARCHAR(255) NULL,
    points INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Final Views
DROP VIEW IF EXISTS admin_user_view;
CREATE VIEW admin_user_view AS
SELECT u.user_id, u.username, u.email, r.name AS role_name, u.created_at, u.last_login_at, u.is_verified
FROM users u
LEFT JOIN roles r ON u.default_role_id = r.role_id;

DROP VIEW IF EXISTS application_funnel;
CREATE VIEW application_funnel AS
SELECT s.order_index, s.name AS stage_name, COUNT(a.application_id) AS applications_count
FROM application_stages s
LEFT JOIN applications a ON a.application_stage_id = s.application_stage_id
GROUP BY s.order_index, s.name
ORDER BY s.order_index;

DROP VIEW IF EXISTS program_popularity;
CREATE VIEW program_popularity AS
SELECT p.program_id, p.program_name, u.name AS university_name,
  COUNT(a.application_id) AS total_applications,
  SUM(CASE WHEN as_.code = 'accepted' THEN 1 ELSE 0 END) AS total_accepted
FROM programs p
JOIN universities u ON u.university_id = p.university_id
LEFT JOIN applications a ON a.program_id = p.program_id
LEFT JOIN application_statuses as_ ON a.application_status_id = as_.application_status_id
GROUP BY p.program_id, p.program_name, u.name;

DROP VIEW IF EXISTS counselor_performance;
CREATE VIEW counselor_performance AS
SELECT ca.counselor_id, u.username AS counselor_username,
  COUNT(DISTINCT ca.student_id) AS total_students,
  SUM(CASE WHEN a.application_id IS NOT NULL THEN 1 ELSE 0 END) AS applications_managed
FROM counselor_assignments ca
LEFT JOIN users u ON u.user_id = ca.counselor_id
LEFT JOIN applications a ON a.student_id = ca.student_id
GROUP BY ca.counselor_id, u.username;

-- 21. Indexes (extra)
CREATE INDEX idx_applications_student_status ON applications(student_id, application_status_id);
CREATE INDEX idx_attachments_on_uploader ON attachments(uploader_user_id);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read);
CREATE INDEX idx_programs_university_deadline ON programs(university_id, application_deadline);
