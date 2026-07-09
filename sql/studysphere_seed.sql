USE studysphere;

INSERT INTO degree_levels (code, name) VALUES
('UG','Undergraduate'),('GRAD','Graduate'),('PHD','PhD'),('DIP','Diploma'),('CERT','Certificate'),('OTHER','Other');

INSERT INTO test_types (code, name) VALUES
('IELTS','IELTS'),('TOEFL','TOEFL'),('GRE','GRE'),('GMAT','GMAT'),('SAT','SAT'),('PTE','PTE'),('ACT','ACT'),('DUOLINGO','Duolingo');

INSERT INTO achievement_types (code, name) VALUES
('project','Project'),('thesis','Thesis'),('research','Research'),('award','Award'),('internship','Internship'),('job','Job'),('co_curricular','Co-curricular'),('volunteer','Volunteer');

INSERT INTO application_statuses (code, name) VALUES
('draft','Draft'),('submitted','Submitted'),('under_review','Under Review'),('interview','Interview'),('accepted','Accepted'),('rejected','Rejected'),('withdrawn','Withdrawn'),('enrolled','Enrolled');

INSERT INTO application_stages (name, order_index, entry_status_id, exit_status_id, is_decision_point) VALUES
('initial_review',1,(SELECT application_status_id FROM application_statuses WHERE code='draft'),(SELECT application_status_id FROM application_statuses WHERE code='under_review'),0),
('documents_check',2,(SELECT application_status_id FROM application_statuses WHERE code='under_review'),(SELECT application_status_id FROM application_statuses WHERE code='under_review'),0),
('interview',3,(SELECT application_status_id FROM application_statuses WHERE code='under_review'),(SELECT application_status_id FROM application_statuses WHERE code='interview'),0),
('decision',4,(SELECT application_status_id FROM application_statuses WHERE code='interview'),(SELECT application_status_id FROM application_statuses WHERE code='accepted'),1);

INSERT INTO finance_types (code, name) VALUES ('income','Income'),('expense','Expense'),('transfer','Transfer');
INSERT INTO notification_types (code, name) VALUES ('news','News'),('application','Application'),('account','Account'),('scholarship','Scholarship'),('job','Job');

INSERT INTO roles (name, description) VALUES
('student','Student user'),('admin','System administrator'),('counselor','Counselor / Advisor'),('super_admin','Full access');

INSERT INTO permissions (code, description) VALUES
('user:read','Read user data'),('user:write','Manage users'),
('application:read','Read applications'),('application:write','Create & modify applications'),('application:approve','Approve/reject applications'),
('program:manage','Manage programs'),('finance:manage','Manage finance data'),('system:manage','Full system control');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p WHERE r.name = 'super_admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p ON p.code IN ('user:read','user:write','application:read','application:write','program:manage','finance:manage') WHERE r.name = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p ON p.code IN ('application:read','application:approve') WHERE r.name = 'counselor';

INSERT INTO countries (iso2, iso3, name, region, phone_code) VALUES
('US','USA','United States','Americas','+1'),
('CA','CAN','Canada','Americas','+1'),
('GB','GBR','United Kingdom','Europe','+44'),
('AU','AUS','Australia','Oceania','+61'),
('DE','DEU','Germany','Europe','+49'),
('FR','FRA','France','Europe','+33'),
('IN','IND','India','Asia','+91'),
('CN','CHN','China','Asia','+86'),
('JP','JPN','Japan','Asia','+81'),
('BD','BGD','Bangladesh','Asia','+880');

INSERT INTO tags (name) VALUES ('STEM'),('Business'),('Engineering'),('Medical'),('Fully Funded'),('Low Tuition'),('Online Available'),('Research Focused'),('Co-op Available');

INSERT INTO users (username, email, password_hash, default_role_id, is_verified, created_at)
VALUES
('superadmin','admin@studysphere.local','$2y$10$REPLACE_WITH_HASH_SUPERADMIN', (SELECT role_id FROM roles WHERE name='super_admin'), 1, NOW()),
('demo_user','demo@student.com','$2y$10$REPLACE_WITH_HASH_DEMO', (SELECT role_id FROM roles WHERE name='student'), 1, NOW());

