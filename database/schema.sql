-- ============================================================
-- UIU Friends Loan & Crowdfunding — Database Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS uiu_friends CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uiu_friends;

-- ---------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------
CREATE TABLE users (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    full_name               VARCHAR(120)  NOT NULL,
    email                   VARCHAR(150)  NOT NULL UNIQUE,
    password_hash           VARCHAR(255)  NOT NULL,
    student_id              VARCHAR(30)   NOT NULL UNIQUE,
    department              VARCHAR(20)   NOT NULL,
    batch                   VARCHAR(10)   NOT NULL,
    phone                   VARCHAR(20)   DEFAULT NULL,
    avatar                  VARCHAR(255)  DEFAULT 'assets/img/default-avatar.svg',
    role                    ENUM('user','admin') NOT NULL DEFAULT 'user',
    trust_score             DECIMAL(3,2)  NOT NULL DEFAULT 4.00,
    uiu_id_verified         TINYINT(1)    NOT NULL DEFAULT 0,
    email_verified          TINYINT(1)    NOT NULL DEFAULT 0,
    phone_verified          TINYINT(1)    NOT NULL DEFAULT 0,
    payment_linked          TINYINT(1)    NOT NULL DEFAULT 0,
    agreement_accepted      TINYINT(1)    NOT NULL DEFAULT 0,
    -- National ID (NID) — required before borrowing / lending / organising a campaign
    nid_number              VARCHAR(20)   DEFAULT NULL UNIQUE,
    date_of_birth           DATE          DEFAULT NULL,
    address                 VARCHAR(255)  DEFAULT NULL,
    nid_front               VARCHAR(255)  DEFAULT NULL,
    nid_back                VARCHAR(255)  DEFAULT NULL,
    nid_status              ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted',
    nid_reject_reason       VARCHAR(255)  DEFAULT NULL,
    nid_submitted_at        DATETIME      DEFAULT NULL,
    status                  ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- LOAN POSTS
-- ---------------------------------------------------------------
CREATE TABLE loan_posts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    purpose             VARCHAR(100)  NOT NULL,
    description         TEXT          NOT NULL,
    emi_ok              TINYINT(1)    NOT NULL DEFAULT 0,
    repay_period_text   VARCHAR(60)   NOT NULL,      -- e.g. "Repay in 2 months"
    status              ENUM('open','funded','closed') NOT NULL DEFAULT 'open',
    admin_status        ENUM('pending','approved','removed') NOT NULL DEFAULT 'approved',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- LOAN OFFERS  (a lender's proposal on a loan post)
-- ---------------------------------------------------------------
CREATE TABLE loan_offers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    loan_post_id    INT NOT NULL,
    lender_id       INT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    interest_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    tenure_months   INT NOT NULL,
    status          ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_post_id) REFERENCES loan_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- AGREEMENTS  (created once an offer is accepted)
-- ---------------------------------------------------------------
CREATE TABLE agreements (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    loan_post_id        INT NOT NULL,
    offer_id            INT NOT NULL,
    borrower_id         INT NOT NULL,
    lender_id           INT NOT NULL,
    principal           DECIMAL(12,2) NOT NULL,
    interest_rate       DECIMAL(5,2)  NOT NULL,
    tenure_months       INT NOT NULL,
    total_payable       DECIMAL(12,2) NOT NULL,
    repayment_plan      ENUM('emi','full') DEFAULT NULL,
    borrower_signed     TINYINT(1) NOT NULL DEFAULT 0,
    lender_signed       TINYINT(1) NOT NULL DEFAULT 0,
    status              ENUM('pending_confirmation','active','completed','disputed') NOT NULL DEFAULT 'pending_confirmation',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_post_id) REFERENCES loan_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (offer_id) REFERENCES loan_offers(id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- INSTALLMENTS  (auto generated schedule per agreement)
-- ---------------------------------------------------------------
CREATE TABLE installments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    agreement_id    INT NOT NULL,
    installment_no  INT NOT NULL,
    due_date        DATE NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    status          ENUM('due','paid','late') NOT NULL DEFAULT 'due',
    FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- TRANSACTIONS  (actual payments made against installments)
-- ---------------------------------------------------------------
CREATE TABLE transactions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    agreement_id        INT NOT NULL,
    installment_id      INT DEFAULT NULL,
    txn_ref             VARCHAR(40) NOT NULL UNIQUE,
    payer_id            INT NOT NULL,
    payee_id            INT NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    gateway_fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method      ENUM('bkash','nagad','card','bank') NOT NULL,
    status              ENUM('success','pending','failed') NOT NULL DEFAULT 'success',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE CASCADE,
    FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE SET NULL,
    FOREIGN KEY (payer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- CROWDFUNDING CAMPAIGNS
-- ---------------------------------------------------------------
CREATE TABLE campaigns (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id    INT NOT NULL,
    title           VARCHAR(150) NOT NULL,
    description     TEXT NOT NULL,
    goal_amount     DECIMAL(12,2) NOT NULL,
    raised_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    image           VARCHAR(255) DEFAULT NULL,
    days_left       INT NOT NULL DEFAULT 30,
    id_verified     TINYINT(1) NOT NULL DEFAULT 0,
    docs_verified   TINYINT(1) NOT NULL DEFAULT 0,
    admin_status    ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    -- credentials the organiser provides so donors can trust the campaign
    category            VARCHAR(40)  NOT NULL DEFAULT 'Other',
    beneficiary_name    VARCHAR(120) DEFAULT NULL,
    beneficiary_relation VARCHAR(60) DEFAULT NULL,
    contact_phone       VARCHAR(20)  DEFAULT NULL,
    receive_method      ENUM('bkash','nagad','bank','hospital','other') DEFAULT NULL,
    receive_account     VARCHAR(150) DEFAULT NULL,
    admin_note          VARCHAR(255) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- CAMPAIGN DOCUMENTS  (credentials uploaded to prove a campaign is genuine)
-- ---------------------------------------------------------------
CREATE TABLE campaign_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id     INT NOT NULL,
    doc_type        VARCHAR(40)  NOT NULL,
    file_path       VARCHAR(255) DEFAULT NULL,
    original_name   VARCHAR(255) DEFAULT NULL,
    status          ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    admin_note      VARCHAR(255) DEFAULT NULL,
    uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- DONATIONS
-- ---------------------------------------------------------------
CREATE TABLE donations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id     INT NOT NULL,
    donor_id        INT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    anonymous       TINYINT(1) NOT NULL DEFAULT 0,
    txn_ref         VARCHAR(40) NOT NULL UNIQUE,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- MESSAGES  (simple threaded chat between two users)
-- ---------------------------------------------------------------
CREATE TABLE messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sender_id       INT NOT NULL,
    receiver_id     INT NOT NULL,
    loan_post_id    INT DEFAULT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    reported        TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_post_id) REFERENCES loan_posts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- REVIEWS
-- ---------------------------------------------------------------
CREATE TABLE reviews (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reviewee_id     INT NOT NULL,
    reviewer_id     INT NOT NULL,
    agreement_id    INT DEFAULT NULL,
    rating          TINYINT NOT NULL,
    comment         VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- DISPUTE / REPORT LOG (feeds the Admin "Reports" screen)
-- ---------------------------------------------------------------
CREATE TABLE reports (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id       INT NOT NULL,
    reported_user_id  INT DEFAULT NULL,
    category          VARCHAR(40)  NOT NULL DEFAULT 'other',
    reason            VARCHAR(255) NOT NULL,              -- short subject line
    details           TEXT DEFAULT NULL,                  -- full description of the problem
    related_type      ENUM('loan_post','campaign','agreement','chat','user','other') NOT NULL DEFAULT 'other',
    related_id        INT DEFAULT NULL,
    agreement_id      INT DEFAULT NULL,
    evidence_file     VARCHAR(255) DEFAULT NULL,
    status            ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    admin_note        TEXT DEFAULT NULL,                  -- admin response shown to the reporter
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================================================================
-- SEED DATA
-- ================================================================

-- Password for ALL seed users (incl. admin) is:  password123
-- Hash generated with PHP password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO users (full_name,email,password_hash,student_id,department,batch,phone,avatar,role,trust_score,uiu_id_verified,email_verified,phone_verified,payment_linked,agreement_accepted) VALUES
('Rafi Ahmed','rafi@bscse.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112021xxx','CSE','2021','01700000001','https://i.pravatar.cc/150?img=12','user',4.80,1,1,1,1,1),
('Nadia Rahman','nadia@bscee.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112020xxx','EEE','2020','01700000002','https://i.pravatar.cc/150?img=45','user',4.90,1,1,1,1,1),
('Imran Hossain','imran@bba.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0212022xxx','BBA','2022','01700000003','https://i.pravatar.cc/150?img=33','user',4.60,1,1,1,0,1),
('Tahmina Akter','tahmina@slm.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0312021xxx','SLM','2021','01700000004','https://i.pravatar.cc/150?img=47','user',4.70,1,1,1,1,1),
('Mahmud Hasan','mahmud@bscse.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112019xxx','CSE','2019','01700000005','https://i.pravatar.cc/150?img=14','user',4.90,1,1,1,1,1),
('Farhana Islam','farhana@bscee.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112020xxy','EEE','2020','01700000006','https://i.pravatar.cc/150?img=48','user',4.80,1,1,1,1,1),
('Arif Ullah','arif@bba.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0212021xxx','BBA','2021','01700000007','https://i.pravatar.cc/150?img=51','user',4.70,1,1,1,1,1),
('Rashed Ahmed','rashed@bscse.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112019xxy','CSE','2019','01700000008','https://i.pravatar.cc/150?img=15','user',4.50,1,1,1,1,1),
('Sara Akter','sara@bscee.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112021xxy','EEE','2021','01700000009','https://i.pravatar.cc/150?img=44','user',4.60,1,1,1,1,1),
('Admin User','admin@uiufriends.com','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','ADMIN0001','ADMIN','2024','01700000000','https://i.pravatar.cc/150?img=68','admin',5.00,1,1,1,1,1);

INSERT INTO loan_posts (user_id,amount,purpose,description,emi_ok,repay_period_text,status,admin_status,created_at) VALUES
(2, 15000, 'Tuition Fee', 'Need a short-term loan to cover my semester tuition installment before the deadline on June 30. I have a partial scholarship and can repay via EMI or full settlement within the agreed period. Willing to sign a formal digital agreement for both parties\' protection.', 1, 'Repay in 2-6 months', 'open', 'approved', NOW() - INTERVAL 2 HOUR),
(3, 8500, 'Textbooks & Lab Equipment', 'Looking for a small loan to buy textbooks and lab equipment for my final year project. Repayment in full within one month.', 0, 'Due in 1 month', 'open', 'approved', NOW() - INTERVAL 5 HOUR),
(4, 22000, 'Conference Travel', 'Covering travel expenses for a conference presentation in Dhaka this weekend. Open to EMI over 3 months.', 1, 'Repay in 3 months', 'open', 'approved', NOW() - INTERVAL 1 DAY);

INSERT INTO loan_offers (loan_post_id,lender_id,amount,interest_rate,tenure_months,status,created_at) VALUES
(1,5,15000,5.00,3,'accepted', NOW() - INTERVAL 1 HOUR),
(1,6,15000,0.00,2,'pending', NOW() - INTERVAL 50 MINUTE),
(1,7,15000,4.00,6,'pending', NOW() - INTERVAL 40 MINUTE);

INSERT INTO agreements (loan_post_id,offer_id,borrower_id,lender_id,principal,interest_rate,tenure_months,total_payable,repayment_plan,borrower_signed,lender_signed,status,created_at) VALUES
(1,1,2,5,15000,5.00,3,15750,'emi',1,1,'active', NOW() - INTERVAL 45 MINUTE);

INSERT INTO installments (agreement_id,installment_no,due_date,amount,status) VALUES
(1,1,'2024-07-30',5250,'paid'),
(1,2,'2024-08-30',5250,'due'),
(1,3,'2024-09-30',5250,'due');

INSERT INTO transactions (agreement_id,installment_id,txn_ref,payer_id,payee_id,amount,gateway_fee,payment_method,status,created_at) VALUES
(1,1,'UIUF-TXN-2024-88213',2,5,5250,15,'bkash','success', '2024-06-30 10:42:00');

INSERT INTO campaigns (organizer_id,title,description,goal_amount,raised_amount,image,days_left,id_verified,docs_verified,admin_status,created_at) VALUES
(8,'Emergency Surgery Fund for Tanvir Bhai','Tanvir, a 3rd year CSE student, urgently needs surgery after a road accident. His family has covered part of the cost, but ৳64,000 more is needed for the operation and recovery. All medical documents have been verified by our admin team. Every taka raised goes directly to the hospital account — 100% transparent.',200000,136000,'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=900&q=60',12,1,1,'verified', NOW() - INTERVAL 6 DAY),
(9,'New Library Books for EEE Dept','Help us stock the EEE department library with updated textbooks and reference materials for juniors.',80000,24000,'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=900&q=60',20,1,0,'pending', NOW() - INTERVAL 3 DAY);

INSERT INTO donations (campaign_id,donor_id,amount,anonymous,txn_ref,created_at) VALUES
(1,6,2000,0,'TXN-88190','2024-06-28 09:00:00'),
(1,2,500,1,'TXN-88185','2024-06-28 08:00:00'),
(1,7,5000,0,'TXN-88142','2024-06-27 14:00:00'),
(1,9,1500,0,'TXN-88090','2024-06-26 11:00:00');

INSERT INTO reviews (reviewee_id,reviewer_id,rating,comment,created_at) VALUES
(2,5,5,'Nadia repaid every installment on time and communicated clearly throughout. Highly trustworthy borrower.','2024-07-02'),
(2,6,5,'Smooth process, signed the agreement quickly and paid back in full.','2024-04-05');

INSERT INTO messages (sender_id,receiver_id,loan_post_id,body,is_read,created_at) VALUES
(5,2,1,'Hi Nadia, I saw your loan post for ৳15,000. I can lend the full amount at 5% interest over 3 months.',1, NOW() - INTERVAL 40 MINUTE),
(2,5,1,'That works for me! Can we do EMI in 3 equal installments?',1, NOW() - INTERVAL 35 MINUTE),
(5,2,1,'Sure, I can offer 5% interest over 3 months. I\'ve sent the formal offer — please confirm it on the post page so we can move to the agreement.',0, NOW() - INTERVAL 2 MINUTE);

-- ================================================================
-- SEED: NID data  (demo numbers only — NOT real NIDs)
-- ================================================================
UPDATE users SET nid_number='1985010001', date_of_birth='2000-03-14', address='House 12, Road 4, Badda, Dhaka',        nid_status='verified', nid_submitted_at=NOW() WHERE email='rafi@bscse.uiu.ac.bd';
UPDATE users SET nid_number='1985010002', date_of_birth='1999-07-21', address='Flat 3B, Mirpur-10, Dhaka',             nid_status='verified', nid_submitted_at=NOW() WHERE email='nadia@bscee.uiu.ac.bd';
UPDATE users SET nid_number='1985010003', date_of_birth='2001-01-09', address='Village Rampur, Comilla Sadar, Comilla', nid_status='verified', nid_submitted_at=NOW() WHERE email='imran@bba.uiu.ac.bd';
UPDATE users SET nid_number='1985010004', date_of_birth='2000-11-30', address='Uttara Sector 7, Dhaka',               nid_status='verified', nid_submitted_at=NOW() WHERE email='tahmina@slm.uiu.ac.bd';
UPDATE users SET nid_number='1985010005', date_of_birth='1998-05-02', address='Dhanmondi 27, Dhaka',                   nid_status='verified', nid_submitted_at=NOW() WHERE email='mahmud@bscse.uiu.ac.bd';
UPDATE users SET nid_number='1985010006', date_of_birth='1999-09-18', address='Sylhet Zindabazar, Sylhet',             nid_status='verified', nid_submitted_at=NOW() WHERE email='farhana@bscee.uiu.ac.bd';
UPDATE users SET nid_number='1985010007', date_of_birth='2000-02-25', address='Chattogram Agrabad, Chattogram',        nid_status='verified', nid_submitted_at=NOW() WHERE email='arif@bba.uiu.ac.bd';
UPDATE users SET nid_number='1985010008', date_of_birth='1998-12-11', address='Rajshahi Boalia, Rajshahi',             nid_status='verified', nid_submitted_at=NOW() WHERE email='rashed@bscse.uiu.ac.bd';
UPDATE users SET nid_number='1985010009', date_of_birth='2000-06-06', address='Khulna Sonadanga, Khulna',              nid_status='verified', nid_submitted_at=NOW() WHERE email='sara@bscee.uiu.ac.bd';

-- A new student whose NID is waiting for admin review (shows the verification queue)
INSERT INTO users (full_name,email,password_hash,student_id,department,batch,phone,avatar,role,trust_score,uiu_id_verified,email_verified,phone_verified,payment_linked,agreement_accepted,nid_number,date_of_birth,address,nid_status,nid_submitted_at) VALUES
('Karim Chowdhury','karim@bscse.uiu.ac.bd','$2y$10$ufrE.muzBj3mW4.6PTy1FObz.aSsR9OqCfe6OtHT344kHiAJuugOe','0112022xxx','CSE','2022','01700000011','https://i.pravatar.cc/150?img=59','user',4.00,0,1,0,0,1,'1985010011','2001-04-04','Mohammadpur, Dhaka','pending',NOW());

-- ================================================================
-- SEED: campaign credentials + documents
-- ================================================================
UPDATE campaigns SET category='Medical', beneficiary_name='Tanvir Hossain', beneficiary_relation='Friend / Classmate', contact_phone='01700000008', receive_method='hospital', receive_account='Square Hospital, Dhaka (paid directly to hospital account)' WHERE id=1;
UPDATE campaigns SET category='Education', beneficiary_name='EEE Department Library', beneficiary_relation='Student organiser', contact_phone='01700000009', receive_method='bkash', receive_account='bKash 01700000009 (Personal)' WHERE id=2;

INSERT INTO campaign_documents (campaign_id,doc_type,file_path,original_name,status) VALUES
(1,'medical_report',NULL,'tanvir_medical_report.pdf','verified'),
(1,'hospital_estimate',NULL,'hospital_cost_estimate.pdf','verified'),
(1,'beneficiary_id',NULL,'tanvir_student_id.jpg','verified'),
(2,'dept_recommendation',NULL,'department_recommendation_letter.pdf','pending');

-- ================================================================
-- SEED: sample complaints so the admin Reports screen is not empty
-- ================================================================
INSERT INTO reports (reporter_id,reported_user_id,category,reason,details,related_type,related_id,status,created_at) VALUES
(6,9,'fake_campaign','Library campaign has no documents yet','I donated after seeing the library campaign but I could not find any proof that the money will be used for books. Please check this campaign (demo report).','campaign',2,'open', NOW() - INTERVAL 5 HOUR),
(4,3,'abusive_chat','Repeated rude messages in chat','After I asked about repayment timing, this user sent several rude messages (demo report).','chat',NULL,'reviewing', NOW() - INTERVAL 1 DAY);
