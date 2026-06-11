/**
 * API通信モジュール
 * boards, panels, uploads に対する CRUD 操作を提供する
 */

const API = (() => {
  const BASE      = typeof BASE_URL !== 'undefined' ? BASE_URL + '/api' : '../../api';
  const BOARD_KEY = typeof ADMIN_BOARD_KEY !== 'undefined' ? ADMIN_BOARD_KEY : 'safety_board_1';

  async function request(url, options = {}) {
    const res = await fetch(url, options);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);
    return json;
  }

  return {
    /** パネル一覧取得 */
    getPanels(boardKey = BOARD_KEY) {
      return request(`${BASE}/panels.php?board=${boardKey}`);
    },

    /** パネル一括保存 */
    savePanels(panels, boardKey = BOARD_KEY) {
      return request(`${BASE}/panels.php?board=${boardKey}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ panels }),
      });
    },

    /** ファイルアップロード */
    async uploadFile(file) {
      const form = new FormData();
      form.append('file', file);
      return request(`${BASE}/upload.php`, { method: 'POST', body: form });
    },

    /** アップロード済みファイル一覧取得 */
    getUploads() {
      return request(`${BASE}/uploads.php`);
    },

    /** アップロード済みファイル削除 */
    deleteUpload(fileName) {
      return request(`${BASE}/uploads.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fileName }),
      });
    },

    /** ボード設定取得 */
    getBoard(boardKey = BOARD_KEY) {
      return request(`${BASE}/boards.php?board=${boardKey}`);
    },

    /** ボード設定更新 */
    saveBoard(settings, boardKey = BOARD_KEY) {
      return request(`${BASE}/boards.php?board=${boardKey}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings),
      });
    },
  };
})();
