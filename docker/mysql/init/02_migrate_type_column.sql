-- panels.type を ENUM から VARCHAR(32) に変更（新しいパネル種別を追加できるようにする）
ALTER TABLE panels MODIFY COLUMN type VARCHAR(32) NOT NULL;

-- 責任者掲示パネル用テーブル
CREATE TABLE IF NOT EXISTS panel_responsible (
  panel_uid   VARCHAR(64) PRIMARY KEY,
  board_key   VARCHAR(64) NOT NULL,
  role_name   VARCHAR(255) DEFAULT '化学物質管理者',
  person_name VARCHAR(255) DEFAULT '',
  font_size   INT NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
