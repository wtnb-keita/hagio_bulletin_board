/**
 * 管理画面ロジック (安全掲示板 No.1)
 * 依存: api.js, panel-render.js
 */

const Admin = (() => {
  // PHPから注入された初期値を使用（なければAPIフォールバック）
  let data     = { panels: typeof INITIAL_PANELS !== 'undefined' ? INITIAL_PANELS : [] };
  let activeId = null;
  let nextId   = typeof INITIAL_NEXT_ID !== 'undefined' ? INITIAL_NEXT_ID : 1;

  const BOARD_KEY = 'safety_board_1';
  const esc = PanelRender.escHtml;

  function escAttr(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
  }

  // ---- 初期化 ----
  async function init() {
    // PHPで初期データが注入されていればAPIコール不要
    if (typeof INITIAL_PANELS === 'undefined') {
      try {
        const res = await API.getPanels(BOARD_KEY);
        data = res;
        (data.panels || []).forEach(p => {
          const n = parseInt(String(p.id).replace('p',''));
          if (!isNaN(n) && n >= nextId) nextId = n + 1;
        });
      } catch(e) {
        showToast('データ取得失敗: ' + e.message, true);
        data = { panels: [] };
      }
    }
    renderSidebar();
    // 初期バージョンを記録し、他者の変更を10秒ごとに監視
    await syncVersion();
    setInterval(checkRemoteVersion, 10000);
  }

  // ---- 保存 ----
  async function saveAll() {
    try {
      const json = await API.savePanels(data.panels, BOARD_KEY);
      if (json.ok) {
        showToast('保存しました');
        // 自分の保存としてバージョンを記録（他者の変更と区別するため）
        await syncVersion();
      } else {
        showToast('保存失敗: ' + (json.error || '不明'), true);
      }
    } catch(e) {
      showToast('通信エラー: ' + e.message, true);
    }
  }

  // ---- バージョン監視 ----
  let _knownVersion = 0;

  async function syncVersion() {
    try {
      const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '../..';
      const res  = await fetch(`${base}/api/panels.php?board=${BOARD_KEY}&action=version`);
      const json = await res.json();
      _knownVersion = json.version || 0;
    } catch(e) { /* 無視 */ }
  }

  async function checkRemoteVersion() {
    try {
      const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '../..';
      const res  = await fetch(`${base}/api/panels.php?board=${BOARD_KEY}&action=version`);
      const json = await res.json();
      const v = json.version || 0;
      if (_knownVersion && v !== _knownVersion) showReloadBanner();
    } catch(e) { /* 無視 */ }
  }

  function showReloadBanner() {
    if (document.getElementById('reloadBanner')) return;
    const banner = document.createElement('div');
    banner.id = 'reloadBanner';
    banner.style.cssText = [
      'position:fixed','top:0','left:0','right:0','z-index:9999',
      'background:#e65c00','color:#fff','text-align:center',
      'padding:10px 16px','font-size:14px','font-weight:bold',
      'display:flex','align-items:center','justify-content:center','gap:16px',
    ].join(';');
    banner.innerHTML = `
      <span>⚠ 別の端末で変更が保存されました。最新の状態に更新してください。</span>
      <button onclick="location.reload()"
        style="background:#fff;color:#e65c00;border:none;border-radius:4px;padding:4px 14px;font-weight:bold;cursor:pointer">
        再読み込み
      </button>
      <button onclick="document.getElementById('reloadBanner').remove()"
        style="background:transparent;color:#fff;border:1px solid #fff;border-radius:4px;padding:4px 10px;cursor:pointer">
        閉じる
      </button>`;
    document.body.prepend(banner);
  }

  // ---- トースト ----
  function showToast(msg, isErr = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.toggle('toast-err', isErr);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
  }

  // ---- サイドバー ----
  const TYPE_LABELS = { media:'メディア', text:'テキスト', accident:'無災害記録', notice:'告知' };
  function typeLabel(type) { return TYPE_LABELS[type] || type; }

  function renderSidebar() {
    const list = document.getElementById('panelList');
    if (!data.panels.length) {
      list.innerHTML = '<div class="panel-list-empty">パネルがありません</div>';
      return;
    }
    list.innerHTML = data.panels.map(p => `
      <div class="panel-item ${p.id === activeId ? 'active' : ''}" onclick="Admin.selectPanel('${p.id}')">
        <span class="type-tag type-${p.type}">${typeLabel(p.type)}</span>
        <span class="panel-item-name">${esc(p.title || '（無題）')}</span>
        <div class="panel-item-actions">
          <button class="btn btn-danger btn-sm" onclick="event.stopPropagation();Admin.deletePanel('${p.id}')">✕</button>
        </div>
      </div>`).join('');
  }

  // ---- パネル追加モーダル ----
  let _selectedType = null;

  function openAddPanel() {
    _selectedType = null;
    // 選択状態リセット
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
    const titleInput = document.getElementById('addPanelTitle');
    if (titleInput) titleInput.value = '';
    const confirm = document.getElementById('addPanelConfirm');
    if (confirm) confirm.disabled = true;
    document.getElementById('addPanelModal').classList.add('open');
    if (titleInput) titleInput.focus();
  }

  function closeAddPanel() {
    document.getElementById('addPanelModal').classList.remove('open');
    _selectedType = null;
  }

  function selectType(btn) {
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    _selectedType = btn.dataset.type;

    // タイトルの自動補完（未入力の場合のみ）
    const titleInput = document.getElementById('addPanelTitle');
    const defaultTitles = { media:'新規メディアパネル', text:'新規テキストパネル', accident:'無災害記録', notice:'告知' };
    if (titleInput && !titleInput.value) titleInput.value = defaultTitles[_selectedType] || '';

    const confirm = document.getElementById('addPanelConfirm');
    if (confirm) confirm.disabled = false;
  }

  function confirmAddPanel() {
    if (!_selectedType) return;
    const titleInput = document.getElementById('addPanelTitle');
    const title = titleInput ? titleInput.value.trim() : '';
    const defaultTitles = { media:'新規メディアパネル', text:'新規テキストパネル', accident:'無災害記録', notice:'告知' };

    const panel = {
      id: 'p' + nextId++,
      type: _selectedType,
      title: title || defaultTitles[_selectedType],
      x: 10, y: 10, width: 300, height: 200,
      content: defaultContent(_selectedType),
    };
    data.panels.push(panel);
    closeAddPanel();
    renderSidebar();
    selectPanel(panel.id);
  }

  function defaultContent(type) {
    switch(type) {
      case 'media':    return { filePath:'', fileType:'', fileName:'', label:'' };
      case 'text':     return { text:'', vertical: false };
      case 'accident': return { targetDays: 1500, startDate: new Date().toISOString().split('T')[0] };
      case 'notice':   return { notices: [] };
      default:         return {};
    }
  }

  // ---- パネル削除 ----
  function deletePanel(id) {
    if (!confirm('このパネルを削除しますか？')) return;
    data.panels = data.panels.filter(p => p.id !== id);
    if (activeId === id) {
      activeId = null;
      document.getElementById('editor').innerHTML = '<div class="editor-empty">← パネルを選択して編集</div>';
    }
    renderSidebar();
  }

  // ---- パネル選択 ----
  function selectPanel(id) {
    activeId = id;
    renderSidebar();
    const panel = data.panels.find(p => p.id === id);
    if (panel) renderEditor(panel);
  }

  // ---- エディタ描画 ----
  function renderEditor(panel) {
    const ed = document.getElementById('editor');
    const hasTitle = panel.title && panel.title.length > 0;

    // タイトルセクション
    const titleSection = `
      <div class="card form-section">
        <h3>タイトル（ヘッダー表示）</h3>
        <div class="title-toggle-row">
          <button class="toggle-btn ${hasTitle ? 'active' : ''}" onclick="Admin.setTitleMode(true)">あり</button>
          <button class="toggle-btn ${hasTitle ? '' : 'active'}" onclick="Admin.setTitleMode(false)">なし</button>
        </div>
        <div id="titleInputArea" style="${hasTitle ? '' : 'display:none'}">
          <div class="title-preview-wrap">
            <div class="title-preview-visual">
              <div class="title-preview-bar" id="titlePreviewBar">${esc(panel.title)}</div>
              <div class="title-preview-body">パネル本体</div>
            </div>
          </div>
          <div class="form-group">
            <input type="text" id="f_title" value="${escAttr(panel.title)}"
              placeholder="タイトルを入力"
              oninput="document.getElementById('titlePreviewBar').textContent=this.value">
          </div>
        </div>
      </div>`;

    // 位置・サイズセクション（CSS transform スケール方式）
    // pos-board-inner が 1800×900 で transform:scale(0.25) → 450×225 に縮小表示
    // パネル座標はすべて実寸(px)で記述する

    const ghosts = data.panels
      .filter(p => p.id !== panel.id)
      .map(p => `<div class="pos-ghost" style="left:${p.x}px;top:${p.y}px;width:${p.width}px;height:${p.height}px">
          ${p.title ? '<div class="pos-ghost-title"></div>' : ''}
        </div>`).join('');

    const posSection = `
      <div class="card form-section">
        <h3>位置・サイズ</h3>
        <div class="pos-editor">
          <div class="pos-board" id="posBoard">
            <div class="pos-board-inner" id="posBoardInner">
              ${ghosts}
              <div class="pos-panel ${hasTitle ? '' : 'no-title'}" id="posPanel"
                   style="left:${panel.x||0}px;top:${panel.y||0}px;width:${panel.width||300}px;height:${panel.height||200}px">
                <div class="pos-panel-title" id="posPanelTitle">${esc(panel.title)}</div>
                <div class="pos-panel-body">${posPanelContent(panel)}</div>
                <div class="resize-handle nw" data-dir="nw"></div>
                <div class="resize-handle n"  data-dir="n"></div>
                <div class="resize-handle ne" data-dir="ne"></div>
                <div class="resize-handle e"  data-dir="e"></div>
                <div class="resize-handle se" data-dir="se"></div>
                <div class="resize-handle s"  data-dir="s"></div>
                <div class="resize-handle sw" data-dir="sw"></div>
                <div class="resize-handle w"  data-dir="w"></div>
              </div>
            </div>
          </div>
          <div class="pos-values">
            <span>X: <b id="dispX">${panel.x||0}</b> px</span>
            <span>Y: <b id="dispY">${panel.y||0}</b> px</span>
            <span>幅: <b id="dispW">${panel.width||300}</b> px</span>
            <span>高さ: <b id="dispH">${panel.height||200}</b> px</span>
          </div>
          <p class="pos-hint">パネルをドラッグして移動 ／ 角・辺のハンドルでリサイズ</p>
          <!-- 隠し入力（applyEdits で読み取る） -->
          <input type="hidden" id="f_x" value="${panel.x||0}">
          <input type="hidden" id="f_y" value="${panel.y||0}">
          <input type="hidden" id="f_w" value="${panel.width||300}">
          <input type="hidden" id="f_h" value="${panel.height||200}">
        </div>
      </div>`;

    let html = `<div class="editor-form">
      <h2>${typeLabel(panel.type)} パネルの編集</h2>
      ${titleSection}
      ${posSection}`;

    switch(panel.type) {
      case 'media':    html += mediaEditorHtml(panel);    break;
      case 'text':     html += textEditorHtml(panel);     break;
      case 'accident': html += accidentEditorHtml(panel); break;
      case 'notice':   html += noticeEditorHtml(panel);   break;
    }

    html += `
      <div class="form-row" style="margin-top:14px">
        <button class="btn btn-success" onclick="Admin.applyEdits();Admin.saveAll()">💾 保存</button>
      </div>
    </div>`;

    ed.innerHTML = html;

    initPositionEditor();
    if (panel.type === 'media') setupFileDrop(panel);
  }

  // ---- タイトルあり/なし切り替え ----
  function setTitleMode(show) {
    document.querySelectorAll('.title-toggle-row .toggle-btn').forEach((b, i) => {
      b.classList.toggle('active', show ? i === 0 : i === 1);
    });
    const area = document.getElementById('titleInputArea');
    if (area) area.style.display = show ? '' : 'none';

    // タイトルなし時はパネルプレビューを no-title にする
    const posPanel = document.getElementById('posPanel');
    if (posPanel) posPanel.classList.toggle('no-title', !show);

    if (show) document.getElementById('f_title')?.focus();
    else if (document.getElementById('f_title')) document.getElementById('f_title').value = '';
  }

  // ---- ポジションエディタ内パネルコンテンツ（実寸フォントで描画 → scale縮小で正確に見える） ----
  function posPanelContent(panel) {
    const c = panel.content || {};
    switch (panel.type) {
      case 'media':
        if (!c.filePath) return `<div class="pc-empty">画像なし</div>`;
        if (c.fileType === 'application/pdf') {
          return `<div class="pc-empty" style="font-size:48px">📄<br><span style="font-size:14px">${esc(c.fileName||'PDF')}</span></div>`;
        }
        return `<img src="${c.filePath}" alt="">`;

      case 'text': {
        const preview = (c.text || '').slice(0, 200);
        return `<div class="pc-text">${esc(preview) || '<span style="opacity:.4">テキストなし</span>'}</div>`;
      }

      case 'accident': {
        const start   = c.startDate || new Date().toISOString().split('T')[0];
        const elapsed = Math.max(0, Math.floor((new Date() - new Date(start)) / 86400000));
        return `<div class="pc-accident">
          <div class="pc-accident-num">${elapsed.toLocaleString()}</div>
          <div class="pc-accident-unit">日</div>
          <div class="pc-accident-target">目標 ${(c.targetDays||1500).toLocaleString()} 日</div>
        </div>`;
      }

      case 'notice': {
        const items = (c.notices || []).slice(0, 6);
        if (!items.length) return `<div class="pc-empty">告知なし</div>`;
        return `<div class="pc-notice">${items.map(n =>
          `<div class="pc-notice-item">${esc(n.title||'（無題）')}</div>`
        ).join('')}</div>`;
      }

      default: return '';
    }
  }

  // ---- ビジュアル配置エディタ ----
  const BOARD_W = 1800, BOARD_H = 900;
  const SCALE   = 0.25; // CSS transform scale — マウス座標→実座標変換に使用
  const MIN_SZ  = 50;   // 実寸最小値(px)

  function initPositionEditor() {
    const posPanel = document.getElementById('posPanel');
    if (!posPanel) return;

    let mode   = null; // 'drag' | 'resize'
    let dir    = null;
    let startX = 0, startY = 0;
    let origL  = 0, origT  = 0, origW  = 0, origH  = 0;

    function getRect() {
      return {
        l: parseInt(posPanel.style.left)  || 0,
        t: parseInt(posPanel.style.top)   || 0,
        w: parseInt(posPanel.style.width) || 300,
        h: parseInt(posPanel.style.height)|| 200,
      };
    }

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

    function setRect(l, t, w, h) {
      // 実寸でクランプ
      const rx = clamp(Math.round(l), 0, BOARD_W - MIN_SZ);
      const ry = clamp(Math.round(t), 0, BOARD_H - MIN_SZ);
      const rw = clamp(Math.round(w), MIN_SZ, BOARD_W - rx);
      const rh = clamp(Math.round(h), MIN_SZ, BOARD_H - ry);

      posPanel.style.left   = rx + 'px';
      posPanel.style.top    = ry + 'px';
      posPanel.style.width  = rw + 'px';
      posPanel.style.height = rh + 'px';

      document.getElementById('dispX').textContent = rx;
      document.getElementById('dispY').textContent = ry;
      document.getElementById('dispW').textContent = rw;
      document.getElementById('dispH').textContent = rh;

      document.getElementById('f_x').value = rx;
      document.getElementById('f_y').value = ry;
      document.getElementById('f_w').value = rw;
      document.getElementById('f_h').value = rh;
    }

    // ドラッグ開始（ハンドル以外）
    posPanel.addEventListener('pointerdown', e => {
      if (e.target.classList.contains('resize-handle')) return;
      mode = 'drag';
      const r = getRect();
      origL = r.l; origT = r.t;
      startX = e.clientX; startY = e.clientY;
      posPanel.setPointerCapture(e.pointerId);
      e.preventDefault();
    });

    // リサイズ開始
    posPanel.querySelectorAll('.resize-handle').forEach(h => {
      h.addEventListener('pointerdown', e => {
        mode = 'resize';
        dir  = h.dataset.dir;
        const r = getRect();
        origL = r.l; origT = r.t; origW = r.w; origH = r.h;
        startX = e.clientX; startY = e.clientY;
        h.setPointerCapture(e.pointerId);
        e.preventDefault();
        e.stopPropagation();
      });
    });

    posPanel.addEventListener('pointermove', e => {
      if (!mode) return;
      // clientX/Y はスクリーン座標 → SCALE で割って実寸に換算
      const dx = (e.clientX - startX) / SCALE;
      const dy = (e.clientY - startY) / SCALE;

      if (mode === 'drag') {
        setRect(origL + dx, origT + dy, getRect().w, getRect().h);
      } else {
        let l = origL, t = origT, w = origW, h = origH;
        if (dir.includes('e')) w = origW + dx;
        if (dir.includes('s')) h = origH + dy;
        if (dir.includes('w')) { l = origL + dx; w = origW - dx; }
        if (dir.includes('n')) { t = origT + dy; h = origH - dy; }
        setRect(l, t, w, h);
      }
    });

    posPanel.addEventListener('pointerup', () => { mode = null; dir = null; });
  }

  // ---- メディアエディタ ----
  function mediaEditorHtml(panel) {
    const c = panel.content || {};
    const previewHtml = c.filePath ? `
      <div class="file-preview" id="filePreview">
        ${c.fileType === 'application/pdf'
          ? `<div style="font-size:24px">📄</div>`
          : `<img src="${c.filePath}" alt="preview">`}
        <div class="file-preview-info">
          <div class="file-preview-name">${esc(c.fileName)}</div>
          <div class="file-preview-size">${esc(c.fileType)}</div>
        </div>
        <button class="btn btn-danger btn-sm" onclick="Admin.clearFile()">削除</button>
      </div>` : '<div id="filePreview"></div>';

    return `
      <div class="card form-section">
        <h3>ファイル（画像 / PDF）</h3>
        <div class="file-drop" id="fileDrop" onclick="document.getElementById('fileInput').click()">
          クリックまたはドロップで画像・PDFをアップロード<br>
          <span style="font-size:11px;color:#666">JPEG / PNG / GIF / WEBP / PDF（最大20MB）</span>
        </div>
        <input type="file" id="fileInput" accept="image/*,application/pdf" style="display:none">
        ${previewHtml}
        <div style="margin-top:8px">
          <button class="btn btn-secondary btn-sm" onclick="Admin.openUploadLibrary()">📁 アップロード済みから選択</button>
        </div>
      </div>
      <div class="card form-section">
        <h3>ラベル（ファイル下部テキスト）</h3>
        <div class="form-group">
          <textarea id="f_label" placeholder="画像・PDFの下部に重ねて表示するテキスト（省略可）">${esc(c.label)}</textarea>
        </div>
      </div>`;
  }

  function setupFileDrop(panel) {
    const drop  = document.getElementById('fileDrop');
    const input = document.getElementById('fileInput');
    if (!drop || !input) return;

    drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
    drop.addEventListener('drop', e => {
      e.preventDefault();
      drop.classList.remove('dragover');
      if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0], panel);
    });
    input.addEventListener('change', () => {
      if (input.files[0]) handleFile(input.files[0], panel);
    });
  }

  async function handleFile(file, panel) {
    const allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    if (!allowed.includes(file.type)) {
      alert('対応していないファイル形式です\n対応形式: JPEG, PNG, GIF, WEBP, PDF');
      return;
    }
    if (file.size > 20 * 1024 * 1024) {
      alert('ファイルサイズは20MB以下にしてください');
      return;
    }
    const drop = document.getElementById('fileDrop');
    if (drop) drop.textContent = 'アップロード中...';
    try {
      const json = await API.uploadFile(file);
      panel.content.filePath = json.filePath;
      panel.content.fileType = json.fileType;
      panel.content.fileName = json.fileName;
      updateFilePreview(panel);
    } catch(e) {
      alert('アップロード失敗: ' + e.message);
      if (drop) drop.textContent = 'クリックまたはドロップで画像・PDFをアップロード';
    }
  }

  function updateFilePreview(panel) {
    const c    = panel.content;
    const prev = document.getElementById('filePreview');
    if (!prev) return;
    prev.innerHTML = `
      <div class="file-preview">
        ${c.fileType === 'application/pdf'
          ? `<div style="font-size:24px">📄</div>`
          : `<img src="${c.filePath}" alt="preview">`}
        <div class="file-preview-info">
          <div class="file-preview-name">${esc(c.fileName)}</div>
          <div class="file-preview-size">${esc(c.fileType)}</div>
        </div>
        <button class="btn btn-danger btn-sm" onclick="Admin.clearFile()">削除</button>
      </div>`;
    const drop = document.getElementById('fileDrop');
    if (drop) drop.textContent = 'クリックまたはドロップで画像・PDFをアップロード';
  }

  function clearFile() {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    panel.content.filePath = '';
    panel.content.fileType = '';
    panel.content.fileName = '';
    const prev = document.getElementById('filePreview');
    if (prev) prev.innerHTML = '';
  }

  // ---- アップロードライブラリモーダル ----
  function openUploadLibrary() {
    document.getElementById('uploadLibModal').classList.add('open');
    loadLibFiles();
    setupLibDrop();
    const inp = document.getElementById('libUploadInput');
    // 二重登録防止
    inp.replaceWith(inp.cloneNode(true));
    document.getElementById('libUploadInput').addEventListener('change', e => {
      libUploadFiles([...e.target.files]);
      e.target.value = '';
    });
  }

  function closeUploadLibrary() {
    document.getElementById('uploadLibModal').classList.remove('open');
  }

  async function loadLibFiles() {
    const grid = document.getElementById('libGrid');
    grid.innerHTML = '<div style="color:var(--text-dim);font-size:13px;padding:20px;text-align:center">読み込み中...</div>';
    try {
      const res = await API.getUploads();
      const files = res.files || [];
      if (!files.length) {
        grid.innerHTML = '<div style="color:var(--text-dim);font-size:13px;padding:20px;text-align:center;grid-column:1/-1">ファイルがありません</div>';
        return;
      }
      grid.innerHTML = files.map(f => {
        const isImg = f.fileType.startsWith('image/');
        const thumb = isImg
          ? `<img src="${f.filePath}" style="width:100%;height:90px;object-fit:cover;display:block;">`
          : `<div style="height:90px;display:flex;align-items:center;justify-content:center;font-size:32px;background:var(--surface2)">📄</div>`;
        const kb = f.fileSize >= 1024*1024
          ? (f.fileSize/1024/1024).toFixed(1)+' MB'
          : Math.round(f.fileSize/1024)+' KB';
        return `
          <div onclick="Admin.pickFile('${esc(f.filePath)}','${esc(f.fileType)}','${esc(f.fileName)}')"
               style="border:2px solid var(--border);border-radius:6px;overflow:hidden;cursor:pointer;
                      background:var(--surface);transition:border-color .15s;"
               onmouseover="this.style.borderColor='var(--accent)'"
               onmouseout="this.style.borderColor='var(--border)'">
            ${thumb}
            <div style="padding:4px 6px;">
              <div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(f.fileName)}</div>
              <div style="font-size:10px;color:var(--text-dim)">${kb}</div>
            </div>
          </div>`;
      }).join('');
    } catch(e) {
      grid.innerHTML = `<div style="color:var(--danger);font-size:13px;padding:20px;">取得失敗: ${e.message}</div>`;
    }
  }

  function setupLibDrop() {
    const zone = document.getElementById('libDropZone');
    if (zone._dropReady) return;
    zone._dropReady = true;
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('dragover');
      libUploadFiles([...e.dataTransfer.files]);
    });
  }

  async function libUploadFiles(fileList) {
    const allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    const valid = fileList.filter(f => allowed.includes(f.type));
    if (!valid.length) { showToast('対応形式: JPEG, PNG, GIF, WEBP, PDF', true); return; }
    const zone = document.getElementById('libDropZone');
    let done = 0;
    for (const file of valid) {
      zone.textContent = `アップロード中 ${done+1}/${valid.length}...`;
      try { await API.uploadFile(file); done++; } catch(e) { /* continue */ }
    }
    zone.textContent = 'ここにファイルをドロップして追加';
    showToast(`${done} 件アップロードしました`);
    loadLibFiles();
  }

  // ファイルを選択してパネルに設定
  function pickFile(filePath, fileType, fileName) {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel || panel.type !== 'media') return;
    panel.content.filePath = filePath;
    panel.content.fileType = fileType;
    panel.content.fileName = fileName;
    closeUploadLibrary();
    updateFilePreview(panel);
    // ミニボードのプレビューも更新
    const posPanel = document.getElementById('posPanel');
    if (posPanel) {
      const body = posPanel.querySelector('.pos-panel-body');
      if (body) body.innerHTML = posPanelContent(panel);
    }
  }

  function onPickFile(filePath, fileType, fileName) {
    pickFile(filePath, fileType, fileName);
  }

  // ---- テキストエディタ ----
  function textEditorHtml(panel) {
    const c = panel.content || {};
    return `
      <div class="card form-section">
        <h3>テキスト内容</h3>
        <div class="form-group">
          <textarea id="f_text" rows="6">${esc(c.text)}</textarea>
        </div>
        <div class="form-row" style="margin-top:6px;align-items:center;gap:8px">
          <input type="checkbox" id="f_vertical" ${c.vertical ? 'checked' : ''}>
          <label for="f_vertical" style="color:var(--text);font-size:13px">縦書き表示</label>
        </div>
      </div>`;
  }

  // ---- 無災害記録エディタ ----
  function accidentEditorHtml(panel) {
    const c = panel.content || {};
    return `
      <div class="card form-section">
        <h3>無災害記録設定</h3>
        <div class="form-row">
          <div class="form-group">
            <label>目標日数</label>
            <input type="number" id="f_targetDays" value="${c.targetDays||1500}" min="1">
          </div>
          <div class="form-group">
            <label>起算日（無災害開始日）</label>
            <input type="date" id="f_startDate" value="${escAttr(c.startDate)}">
          </div>
        </div>
      </div>`;
  }

  // ---- 告知エディタ ----
  function noticeEditorHtml(panel) {
    const notices = (panel.content || {}).notices || [];
    const cards   = notices.map((n, i) => `
      <div class="notice-card" id="nc_${i}">
        <button class="btn btn-danger btn-sm" onclick="Admin.deleteNotice(${i})">✕</button>
        <div class="form-row">
          <div class="form-group">
            <label>タイトル</label>
            <input type="text" id="nt_${i}" value="${escAttr(n.title)}">
          </div>
          <div class="form-group" style="max-width:100px">
            <label>レベル</label>
            <select id="nl_${i}">
              <option value="1" ${n.level==1?'selected':''}>1 情報</option>
              <option value="2" ${n.level==2?'selected':''}>2 注意</option>
              <option value="3" ${n.level==3?'selected':''}>3 警告</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>開始日</label><input type="date" id="ns_${i}" value="${escAttr(n.startDate)}"></div>
          <div class="form-group"><label>終了日</label><input type="date" id="ne_${i}" value="${escAttr(n.endDate)}"></div>
        </div>
        <div class="form-group">
          <label>内容</label>
          <textarea id="nx_${i}" rows="3">${esc(n.text)}</textarea>
        </div>
      </div>`).join('');

    return `
      <div class="card form-section">
        <h3>告知一覧</h3>
        <div class="notice-list" id="noticeList">${cards}</div>
        <button class="btn btn-accent3 btn-sm" onclick="Admin.addNotice()">＋ 告知を追加</button>
        <p style="font-size:11px;color:var(--text-dim);margin-top:6px">※ 期間外の告知はビューに表示されません</p>
      </div>`;
  }

  function syncNotices(panel) {
    (panel.content.notices || []).forEach((n, i) => {
      const get = id => document.getElementById(id);
      if (get(`nt_${i}`)) n.title     = get(`nt_${i}`).value;
      if (get(`nl_${i}`)) n.level     = parseInt(get(`nl_${i}`).value);
      if (get(`ns_${i}`)) n.startDate = get(`ns_${i}`).value;
      if (get(`ne_${i}`)) n.endDate   = get(`ne_${i}`).value;
      if (get(`nx_${i}`)) n.text      = get(`nx_${i}`).value;
    });
  }

  function addNotice() {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    syncNotices(panel);
    panel.content.notices.push({ title:'', level:1, startDate:'', endDate:'', text:'' });
    renderEditor(panel);
  }

  function deleteNotice(i) {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    syncNotices(panel);
    panel.content.notices.splice(i, 1);
    renderEditor(panel);
  }

  // ---- 編集反映 ----
  function applyEdits() {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;

    const g = id => document.getElementById(id);

    // タイトル（あり/なし トグル対応）
    const titleArea = g('titleInputArea');
    const titleShown = titleArea && titleArea.style.display !== 'none';
    panel.title = titleShown ? (g('f_title')?.value ?? '') : '';

    if (g('f_x'))     panel.x      = parseInt(g('f_x').value)  || 0;
    if (g('f_y'))     panel.y      = parseInt(g('f_y').value)  || 0;
    if (g('f_w'))     panel.width  = parseInt(g('f_w').value)  || 300;
    if (g('f_h'))     panel.height = parseInt(g('f_h').value)  || 200;

    switch(panel.type) {
      case 'media':
        if (g('f_label')) panel.content.label = g('f_label').value;
        break;
      case 'text':
        if (g('f_text'))     panel.content.text     = g('f_text').value;
        if (g('f_vertical')) panel.content.vertical = g('f_vertical').checked;
        break;
      case 'accident':
        if (g('f_targetDays')) panel.content.targetDays = parseInt(g('f_targetDays').value) || 1500;
        if (g('f_startDate'))  panel.content.startDate  = g('f_startDate').value;
        break;
      case 'notice':
        syncNotices(panel);
        break;
    }

    renderSidebar();
    showToast('反映しました（未保存）');
  }

  // ---- ボード設定 ----
  function openBoardSettings() {
    document.getElementById('boardSettingsModal').classList.add('open');
    document.getElementById('bs_name')?.focus();
  }

  function closeBoardSettings() {
    document.getElementById('boardSettingsModal').classList.remove('open');
  }

  async function saveBoardSettings() {
    const name   = document.getElementById('bs_name')?.value?.trim();
    const width  = parseInt(document.getElementById('bs_width')?.value);
    const height = parseInt(document.getElementById('bs_height')?.value);

    if (!name)                         { showToast('掲示板名を入力してください', true); return; }
    if (width  < 400 || width  > 7680) { showToast('幅は 400〜7680 で指定してください', true); return; }
    if (height < 200 || height > 4320) { showToast('高さは 200〜4320 で指定してください', true); return; }

    try {
      const json = await API.saveBoard({ name, width, height });
      if (json.ok) {
        closeBoardSettings();
        showToast('ボード設定を保存しました。ページを再読み込みします...');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast('保存失敗: ' + (json.error || '不明'), true);
      }
    } catch(e) {
      showToast('通信エラー: ' + e.message, true);
    }
  }

  // ---- レイアウトプレビュー ----
  function openLayoutPreview() {
    const preview = document.getElementById('layoutPreview');
    if (!preview) return;
    preview.innerHTML = data.panels.map(p => {
      const x = p.x/2, y = p.y/2, w = p.width/2, h = p.height/2;
      return `<div class="layout-panel type-${p.type}" style="left:${x}px;top:${y}px;width:${w}px;height:${h}px">
        <div class="layout-panel-title">${esc(p.title || typeLabel(p.type))}</div>
      </div>`;
    }).join('');
    document.getElementById('layoutModal').classList.add('open');
  }

  function closeLayoutPreview() {
    document.getElementById('layoutModal').classList.remove('open');
  }

  function openViewBoard() {
    const base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '');
    window.open(`${base}/view_board/safetynotice_board_no1/index.php`, '_blank');
  }

  // ---- 公開API ----
  return {
    init,
    saveAll,
    selectPanel,
    deletePanel,
    openAddPanel,
    closeAddPanel,
    selectType,
    confirmAddPanel,
    setTitleMode,
    applyEdits,
    addNotice,
    deleteNotice,
    clearFile,
    openUploadLibrary,
    closeUploadLibrary,
    pickFile,
    onPickFile,
    openBoardSettings,
    closeBoardSettings,
    saveBoardSettings,
    openLayoutPreview,
    closeLayoutPreview,
    openViewBoard,
  };
})();

// 初期化
document.addEventListener('DOMContentLoaded', () => {
  Admin.init();

  // モーダル内 Enter で確定、Esc で閉じる
  document.addEventListener('keydown', e => {
    const modal = document.getElementById('addPanelModal');
    if (!modal?.classList.contains('open')) return;
    if (e.key === 'Escape') Admin.closeAddPanel();
    if (e.key === 'Enter') {
      const btn = document.getElementById('addPanelConfirm');
      if (btn && !btn.disabled) Admin.confirmAddPanel();
    }
  });
});
