-- ============================================================
--  マイグレーション: コンテンツテーブルの PRIMARY KEY を
--  (panel_uid) → (panel_uid, board_key) 複合キーに変更
--
--  背景: 複数ボード間で panel_uid が重複した場合に
--        コンテンツデータが上書き/読み取り不可になるバグを修正
-- ============================================================

ALTER TABLE panel_media
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (panel_uid, board_key);

ALTER TABLE panel_text
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (panel_uid, board_key);

ALTER TABLE panel_accident
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (panel_uid, board_key);

ALTER TABLE panel_notice
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (panel_uid, board_key);

ALTER TABLE panel_responsible
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (panel_uid, board_key);
