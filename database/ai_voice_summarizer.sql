-- ============================================================
-- AI Voice Summarizer - MySQL Database
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- ============================================================

CREATE DATABASE IF NOT EXISTS ai_voice_summarizer
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ai_voice_summarizer;

-- -----------------------------------------------------------
-- users table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED  AUTO_INCREMENT  PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    email       VARCHAR(190)  NOT NULL        UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_email ON users(email);

-- -----------------------------------------------------------
-- summaries table
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS summaries (
    id                 INT UNSIGNED  AUTO_INCREMENT  PRIMARY KEY,
    user_id            INT UNSIGNED  NOT NULL,
    title              VARCHAR(255)  NOT NULL        DEFAULT 'Untitled Summary',
    audio_filename     VARCHAR(255)  NOT NULL,
    transcription_text TEXT          NULL,
    summary_text       TEXT          NULL,
    summary_type       ENUM(
                          'short',
                          'detailed',
                          'bullets',
                          'meeting_notes',
                          'study_notes',
                          'custom'
                      )              NOT NULL DEFAULT 'short',
    custom_instruction   VARCHAR(500) NULL,
    custom_instruction  VARCHAR(500) NULL,
    language           VARCHAR(10)   NOT NULL        DEFAULT 'en',
    status             ENUM(
                          'transcribed',
                          'summarized',
                          'failed',
                          'deleted'
                      )              NOT NULL DEFAULT 'transcribed',
    created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_summaries_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_summaries_user_id ON summaries(user_id);
CREATE INDEX idx_summaries_status  ON summaries(status);
CREATE INDEX idx_summaries_created ON summaries(created_at);
CREATE INDEX idx_summaries_type    ON summaries(summary_type);
