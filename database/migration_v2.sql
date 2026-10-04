-- ============================================================
-- Migration v2 — for databases that were ALREADY imported from the old schema.sql
-- (fresh installs do NOT need this file: schema.sql already contains everything)
--
-- Adds:  NID verification, campaign credentials/documents, full complaint (report) system
-- Run ONCE in phpMyAdmin (select the uiu_friends database -> SQL tab -> paste -> Go)
-- ============================================================
USE uiu_friends;

-- 1) NID fields on users -------------------------------------------------
ALTER TABLE users
    ADD COLUMN nid_number        VARCHAR(20)  DEFAULT NULL UNIQUE,
    ADD COLUMN date_of_birth     DATE         DEFAULT NULL,
    ADD COLUMN address           VARCHAR(255) DEFAULT NULL,
    ADD COLUMN nid_front         VARCHAR(255) DEFAULT NULL,
    ADD COLUMN nid_back          VARCHAR(255) DEFAULT NULL,
    ADD COLUMN nid_status        ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted',
    ADD COLUMN nid_reject_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN nid_submitted_at  DATETIME     DEFAULT NULL;

-- 2) Campaign credential fields ------------------------------------------
ALTER TABLE campaigns
    ADD COLUMN category             VARCHAR(40)  NOT NULL DEFAULT 'Other',
    ADD COLUMN beneficiary_name     VARCHAR(120) DEFAULT NULL,
    ADD COLUMN beneficiary_relation VARCHAR(60)  DEFAULT NULL,
    ADD COLUMN contact_phone        VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN receive_method       ENUM('bkash','nagad','bank','hospital','other') DEFAULT NULL,
    ADD COLUMN receive_account      VARCHAR(150) DEFAULT NULL,
    ADD COLUMN admin_note           VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS campaign_documents (
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

-- 3) Complaint / report system --------------------------------------------
ALTER TABLE reports
    ADD COLUMN reported_user_id INT DEFAULT NULL AFTER reporter_id,
    ADD COLUMN category         VARCHAR(40) NOT NULL DEFAULT 'other' AFTER reported_user_id,
    ADD COLUMN details          TEXT DEFAULT NULL AFTER reason,
    ADD COLUMN related_type     ENUM('loan_post','campaign','agreement','chat','user','other') NOT NULL DEFAULT 'other' AFTER details,
    ADD COLUMN related_id       INT DEFAULT NULL AFTER related_type,
    ADD COLUMN evidence_file    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN admin_note       TEXT DEFAULT NULL,
    ADD COLUMN updated_at       DATETIME DEFAULT NULL,
    MODIFY COLUMN status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    ADD CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- NOTE: existing students start with nid_status = 'not_submitted'.
-- They must submit their NID (Security & Verification page) and be approved by an admin
-- before they can request a loan, make an offer, or start a campaign.