INSERT INTO universities (name, country_code, city, website, qs_ranking, approx_tuition, student_count, description, created_at)
VALUES
('University of Example','US','Example City','https://www.example.edu',50,30000,20000,'Demo University',NOW()),
('Global Institute of Tech','GB','London','https://www.gitlondon.ac.uk',120,28000,15000,'Tech focused',NOW());

INSERT INTO programs (university_id, program_name, degree_level_id, department, duration_months, tuition_fee, currency, intake_months, created_at)
VALUES
((SELECT university_id FROM universities WHERE name='University of Example'),'MSc Computer Science',(SELECT degree_level_id FROM degree_levels WHERE code='GRAD'),'Computer Science',24,30000,'USD','Fall',NOW()),
((SELECT university_id FROM universities WHERE name='Global Institute of Tech'),'BSc Engineering',(SELECT degree_level_id FROM degree_levels WHERE code='UG'),'Engineering',48,20000,'GBP','Fall',NOW());

INSERT INTO students (user_id, degree_level_id, country_from, budget_min, budget_max, subject_area_id, part_time_needed, scholarship_needed, created_at)
VALUES ((SELECT user_id FROM users WHERE username='demo_user'), (SELECT degree_level_id FROM degree_levels WHERE code='GRAD'), 'IN', 5000, 40000, NULL, 1, 1, NOW());

