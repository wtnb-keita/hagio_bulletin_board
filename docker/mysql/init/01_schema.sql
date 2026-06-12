-- ============================================================
--  hagio_bulletin_board  完全スキーマ（新規環境セットアップ用）
--  このファイル1本で全テーブル・初期データを作成できます。
-- ============================================================

-- ---- 掲示板マスター ----
CREATE TABLE IF NOT EXISTS boards (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  board_key          VARCHAR(64)  NOT NULL UNIQUE  COMMENT '掲示板識別キー',
  name               VARCHAR(255) NOT NULL          COMMENT '掲示板名',
  width              INT NOT NULL DEFAULT 1800,
  height             INT NOT NULL DEFAULT 900,
  slideshow_enabled  TINYINT(1)  NOT NULL DEFAULT 0  COMMENT '1=スライドショー有効',
  slideshow_interval INT         NOT NULL DEFAULT 10  COMMENT '自動切替間隔（秒）',
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- ページ管理 ----
CREATE TABLE IF NOT EXISTS board_pages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  board_key   VARCHAR(64)  NOT NULL,
  page_number INT          NOT NULL DEFAULT 1,
  page_name   VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uk_board_page (board_key, page_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- パネル共通 ----
CREATE TABLE IF NOT EXISTS panels (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL  COMMENT 'boards.board_key',
  panel_uid   VARCHAR(64) NOT NULL  COMMENT 'フロントが発行するユニークID',
  type        VARCHAR(32) NOT NULL,
  title       VARCHAR(255) DEFAULT '' COMMENT 'パネルヘッダー',
  pos_x       INT NOT NULL DEFAULT 0,
  pos_y       INT NOT NULL DEFAULT 0,
  width       INT NOT NULL DEFAULT 300,
  height      INT NOT NULL DEFAULT 200,
  sort_order  INT NOT NULL DEFAULT 0,
  page_number INT NOT NULL DEFAULT 1  COMMENT '所属ページ番号',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_board_uid (board_key, panel_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- メディアパネル ----
CREATE TABLE IF NOT EXISTS panel_media (
  panel_uid   VARCHAR(64)  PRIMARY KEY,
  board_key   VARCHAR(64)  NOT NULL,
  file_name   VARCHAR(255) DEFAULT '',
  file_type   VARCHAR(64)  DEFAULT '',
  file_path   VARCHAR(512) DEFAULT '' COMMENT 'サーバー上のファイルパス',
  label_text  TEXT         DEFAULT NULL COMMENT '画像下部ラベル'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- テキストパネル（disaster種別も兼用） ----
CREATE TABLE IF NOT EXISTS panel_text (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL,
  content     TEXT        DEFAULT NULL,
  vertical    TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1=縦書き'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- 無災害記録パネル ----
CREATE TABLE IF NOT EXISTS panel_accident (
  panel_uid    VARCHAR(64) PRIMARY KEY,
  board_key    VARCHAR(64) NOT NULL,
  target_days  INT  NOT NULL DEFAULT 1500,
  start_date   DATE NOT NULL,
  initial_days INT  NOT NULL DEFAULT 0 COMMENT '導入前の達成済み日数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- 告知パネル（ヘッダー） ----
CREATE TABLE IF NOT EXISTS panel_notice (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- 告知アイテム ----
CREATE TABLE IF NOT EXISTS notice_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  panel_uid   VARCHAR(64)  NOT NULL,
  board_key   VARCHAR(64)  NOT NULL,
  title       VARCHAR(255) DEFAULT '',
  level       TINYINT      NOT NULL DEFAULT 1 COMMENT '1=情報 2=注意 3=警告',
  start_date  DATE         DEFAULT NULL,
  end_date    DATE         DEFAULT NULL,
  content     TEXT         DEFAULT NULL,
  sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- 責任者掲示パネル ----
CREATE TABLE IF NOT EXISTS panel_responsible (
  panel_uid   VARCHAR(64)  PRIMARY KEY,
  board_key   VARCHAR(64)  NOT NULL,
  role_name   VARCHAR(255) DEFAULT '化学物質管理者',
  person_name VARCHAR(255) DEFAULT '',
  font_size   INT          NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- スタッフ ----
CREATE TABLE IF NOT EXISTS staff (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  board_key   VARCHAR(64)  NOT NULL,
  name        VARCHAR(255) NOT NULL DEFAULT '',
  department  VARCHAR(255) DEFAULT '' COMMENT '表示用サブテキスト（血液型等）',
  photo_path  VARCHAR(512) DEFAULT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  page_number INT          NOT NULL DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- スタッフ資格 ----
CREATE TABLE IF NOT EXISTS staff_qualifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  staff_id    INT          NOT NULL,
  name        VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  初期データ
-- ============================================================

INSERT IGNORE INTO boards (board_key, name, width, height) VALUES
  ('safety_board_1', '安全掲示板 No.1',   1800, 900),
  ('safety_board_2', '安全掲示板 No.2',   1800, 900),
  ('staff_board',    '安全資格者掲示板', 1800, 900);

INSERT IGNORE INTO board_pages (board_key, page_number, page_name, sort_order) VALUES
  ('safety_board_1', 1, 'ページ 1', 0),
  ('safety_board_2', 1, 'ページ 1', 0),
  ('staff_board',    1, 'ページ 1', 0);
