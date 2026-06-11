CREATE TABLE IF NOT EXISTS boards (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL UNIQUE COMMENT '掲示板識別キー (例: safety_board_1)',
  name        VARCHAR(255) NOT NULL COMMENT '掲示板名',
  width       INT NOT NULL DEFAULT 1800,
  height      INT NOT NULL DEFAULT 900,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS panels (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL COMMENT 'boards.board_key',
  panel_uid   VARCHAR(64) NOT NULL COMMENT 'フロントが発行するユニークID',
  type        ENUM('media','text','accident','notice') NOT NULL,
  title       VARCHAR(255) DEFAULT '' COMMENT 'パネルヘッダー',
  pos_x       INT NOT NULL DEFAULT 0,
  pos_y       INT NOT NULL DEFAULT 0,
  width       INT NOT NULL DEFAULT 300,
  height      INT NOT NULL DEFAULT 200,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_board_uid (board_key, panel_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- メディアパネル用
CREATE TABLE IF NOT EXISTS panel_media (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL,
  file_name   VARCHAR(255) DEFAULT '',
  file_type   VARCHAR(64)  DEFAULT '',
  file_path   VARCHAR(512) DEFAULT '' COMMENT 'サーバー上のファイルパス',
  label_text  TEXT DEFAULT NULL COMMENT '画像下部ラベル'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- テキストパネル用
CREATE TABLE IF NOT EXISTS panel_text (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL,
  content     TEXT DEFAULT NULL,
  vertical    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=縦書き'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 無災害記録パネル用
CREATE TABLE IF NOT EXISTS panel_accident (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL,
  target_days INT NOT NULL DEFAULT 1500,
  start_date  DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 告知パネル用（ヘッダー）
CREATE TABLE IF NOT EXISTS panel_notice (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 告知アイテム
CREATE TABLE IF NOT EXISTS notice_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  panel_uid   VARCHAR(64) NOT NULL,
  board_key   VARCHAR(64) NOT NULL,
  title       VARCHAR(255) DEFAULT '',
  level       TINYINT NOT NULL DEFAULT 1 COMMENT '1=情報 2=注意 3=警告',
  start_date  DATE DEFAULT NULL,
  end_date    DATE DEFAULT NULL,
  content     TEXT DEFAULT NULL,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期データ
INSERT IGNORE INTO boards (board_key, name, width, height)
VALUES ('safety_board_1', '安全掲示板 No.1', 1800, 900);
