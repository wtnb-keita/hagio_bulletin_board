-- ============================================================
--  マイグレーション: panels テーブルに is_delete カラムを追加
--  論理削除（ソフトデリート）のための列
-- ============================================================

ALTER TABLE panels
  ADD COLUMN is_delete TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=論理削除済み'
  AFTER title_visible;