INSERT INTO education_records (student_id, level, institution_name, country_code, degree_name, major_id, gpa, start_date, end_date, created_at)
VALUES ((SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')), 'UG', 'Demo College', 'IN', 'BSc Computer Science', NULL, 3.6, '2016-08-01', '2020-05-15', NOW());

INSERT INTO attachments (uploader_user_id, filename, file_url, file_type, file_size, checksum, created_at)
VALUES ((SELECT user_id FROM users WHERE username='demo_user'),'transcript.pdf','/files/demo/transcript.pdf','application/pdf',123456,'abc123',NOW());

INSERT INTO education_documents (education_record_id, attachment_id, created_at)
VALUES (
  (SELECT record_id FROM education_records WHERE student_id=(SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user'))),
  (SELECT attachment_id FROM attachments WHERE filename='transcript.pdf'),
  NOW()
);

INSERT INTO test_scores (student_id, test_type_id, test_date, valid_until, created_at)
VALUES (
  (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')),
  (SELECT test_type_id FROM test_types WHERE code='IELTS'),
  '2024-03-10','2026-03-10',NOW()
);

INSERT INTO test_score_documents (score_id, attachment_id, created_at)
VALUES (
  (SELECT score_id FROM test_scores WHERE student_id=(SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')) LIMIT 1),
  (SELECT attachment_id FROM attachments WHERE filename='transcript.pdf' LIMIT 1),
  NOW()
);

INSERT INTO test_score_sections (score_id, section_name, score_value, max_score, percentile, created_at)
VALUES (
  (SELECT score_id FROM test_scores WHERE student_id=(SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')) LIMIT 1),
  'Reading', 8.5, 9.0, NULL, NOW()
);

INSERT INTO achievements (student_id, achievement_type_id, title, description, organization, start_date, end_date, created_at)
VALUES (
  (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')),
  (SELECT achievement_type_id FROM achievement_types WHERE code='project'),
  'Demo Project', 'A sample project entry', 'Demo Org', '2019-01-01', '2019-06-01', NOW()
);

INSERT INTO achievement_documents (achievement_id, attachment_id, created_at)
VALUES (
  (SELECT achievement_id FROM achievements WHERE student_id=(SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')) LIMIT 1),
  (SELECT attachment_id FROM attachments WHERE filename='transcript.pdf' LIMIT 1),
  NOW()
);

INSERT INTO applications (student_id, program_id, university_id, application_status_id, application_stage_id, applied_at, created_by)
VALUES (
 (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')),
 (SELECT program_id FROM programs WHERE program_name='MSc Computer Science'),
 (SELECT university_id FROM universities WHERE name='University of Example'),
 (SELECT application_status_id FROM application_statuses WHERE code='draft'),
 (SELECT application_stage_id FROM application_stages WHERE name='initial_review'),
 NOW(),
 (SELECT user_id FROM users WHERE username='demo_user')
);

INSERT INTO scholarships (program_id, name, amount, currency, criteria, deadline, created_at)
VALUES ((SELECT program_id FROM programs WHERE program_name='MSc Computer Science'), 'Merit Scholarship', 5000, 'USD', JSON_OBJECT('min_gpa',3.5), '2025-06-01', NOW());

INSERT INTO application_fees (program_id, amount, currency, waiver_criteria, created_at)
VALUES ((SELECT program_id FROM programs WHERE program_name='MSc Computer Science'), 75.00, 'USD', JSON_ARRAY('low_income'), NOW());

INSERT INTO visa_types (country_code, name, description, requirements, processing_time_days, created_by, created_at)
VALUES
('US','F-1 Student Visa','US academic student visa', JSON_OBJECT('min_gpa',3.0,'required_documents', JSON_ARRAY('passport','transcript','financial_statement')),30, (SELECT user_id FROM users WHERE username='superadmin'), NOW()),
('GB','Student Visa (Tier 4)','UK student visa', JSON_OBJECT('min_gpa',3.0,'required_documents', JSON_ARRAY('passport','cas_letter','financial_statement')),28, (SELECT user_id FROM users WHERE username='superadmin'), NOW());

INSERT INTO embassy_consulate_records (country_code, name, city, address, contact_email, contact_phone, created_at)
VALUES ('US','US Embassy Example','Example City','123 Embassy Rd','visa@example.gov','+1-555-0000',NOW());

INSERT INTO student_visas (student_id, visa_type_id, program_id, application_date, status, created_by, created_at)
VALUES (
 (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='demo_user')),
 (SELECT visa_type_id FROM visa_types WHERE name LIKE '%Student%Visa%' LIMIT 1),
 (SELECT program_id FROM programs WHERE program_name='MSc Computer Science'),
 CURDATE(),'applied',(SELECT user_id FROM users WHERE username='demo_user'),NOW()
);
-- Add description and required_documents columns to scholarships table
ALTER TABLE scholarships 
ADD COLUMN description TEXT NULL AFTER criteria,
ADD COLUMN required_documents TEXT NULL AFTER description;

-- Add some other missing columns that might be needed based on your code
ALTER TABLE universities 
ADD COLUMN required_documents TEXT NULL AFTER description;

-- Optional: Add columns to other tables if needed
ALTER TABLE programs 
ADD COLUMN description TEXT NULL AFTER department;
ALTER TABLE applications 
ADD COLUMN notes TEXT NULL AFTER application_stage_id;


ALTER TABLE student_todos 
ADD COLUMN category VARCHAR(50) NULL AFTER priority,
ADD COLUMN linked_application INT UNSIGNED NULL AFTER category,
ADD CONSTRAINT fk_todos_linked_application 
    FOREIGN KEY (linked_application) 
    REFERENCES applications(application_id) 
    ON DELETE SET NULL;

  
ALTER TABLE counseling_sessions 
ADD COLUMN calendar_event_id VARCHAR(255) NULL AFTER notes, -- ID from Google Calendar/Outlook
ADD COLUMN meeting_link VARCHAR(512) NULL AFTER calendar_event_id,
ADD COLUMN is_booked TINYINT(1) DEFAULT 0 AFTER session_type,
ADD COLUMN booked_by_student_id INT UNSIGNED NULL AFTER is_booked,
ADD FOREIGN KEY (booked_by_student_id) REFERENCES students(student_id) ON DELETE SET NULL;

ALTER TABLE student_milestones 
ADD COLUMN milestone_def_id INT UNSIGNED NULL AFTER milestone_type,
ADD FOREIGN KEY (milestone_def_id) REFERENCES milestone_definitions(milestone_id) ON DELETE SET NULL;

ALTER TABLE fee_payments 
ADD COLUMN gateway_data JSON NULL AFTER transaction_id;

ALTER TABLE attachments 
ADD COLUMN category VARCHAR(100) NULL AFTER file_type;

ALTER TABLE student_scholarships MODIFY status ENUM('applied','under_review','awarded','rejected','withdrawn') DEFAULT 'applied';

ALTER TABLE students ADD COLUMN visa_checklist JSON NOT NULL DEFAULT (JSON_ARRAY()) AFTER updated_at;

INSERT IGNORE INTO finance_categories (name) VALUES 
('Tuition'), ('Books'), ('Housing'), ('Food'), ('Transportation'), ('Entertainment'), ('Other');

INSERT INTO jobs (country_code, title, description, company, website, job_type, sector, posted_date, is_active) VALUES
('US', 'Student Assistant', 'Help in library', 'University Library', 'https://library.example.edu', 'part-time', 'Education', CURDATE(), 1),
('GB', 'Barista', 'Coffee shop', 'Costa Coffee', 'https://costa.co.uk', 'part-time', 'Hospitality', CURDATE(), 1),
('CA', 'Retail Associate', 'Weekend shifts', 'Walmart', 'https://walmart.ca', 'part-time', 'Retail', CURDATE(), 1);

INSERT INTO milestone_definitions (code, name, description, badge_icon, points) VALUES
('profile_completed', 'Profile Completed', 'Fill in all personal details in your profile', 'fa-user-check', 10),
('first_application', 'First Application', 'Start your first university application', 'fa-file-alt', 20),
('first_document', 'First Document Upload', 'Upload your first document', 'fa-upload', 5),
('application_submitted', 'Application Submitted', 'Submit an application for review', 'fa-paper-plane', 30),
('first_scholarship', 'Scholarship Applicant', 'Apply for your first scholarship', 'fa-award', 15),
('test_score_added', 'Test Score Added', 'Add a standardized test score', 'fa-chart-line', 10),
('education_added', 'Education Record Added', 'Add your academic history', 'fa-graduation-cap', 10),
('recommender_added', 'Recommender Added', 'Add a recommender', 'fa-user-plus', 5),
('recommendation_received', 'Recommendation Received', 'Receive your first recommendation letter', 'fa-envelope-open-text', 25),
('visa_applied', 'Visa Application Started', 'Start your visa application', 'fa-passport', 15),
('job_applied', 'First Job Application', 'Apply for a part-time job', 'fa-briefcase', 10),
('essay_submitted', 'Essay Submitted', 'Submit an essay for review', 'fa-pen', 10),
('session_booked', 'First Counselling Session', 'Book a counselling session', 'fa-calendar-check', 10);

INSERT INTO email_templates (name, subject, body, variables, is_active) VALUES
('application_submitted', 'Application Submitted Successfully', 
 '<h2>Dear {{student_name}},</h2><p>Your application for <strong>{{program_name}}</strong> at <strong>{{university_name}}</strong> has been submitted successfully.</p><p>Application ID: {{application_id}}</p><p>You can track your application status by logging into your dashboard.</p><p>Thank you,<br>{{site_name}}</p>',
 '["student_name","program_name","university_name","application_id"]', 1),

('application_status_changed', 'Your Application Status Has Changed',
 '<h2>Dear {{student_name}},</h2><p>The status of your application for <strong>{{program_name}}</strong> at <strong>{{university_name}}</strong> has been updated to <strong>{{status}}</strong>.</p><p>Reason: {{status_reason}}</p><p>Login to your dashboard to view more details.</p><p>Regards,<br>{{site_name}}</p>',
 '["student_name","program_name","university_name","application_id","status","status_reason"]', 1),

('payment_received', 'Payment Confirmation',
 '<h2>Dear {{student_name}},</h2><p>We have received your payment of <strong>{{amount}}</strong> for the application to <strong>{{program_name}}</strong> at <strong>{{university_name}}</strong>.</p><p>Transaction ID: {{transaction_id}}</p><p>Thank you for using {{site_name}}!</p>',
 '["student_name","program_name","university_name","application_id","amount","transaction_id"]', 1),

('new_message', 'You Have a New Message',
 '<h2>Dear {{recipient_name}},</h2><p>You have received a new message from {{sender_name}} on {{site_name}}.</p><p>Subject: {{subject}}</p><p>Login to your dashboard to read and reply.</p>',
 '["recipient_name","sender_name","subject"]', 1);