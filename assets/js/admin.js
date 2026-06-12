/**
 * 管理画面ロジック (安全掲示板 No.1)
 * 依存: api.js, panel-render.js
 */

const Admin = (() => {
  // PHPから注入された初期値を使用（なければAPIフォールバック）
  let data     = { panels: typeof INITIAL_PANELS !== 'undefined' ? INITIAL_PANELS : [] };
  let activeId = null;
  let nextId   = typeof INITIAL_NEXT_ID !== 'undefined' ? INITIAL_NEXT_ID : 1;

  // ページ管理
  let pages       = typeof INITIAL_PAGES !== 'undefined' ? [...INITIAL_PAGES] : [{page_number:1,page_name:'ページ 1',sort_order:0}];
  let currentPage = 1;

  const BOARD_KEY = typeof ADMIN_BOARD_KEY !== 'undefined' ? ADMIN_BOARD_KEY : 'safety_board_1';
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
    renderPageTabs();
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
  const TYPE_LABELS = { media:'メディア', text:'テキスト', accident:'無災害記録', notice:'告知', disaster:'災害速報', responsible:'責任者掲示', hazard:'警戒枠', label:'カラーラベル' };
  function typeLabel(type) { return TYPE_LABELS[type] || type; }

  // ---- ページタブ ----
  function renderPageTabs() {
    const container = document.getElementById('pageTabs');
    if (!container) return;
    container.innerHTML =
      pages.map(pg => `
      <button class="page-tab ${pg.page_number === currentPage ? 'active' : ''}"
              onclick="Admin.switchPage(${pg.page_number})"
              title="ダブルクリックでリネーム"
              ondblclick="Admin.renamePage(${pg.page_number})">
        ${esc(pg.page_name)}
        ${pages.length > 1 ? `<span class="page-tab-del" onclick="event.stopPropagation();Admin.deletePage(${pg.page_number})" title="削除">✕</span>` : ''}
      </button>`).join('') +
      `<button class="page-tab-add" onclick="Admin.addPage()" title="ページを追加">＋ ページ追加</button>`;
  }

  function switchPage(pageNum) {
    applyEdits();
    currentPage = pageNum;
    activeId    = null;
    renderPageTabs();
    renderSidebar();
    document.getElementById('editor').innerHTML = '<div class="editor-empty">← パネルを選択して編集</div>';
  }

  function addPage() {
    const maxNum = Math.max(0, ...pages.map(p => p.page_number));
    const newNum = maxNum + 1;
    pages.push({ page_number: newNum, page_name: 'ページ ' + newNum, sort_order: pages.length });
    currentPage = newNum;
    activeId    = null;
    renderPageTabs();
    renderSidebar();
    document.getElementById('editor').innerHTML = '<div class="editor-empty">← パネルを選択して編集</div>';
    _savePages();
  }

  function deletePage(pageNum) {
    if (pages.length <= 1) { showToast('最後のページは削除できません', true); return; }
    const pg = pages.find(p => p.page_number === pageNum);
    if (!confirm(`「${pg?.page_name || 'ページ'}」を削除しますか？\nこのページのパネルも全て削除されます。`)) return;
    data.panels = data.panels.filter(p => (p.page || 1) !== pageNum);
    pages       = pages.filter(p => p.page_number !== pageNum);
    if (currentPage === pageNum) currentPage = pages[0].page_number;
    activeId = null;
    renderPageTabs();
    renderSidebar();
    document.getElementById('editor').innerHTML = '<div class="editor-empty">← パネルを選択して編集</div>';
    _savePages();
    saveAll();
  }

  function renamePage(pageNum) {
    const pg = pages.find(p => p.page_number === pageNum);
    if (!pg) return;
    const name = prompt('ページ名を入力してください', pg.page_name);
    if (name === null) return;
    pg.page_name = name.trim() || pg.page_name;
    renderPageTabs();
    _savePages();
  }

  async function _savePages() {
    try { await API.savePages(pages, BOARD_KEY); } catch(e) { showToast('ページ保存エラー: ' + e.message, true); }
  }

  function renderSidebar() {
    const list         = document.getElementById('panelList');
    const visiblePanels = data.panels.filter(p => (p.page || 1) === currentPage);
    if (!visiblePanels.length) {
      list.innerHTML = '<div class="panel-list-empty">このページにパネルがありません</div>';
      return;
    }
    list.innerHTML = visiblePanels.map(p => `
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
    const defaultTitles = { media:'新規メディアパネル', text:'新規テキストパネル', accident:'無災害記録', notice:'告知', disaster:'○○会災害速報', responsible:'化学物質管理者', hazard:'警戒枠', label:'カラーラベル' };
    if (titleInput && !titleInput.value) titleInput.value = defaultTitles[_selectedType] || '';

    const confirm = document.getElementById('addPanelConfirm');
    if (confirm) confirm.disabled = false;
  }

  function confirmAddPanel() {
    if (!_selectedType) return;
    const titleInput = document.getElementById('addPanelTitle');
    const title = titleInput ? titleInput.value.trim() : '';
    const defaultTitles = { media:'新規メディアパネル', text:'新規テキストパネル', accident:'無災害記録', notice:'告知', disaster:'○○会災害速報', responsible:'化学物質管理者', hazard:'警戒枠', label:'カラーラベル' };

    const defaultSizes = { responsible: [120, 360], label: [400, 100] };
    const [defW, defH] = defaultSizes[_selectedType] || [300, 200];
    const noTitleByDefault = new Set(['label', 'hazard']);
    const panel = {
      id: 'p' + nextId++,
      type: _selectedType,
      title: title || defaultTitles[_selectedType],
      titleVisible: !noTitleByDefault.has(_selectedType),
      x: 10, y: 10, width: defW, height: defH,
      page: currentPage,
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
      case 'text':     return { text:'', vertical: false, fontSize: 14 };
      case 'accident': return { targetDays: 1500, startDate: new Date().toISOString().split('T')[0], initialDays: 0 };
      case 'notice':   return { notices: [] };
      case 'disaster':     return { items: [], slideshowEnabled: false, slideshowInterval: 5 };
      case 'responsible':  return { role: '化学物質管理者', name: '', fontSize: 40 };
      case 'hazard':       return { borderWidth: 30, stripeSize: 30, color1: '#FFD700', color2: '#000000', innerBg: '#ffffff', text: '', fontSize: 24, textColor: '#000000' };
      case 'label':        return { text: 'ラベルテキスト', textColor: '#ffffff', bgColor: '#e94560', fontSize: 24, textAlign: 'center', bold: true };
      default:             return {};
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
    saveAll();
  }

  // ---- パネル選択 ----
  function selectPanel(id) {
    activeId = id;
    renderSidebar();
    const panel = data.panels.find(p => p.id === id);
    if (panel) renderEditor(panel);
  }

  // ---- エディタ描画 ----
  // タイトルあり/なしトグルが不要なパネル種別
  const NO_TITLE_TOGGLE = new Set(['responsible', 'hazard', 'label']);

  function renderEditor(panel) {
    const ed = document.getElementById('editor');
    const hasTitle = panel.titleVisible !== undefined ? !!panel.titleVisible : !!(panel.title);

    // タイトルセクション（responsible / hazard / label はトグルなしの入力欄のみ）
    const titleSection = NO_TITLE_TOGGLE.has(panel.type)
      ? `<div class="card form-section">
          <h3>タイトル（ヘッダー表示）</h3>
          <div class="form-group">
            <input type="text" id="f_title" value="${escAttr(panel.title)}"
              placeholder="タイトルを入力（空欄なら非表示）">
          </div>
        </div>`
      : `<div class="card form-section">
          <h3>タイトル（ヘッダー表示）</h3>
          <div class="title-toggle-row">
            <button class="toggle-btn ${hasTitle ? 'active' : ''}" onclick="Admin.setTitleMode(true)">あり</button>
            <button class="toggle-btn ${hasTitle ? '' : 'active'}" onclick="Admin.setTitleMode(false)">なし</button>
          </div>
          <div id="titlePreviewArea" style="${hasTitle ? '' : 'display:none'}">
            <div class="title-preview-wrap">
              <div class="title-preview-visual">
                <div class="title-preview-bar" id="titlePreviewBar">${esc(panel.title)}</div>
                <div class="title-preview-body">パネル本体</div>
              </div>
            </div>
          </div>
          <div class="form-group" style="margin-top:8px">
            <input type="text" id="f_title" value="${escAttr(panel.title)}"
              placeholder="タイトルを入力（「なし」でも保持されます）"
              oninput="document.getElementById('titlePreviewBar').textContent=this.value">
          </div>
        </div>`;

    // 位置・サイズセクション（CSS transform スケール方式）
    // pos-board-inner が 1800×900 で transform:scale(0.25) → 450×225 に縮小表示
    // パネル座標はすべて実寸(px)で記述する

    const ghosts = data.panels
      .filter(p => p.id !== panel.id && (p.page || 1) === currentPage)
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
            <span>ページ: <b>${esc(pages.find(pg => pg.page_number === (panel.page||1))?.page_name || String(panel.page||1))}</b></span>
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
      case 'disaster':     html += disasterEditorHtml(panel);     break;
      case 'responsible':  html += responsibleEditorHtml(panel);  break;
      case 'hazard':       html += hazardEditorHtml(panel);       break;
      case 'label':        html += labelEditorHtml(panel);        break;
    }

    html += `
      <div class="editor-actions">
        <button class="btn btn-success btn-editor-action" onclick="Admin.applyEdits();Admin.saveAll()">💾 保存</button>
        <button class="btn btn-secondary btn-editor-action" onclick="Admin.saveAsTemplate()">📋 テンプレートとして保存</button>
      </div>
    </div>`;

    ed.innerHTML = html;

    initPositionEditor();
    if (panel.type === 'media')    setupFileDrop(panel);
    if (panel.type === 'disaster') setupDisasterUpload(panel);
  }

  // ---- タイトルあり/なし切り替え ----
  function setTitleMode(show) {
    document.querySelectorAll('.title-toggle-row .toggle-btn').forEach((b, i) => {
      b.classList.toggle('active', show ? i === 0 : i === 1);
    });
    // プレビューバーのみ表示切替（入力欄は常に表示）
    const preview = document.getElementById('titlePreviewArea');
    if (preview) preview.style.display = show ? '' : 'none';

    // ポジションエディタのプレビューも連動
    const posPanel = document.getElementById('posPanel');
    if (posPanel) posPanel.classList.toggle('no-title', !show);

    if (show) document.getElementById('f_title')?.focus();
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
        return `<img src="${c.filePath}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><div class="pc-empty" style="display:none">画像なし（ファイル未存在）</div>`;

      case 'text': {
        const preview = (c.text || '').slice(0, 200);
        return `<div class="pc-text">${esc(preview) || '<span style="opacity:.4">テキストなし</span>'}</div>`;
      }

      case 'accident': {
        const start   = c.startDate || new Date().toISOString().split('T')[0];
        const elapsed = Math.max(0, Math.floor((new Date() - new Date(start)) / 86400000));
        const total   = elapsed + (c.initialDays || 0);
        return `<div class="pc-accident">
          <div class="pc-accident-num">${total.toLocaleString()}</div>
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

      case 'disaster': {
        const cnt = (c.items || []).length;
        return cnt
          ? `<div class="pc-empty" style="font-size:14px">🚨 ${cnt} 件</div>`
          : `<div class="pc-empty">速報なし</div>`;
      }

      case 'responsible': {
        const fs = (c.fontSize || 40) + 'px';
        return `<div style="display:flex;width:100%;height:100%;background:#FFD700;">
          <div style="flex:1;background:#fff;margin:8%;display:flex;align-items:center;justify-content:center;writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#222;overflow:hidden;border:2px solid #e0b800;">${esc(c.name||'')}</div>
          <div style="writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#111;padding:6% 5% 6% 2%;white-space:nowrap;">${esc(c.role||'化学物質管理者')}</div>
        </div>`;
      }

      case 'hazard': {
        const bw = c.borderWidth || 30;
        const sz = c.stripeSize  || 30;
        const c1 = c.color1  || '#FFD700';
        const c2 = c.color2  || '#000000';
        const bg = c.innerBg || '#ffffff';
        const stripe = `repeating-linear-gradient(-45deg,${c1} 0px,${c1} ${sz}px,${c2} ${sz}px,${c2} ${sz*2}px)`;
        return `<div style="width:100%;height:100%;background:${stripe};padding:${bw}px;box-sizing:border-box;">
          <div style="width:100%;height:100%;background:${bg};display:flex;align-items:center;justify-content:center;font-size:14px;color:#666;">${esc(c.text||'')}</div>
        </div>`;
      }

      case 'label': {
        const bg = c.bgColor   || '#e94560';
        const tc = c.textColor || '#ffffff';
        const fs = (c.fontSize || 24) + 'px';
        return `<div style="width:100%;height:100%;background:${bg};display:flex;align-items:center;justify-content:center;color:${tc};font-size:${fs};font-weight:${c.bold!==false?'bold':'normal'};text-align:center;padding:8px;word-break:break-all;">${esc(c.text||'')}</div>`;
      }

      default: return '';
    }
  }

  // ---- ビジュアル配置エディタ ----
  const BOARD_W = typeof ADMIN_BOARD_W !== 'undefined' ? ADMIN_BOARD_W : 1800;
  const BOARD_H = typeof ADMIN_BOARD_H !== 'undefined' ? ADMIN_BOARD_H : 900;
  const MIN_SZ  = 50;   // 実寸最小値(px)

  function getPosScale() {
    const board = document.getElementById('posBoard');
    return board ? board.clientWidth / BOARD_W : 0.25;
  }

  function applyPosScale() {
    const board = document.getElementById('posBoard');
    const inner = document.getElementById('posBoardInner');
    if (!board || !inner) return;
    // innerのサイズを実際の解像度に合わせる
    inner.style.width  = BOARD_W + 'px';
    inner.style.height = BOARD_H + 'px';
    const scale = getPosScale();
    board.style.height = Math.round(BOARD_H * scale) + 'px';
    inner.style.transform = `scale(${scale})`;
  }

  function initPositionEditor() {
    const posPanel = document.getElementById('posPanel');
    if (!posPanel) return;

    applyPosScale();
    const ro = new ResizeObserver(applyPosScale);
    ro.observe(document.getElementById('posBoard'));

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
      const scale = getPosScale();
      const dx = (e.clientX - startX) / scale;
      const dy = (e.clientY - startY) / scale;

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
          ? `<div style="font-size:56px;line-height:1">📄</div>`
          : `<img src="${c.filePath}" alt="preview">`}
        <div class="file-preview-info">
          <div class="file-preview-name">${esc(c.fileName)}</div>
          <div class="file-preview-size">${esc(c.fileType)}</div>
        </div>
        <button class="btn btn-danger btn-sm" onclick="Admin.clearFile()">削除</button>
      </div>` : '<div id="filePreview"></div>';

    return `
      <div class="media-editor">
        <div class="card form-section">
          <h3>ファイル（画像 / PDF）</h3>
          <div class="file-drop" id="fileDrop" onclick="document.getElementById('fileInput').click()">
            <div style="font-size:28px;margin-bottom:7px">🖼️</div>
            クリックまたはドロップで画像・PDFをアップロード<br>
            <span style="font-size:12px;color:#888">JPEG / PNG / GIF / WEBP / PDF（最大20MB）</span>
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
            <textarea id="f_label" rows="3" placeholder="画像・PDFの下部に重ねて表示するテキスト（省略可）">${esc(c.label)}</textarea>
          </div>
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
          ? `<div style="font-size:80px;line-height:1">📄</div>`
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

  // ---- 災害速報エディタ ----
  function disasterEditorHtml(panel) {
    const c = panel.content || {};
    const items = c.items || [];

    const itemCards = items.map((item, i) => {
      const thumb = item.fileType
        ? (item.fileType.startsWith('image/')
            ? `<img src="${item.filePath}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;flex-shrink:0;">`
            : `<div style="width:60px;height:60px;display:flex;align-items:center;justify-content:center;font-size:28px;background:var(--surface2);border-radius:4px;flex-shrink:0;">📄</div>`)
        : `<div style="width:60px;height:60px;display:flex;align-items:center;justify-content:center;font-size:28px;background:var(--surface2);border-radius:4px;flex-shrink:0;">📝</div>`;
      const label = item.fileType ? esc(item.fileName || item.filePath) : `テキスト: ${esc((item.text||'').slice(0,40))}`;
      return `
        <div class="notice-card" style="display:flex;align-items:center;gap:10px;padding:8px 10px;">
          ${thumb}
          <span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${label}</span>
          <button class="btn btn-danger btn-sm" onclick="Admin.deleteDisasterItem(${i})">✕</button>
        </div>`;
    }).join('');

    return `
      <div class="card form-section">
        <h3>コンテンツ（画像・PDF・テキスト）</h3>
        <p style="font-size:11px;color:var(--text-dim);margin-bottom:8px">
          画像・PDF・テキストを複数追加できます。多くなるとスライドショーで表示します。
        </p>
        <div id="disasterItemList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">
          ${itemCards || '<div style="color:var(--text-dim);font-size:12px;padding:8px 0">コンテンツがありません</div>'}
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label class="btn btn-primary btn-sm" style="cursor:pointer">
            ＋ アップロード
            <input type="file" id="disasterFileInput" accept="image/*,application/pdf" multiple style="display:none">
          </label>
          <button class="btn btn-secondary btn-sm" onclick="Admin.openDisasterLibrary()">📁 ライブラリから選択</button>
          <button class="btn btn-secondary btn-sm" onclick="Admin.addDisasterTextItem()">📝 テキスト追加</button>
        </div>
      </div>
      <div class="card form-section">
        <h3>スライドショー設定</h3>
        <div class="form-row" style="align-items:center;gap:16px">
          <label style="display:flex;align-items:center;gap:6px;color:var(--text);font-size:13px;white-space:nowrap">
            <input type="checkbox" id="f_slideshow_enabled" ${c.slideshowEnabled ? 'checked' : ''}>
            スライドショー表示
          </label>
          <div style="display:flex;align-items:center;gap:6px">
            <label style="color:var(--text-dim);font-size:12px;white-space:nowrap">切替間隔</label>
            <input type="number" id="f_slideshow_interval" value="${c.slideshowInterval || 5}" min="1" max="60" style="width:70px">
            <span style="font-size:12px;color:var(--text-dim)">秒</span>
          </div>
        </div>
      </div>`;
  }

  // ---- 責任者掲示エディタ ----
  function responsibleEditorHtml(panel) {
    const c = panel.content || {};
    const fs = c.fontSize || 40;
    return `
      <div class="card form-section">
        <h3>責任者掲示設定</h3>
        <div class="form-group">
          <label>役職名（縦書き・右側に表示）</label>
          <input type="text" id="f_role" value="${escAttr(c.role||'化学物質管理者')}" placeholder="化学物質管理者">
        </div>
        <div class="form-group">
          <label>名前（縦書き・左側の白枠に表示）</label>
          <input type="text" id="f_name" value="${escAttr(c.name||'')}" placeholder="氏名を入力">
        </div>
        <div class="form-row" style="align-items:center;gap:8px;margin-top:4px">
          <label style="color:var(--text);font-size:13px;white-space:nowrap">文字サイズ (px)</label>
          <input type="number" id="f_fontSize" value="${fs}" min="10" max="200" step="1" style="width:80px">
        </div>
      </div>`;
  }

  // ---- 警戒枠エディタ ----
  function hazardEditorHtml(panel) {
    const c = panel.content || {};
    return `
      <div class="card form-section">
        <h3>警戒枠設定</h3>
        <div class="form-row">
          <div class="form-group">
            <label>枠の太さ (px)</label>
            <input type="number" id="f_borderWidth" value="${c.borderWidth||30}" min="5" max="300" step="1" style="width:80px">
          </div>
          <div class="form-group">
            <label>ストライプ幅 (px)</label>
            <input type="number" id="f_stripeSize" value="${c.stripeSize||30}" min="5" max="200" step="1" style="width:80px">
          </div>
        </div>
        <div class="form-row" style="margin-top:8px;flex-wrap:wrap;gap:12px">
          <div class="form-group">
            <label>色1（明るい色）</label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="f_color1" value="${escAttr(c.color1||'#FFD700')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer"
                     oninput="document.getElementById('f_color1_txt').textContent=this.value">
              <span id="f_color1_txt" style="font-size:12px;color:var(--text-dim)">${escAttr(c.color1||'#FFD700')}</span>
            </div>
          </div>
          <div class="form-group">
            <label>色2（暗い色）</label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="f_color2" value="${escAttr(c.color2||'#000000')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer"
                     oninput="document.getElementById('f_color2_txt').textContent=this.value">
              <span id="f_color2_txt" style="font-size:12px;color:var(--text-dim)">${escAttr(c.color2||'#000000')}</span>
            </div>
          </div>
          <div class="form-group">
            <label>内側の背景色</label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="f_innerBg" value="${escAttr(c.innerBg||'#ffffff')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer"
                     oninput="document.getElementById('f_innerBg_txt').textContent=this.value">
              <span id="f_innerBg_txt" style="font-size:12px;color:var(--text-dim)">${escAttr(c.innerBg||'#ffffff')}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="card form-section">
        <h3>内側テキスト（省略可）</h3>
        <div class="form-group">
          <textarea id="f_text" rows="3" placeholder="内側に表示するテキスト（省略可）">${esc(c.text||'')}</textarea>
        </div>
        <div class="form-row" style="align-items:center;gap:16px;flex-wrap:wrap">
          <label style="color:var(--text);font-size:13px;white-space:nowrap">
            文字サイズ (px)
            <input type="number" id="f_fontSize" value="${c.fontSize||24}" min="6" max="200" step="1" style="width:70px">
          </label>
          <div class="form-group" style="margin:0">
            <label>文字色</label>
            <input type="color" id="f_textColor" value="${escAttr(c.textColor||'#000000')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer">
          </div>
        </div>
      </div>`;
  }

  // ---- カラーラベルエディタ ----
  function labelEditorHtml(panel) {
    const c = panel.content || {};
    return `
      <div class="card form-section">
        <h3>テキスト内容</h3>
        <div class="form-group">
          <textarea id="f_text" rows="4">${esc(c.text||'')}</textarea>
        </div>
        <div class="form-row" style="margin-top:6px;align-items:center;gap:16px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:6px;color:var(--text);font-size:13px">
            <input type="checkbox" id="f_bold" ${c.bold!==false?'checked':''}>
            太字
          </label>
          <label style="color:var(--text);font-size:13px;white-space:nowrap">
            文字サイズ (px)
            <input type="number" id="f_fontSize" value="${c.fontSize||24}" min="6" max="200" step="1" style="width:70px">
          </label>
          <div style="display:flex;align-items:center;gap:6px">
            <label style="color:var(--text);font-size:13px">揃え</label>
            <select id="f_textAlign" style="font-size:13px">
              <option value="left"   ${(c.textAlign||'center')==='left'   ?'selected':''}>左揃え</option>
              <option value="center" ${(c.textAlign||'center')==='center' ?'selected':''}>中央揃え</option>
              <option value="right"  ${(c.textAlign||'center')==='right'  ?'selected':''}>右揃え</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card form-section">
        <h3>色設定</h3>
        <div class="form-row" style="flex-wrap:wrap;gap:12px">
          <div class="form-group">
            <label>文字色</label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="f_textColor" value="${escAttr(c.textColor||'#ffffff')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer"
                     oninput="document.getElementById('f_textColor_txt').textContent=this.value">
              <span id="f_textColor_txt" style="font-size:12px;color:var(--text-dim)">${escAttr(c.textColor||'#ffffff')}</span>
            </div>
          </div>
          <div class="form-group">
            <label>背景色</label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="f_bgColor" value="${escAttr(c.bgColor||'#e94560')}" style="width:48px;height:32px;border:none;padding:0;cursor:pointer"
                     oninput="document.getElementById('f_bgColor_txt').textContent=this.value">
              <span id="f_bgColor_txt" style="font-size:12px;color:var(--text-dim)">${escAttr(c.bgColor||'#e94560')}</span>
            </div>
          </div>
        </div>
      </div>`;
  }

  function setupDisasterUpload(panel) {
    const input = document.getElementById('disasterFileInput');
    if (!input) return;
    input.addEventListener('change', async () => {
      const files = [...input.files];
      const allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
      for (const file of files) {
        if (!allowed.includes(file.type)) continue;
        try {
          const json = await API.uploadFile(file);
          panel.content.items.push({ filePath: json.filePath, fileType: json.fileType, fileName: json.fileName });
        } catch(e) { showToast('アップロード失敗: ' + e.message, true); }
      }
      input.value = '';
      renderEditor(panel);
    });
  }

  function deleteDisasterItem(i) {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    panel.content.items.splice(i, 1);
    renderEditor(panel);
  }

  function addDisasterTextItem() {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    const text = prompt('表示するテキストを入力してください:');
    if (text === null) return;
    panel.content.items.push({ text });
    renderEditor(panel);
  }

  let _libContext = 'media';

  function openDisasterLibrary() {
    _libContext = 'disaster';
    document.getElementById('uploadLibModal').classList.add('open');
    loadLibFiles();
    setupLibDrop();
    const inp = document.getElementById('libUploadInput');
    inp.replaceWith(inp.cloneNode(true));
    document.getElementById('libUploadInput').addEventListener('change', e => {
      libUploadFiles([...e.target.files]);
      e.target.value = '';
    });
  }

  // ---- アップロードライブラリモーダル ----
  function openUploadLibrary() {
    _libContext = 'media';
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
          <div style="border:2px solid var(--border);border-radius:6px;overflow:hidden;
                      background:var(--surface);transition:border-color .15s;position:relative;"
               onmouseover="this.style.borderColor='var(--accent)'"
               onmouseout="this.style.borderColor='var(--border)'">
            <div onclick="Admin.pickFile('${esc(f.filePath)}','${esc(f.fileType)}','${esc(f.fileName)}')" style="cursor:pointer;">
              ${thumb}
              <div style="padding:4px 6px;">
                <div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(f.fileName)}</div>
                <div style="font-size:10px;color:var(--text-dim)">${kb}</div>
              </div>
            </div>
            <button class="btn btn-danger btn-sm"
                    onclick="event.stopPropagation();Admin.deleteLibFile('${esc(f.fileName)}')"
                    style="position:absolute;top:4px;right:4px;padding:1px 5px;font-size:10px;line-height:1.4;">✕</button>
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

  // アップロードライブラリからファイルを削除
  async function deleteLibFile(fileName) {
    if (!confirm(`「${fileName}」を削除しますか？\nこのファイルを使用中のパネルでは画像が表示されなくなります。`)) return;
    try {
      await API.deleteUpload(fileName);
      showToast('削除しました');
      loadLibFiles();
    } catch(e) {
      alert('削除失敗: ' + e.message);
    }
  }

  // ファイルを選択してパネルに設定
  function pickFile(filePath, fileType, fileName) {
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) return;
    if (_libContext === 'disaster' && panel.type === 'disaster') {
      panel.content.items.push({ filePath, fileType, fileName });
      closeUploadLibrary();
      renderEditor(panel);
      return;
    }
    if (panel.type !== 'media') return;
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
        <div class="form-row" style="margin-top:6px;align-items:center;gap:16px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:6px;color:var(--text);font-size:13px">
            <input type="checkbox" id="f_vertical" ${c.vertical ? 'checked' : ''}>
            縦書き表示
          </label>
          <label style="display:flex;align-items:center;gap:6px;color:var(--text);font-size:13px;white-space:nowrap">
            文字サイズ (px)
            <input type="number" id="f_fontSize" value="${c.fontSize || 14}" min="6" max="200" step="1" style="width:70px">
          </label>
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

    // タイトル（トグルなし種別はタイトルの有無で自動決定）
    panel.title = g('f_title')?.value ?? '';
    if (NO_TITLE_TOGGLE.has(panel.type)) {
      panel.titleVisible = false;
    } else {
      const titlePreview = g('titlePreviewArea');
      panel.titleVisible = titlePreview && titlePreview.style.display !== 'none';
    }

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
        if (g('f_fontSize')) panel.content.fontSize = parseInt(g('f_fontSize').value) || 14;
        break;
      case 'accident':
        if (g('f_targetDays'))  panel.content.targetDays  = parseInt(g('f_targetDays').value) || 1500;
        if (g('f_startDate'))   panel.content.startDate   = g('f_startDate').value;
        if (g('f_initialDays')) panel.content.initialDays = Math.max(0, parseInt(g('f_initialDays').value) || 0);
        break;
      case 'notice':
        syncNotices(panel);
        break;
      case 'disaster':
        if (g('f_slideshow_enabled'))  panel.content.slideshowEnabled  = g('f_slideshow_enabled').checked;
        if (g('f_slideshow_interval')) panel.content.slideshowInterval = parseInt(g('f_slideshow_interval').value) || 5;
        break;
      case 'responsible':
        if (g('f_role'))     panel.content.role     = g('f_role').value;
        if (g('f_name'))     panel.content.name     = g('f_name').value;
        if (g('f_fontSize')) panel.content.fontSize = parseInt(g('f_fontSize').value) || 40;
        break;
      case 'hazard':
        if (g('f_borderWidth')) panel.content.borderWidth = parseInt(g('f_borderWidth').value) || 30;
        if (g('f_stripeSize'))  panel.content.stripeSize  = parseInt(g('f_stripeSize').value)  || 30;
        if (g('f_color1'))      panel.content.color1      = g('f_color1').value;
        if (g('f_color2'))      panel.content.color2      = g('f_color2').value;
        if (g('f_innerBg'))     panel.content.innerBg     = g('f_innerBg').value;
        if (g('f_text'))        panel.content.text        = g('f_text').value;
        if (g('f_fontSize'))    panel.content.fontSize    = parseInt(g('f_fontSize').value) || 24;
        if (g('f_textColor'))   panel.content.textColor   = g('f_textColor').value;
        break;
      case 'label':
        if (g('f_text'))      panel.content.text      = g('f_text').value;
        if (g('f_textColor')) panel.content.textColor = g('f_textColor').value;
        if (g('f_bgColor'))   panel.content.bgColor   = g('f_bgColor').value;
        if (g('f_fontSize'))  panel.content.fontSize  = parseInt(g('f_fontSize').value) || 24;
        if (g('f_textAlign')) panel.content.textAlign = g('f_textAlign').value;
        if (g('f_bold'))      panel.content.bold      = g('f_bold').checked;
        break;
    }

    renderSidebar();
    showToast('反映しました（未保存）');
  }

  // ---- スライドショートグルボタン ----
  function _setSsBtn(enabled) {
    const btn = document.getElementById('bs_slideshow');
    if (!btn) return;
    btn.dataset.enabled = enabled ? '1' : '0';
    btn.querySelector('.ss-dot').style.background   = enabled ? '#4caf50' : '#aaa';
    btn.querySelector('.ss-label').textContent       = enabled ? '有効' : '無効';
    btn.style.background  = enabled ? 'rgba(76,175,80,0.12)' : 'var(--surface2)';
    btn.style.borderColor = enabled ? '#4caf50'               : 'var(--border)';
    btn.style.color       = enabled ? '#2e7d32'               : 'var(--text-dim)';
  }

  function toggleSlideshowBtn() {
    const btn = document.getElementById('bs_slideshow');
    if (btn) _setSsBtn(btn.dataset.enabled !== '1');
  }

  // ---- ボード設定 ----
  function openBoardSettings() {
    document.getElementById('boardSettingsModal').classList.add('open');
    // DBから現在のスライドショー設定を読み込んでトグルボタン・間隔を復元
    API.getBoard(BOARD_KEY).then(cfg => {
      _setSsBtn(!!cfg.slideshow_enabled);
      const ivEl = document.getElementById('bs_interval');
      if (ivEl && cfg.slideshow_interval) ivEl.value = cfg.slideshow_interval;
    }).catch(() => {});
    document.getElementById('bs_name')?.focus();
  }

  function closeBoardSettings() {
    document.getElementById('boardSettingsModal').classList.remove('open');
  }

  async function saveBoardSettings() {
    const name       = document.getElementById('bs_name')?.value?.trim();
    const width      = parseInt(document.getElementById('bs_width')?.value);
    const height     = parseInt(document.getElementById('bs_height')?.value);
    const ssEnabled  = document.getElementById('bs_slideshow')?.dataset.enabled === '1';
    const ssInterval = parseInt(document.getElementById('bs_interval')?.value) || 10;

    if (!name)                         { showToast('掲示板名を入力してください', true); return; }
    if (width  < 400 || width  > 7680) { showToast('幅は 400〜7680 で指定してください', true); return; }
    if (height < 200 || height > 4320) { showToast('高さは 200〜4320 で指定してください', true); return; }

    try {
      const json = await API.saveBoard({ name, width, height, slideshow_enabled: ssEnabled, slideshow_interval: ssInterval }, BOARD_KEY);
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
    const path = (typeof ADMIN_VIEW_URL !== 'undefined' ? ADMIN_VIEW_URL : '/view_board/safetynotice_board_no1/index.php');
    window.open(`${base}${path}`, '_blank');
  }

  // ---- テンプレート管理 ----
  function _tplApiBase() {
    const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '../..';
    return `${base}/api/templates.php`;
  }

  async function saveAsTemplate() {
    applyEdits();
    const panel = data.panels.find(p => p.id === activeId);
    if (!panel) { showToast('保存するパネルを選択してください', true); return; }
    const defaultName = panel.title || typeLabel(panel.type);
    const name = prompt('テンプレート名を入力してください', defaultName);
    if (name === null) return;
    try {
      await fetch(_tplApiBase(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name.trim() || defaultName,
          type: panel.type,
          title: panel.title || '',
          content: panel.content || {},
        }),
      });
      showToast('テンプレートに保存しました');
    } catch(e) {
      showToast('保存エラー: ' + e.message, true);
    }
  }

  async function openTemplateModal() {
    document.getElementById('templateModal').classList.add('open');
    await _renderTemplateList();
  }

  function closeTemplateModal() {
    document.getElementById('templateModal').classList.remove('open');
  }

  async function _renderTemplateList() {
    const list = document.getElementById('templateList');
    if (!list) return;
    list.innerHTML = '<div class="template-empty">読み込み中...</div>';
    try {
      const res   = await fetch(_tplApiBase());
      const json  = await res.json();
      const tmpls = json.templates || [];
      if (!tmpls.length) {
        list.innerHTML = `<div class="template-empty">テンプレートがありません<br><small>パネル編集画面の「📋 テンプレートとして保存」から追加できます</small></div>`;
        return;
      }
      list.innerHTML = tmpls.map(t => `
        <div class="template-item">
          <span class="type-tag type-${t.type}">${typeLabel(t.type)}</span>
          <div class="template-item-info">
            <div class="template-item-name">${esc(t.name)}</div>
            ${t.title ? `<div class="template-item-title">${esc(t.title)}</div>` : ''}
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <button class="btn btn-primary btn-sm" onclick="Admin.applyTemplate(${t.id})">使用</button>
            <button class="btn btn-danger btn-sm" onclick="Admin.deleteTemplate(${t.id})">削除</button>
          </div>
        </div>`).join('');
    } catch(e) {
      list.innerHTML = '<div class="template-empty">読み込みエラー</div>';
    }
  }

  async function applyTemplate(tplId) {
    try {
      const res  = await fetch(_tplApiBase());
      const json = await res.json();
      const tpl  = (json.templates || []).find(t => t.id === tplId);
      if (!tpl) return;
      const id = 'p' + nextId++;
      const newPanel = {
        id,
        type: tpl.type,
        title: tpl.title || '',
        content: JSON.parse(JSON.stringify(tpl.content || {})),
        page: currentPage,
        x: 0, y: 0, width: 400, height: 300,
        titleVisible: true,
      };
      data.panels.push(newPanel);
      activeId = id;
      renderSidebar();
      renderEditor(newPanel);
      closeTemplateModal();
      showToast(`「${tpl.name}」を適用しました（未保存）`);
    } catch(e) {
      showToast('適用エラー: ' + e.message, true);
    }
  }

  async function deleteTemplate(tplId) {
    if (!confirm('このテンプレートを削除しますか？')) return;
    try {
      await fetch(`${_tplApiBase()}?id=${tplId}`, { method: 'DELETE' });
      await _renderTemplateList();
    } catch(e) {
      showToast('削除エラー: ' + e.message, true);
    }
  }

  // ---- 削除済み履歴 ----
  let _historyOpen = false;

  function toggleHistory() {
    _historyOpen = !_historyOpen;
    const list = document.getElementById('historyPanelList');
    const icon = document.getElementById('historyToggleIcon');
    if (list) list.style.display = _historyOpen ? '' : 'none';
    if (icon) icon.textContent = _historyOpen ? '▼' : '▶';
    if (_historyOpen) loadHistory();
  }

  async function loadHistory() {
    const list = document.getElementById('historyPanelList');
    if (!list) return;
    list.innerHTML = '<div class="panel-list-empty">読み込み中...</div>';
    try {
      const res     = await API.getDeletedPanels(BOARD_KEY);
      const deleted = res.panels || [];
      if (!deleted.length) {
        list.innerHTML = '<div class="panel-list-empty">削除済みパネルはありません</div>';
        return;
      }
      list.innerHTML = deleted.map(p => {
        const pgNum = p.page || 1;
        const pg    = pages.find(pg => pg.page_number === pgNum);
        const pgLabel = pg ? esc(pg.page_name) : `ページ ${pgNum}`;
        return `
        <div class="panel-item" style="opacity:0.6;">
          <span class="type-tag type-${p.type}">${typeLabel(p.type)}</span>
          <span class="panel-item-name" title="${esc(p.title || '（無題）')}">
            ${esc(p.title || '（無題）')}<br>
            <small style="color:var(--text-dim);font-size:10px">${pgLabel}</small>
          </span>
          <div class="panel-item-actions">
            <button class="btn btn-secondary btn-sm" onclick="Admin.restorePanel('${p.id}')">↩</button>
          </div>
        </div>`;
      }).join('');
    } catch(e) {
      list.innerHTML = `<div class="panel-list-empty" style="color:var(--danger)">読み込みエラー</div>`;
    }
  }

  async function restorePanel(uid) {
    try {
      await API.restorePanel(uid, BOARD_KEY);
      showToast('パネルを復元しました');
      // パネル一覧をAPIから再取得して反映
      const res = await API.getPanels(BOARD_KEY);
      data.panels = res.panels || [];
      // nextId を更新
      data.panels.forEach(p => {
        const n = parseInt(String(p.id).replace('p', ''));
        if (!isNaN(n) && n >= nextId) nextId = n + 1;
      });
      activeId = null;
      renderPageTabs();
      renderSidebar();
      document.getElementById('editor').innerHTML = '<div class="editor-empty">← パネルを選択して編集</div>';
      if (_historyOpen) loadHistory();
    } catch(e) {
      showToast('復元失敗: ' + e.message, true);
    }
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
    deleteLibFile,
    openBoardSettings,
    closeBoardSettings,
    saveBoardSettings,
    toggleSlideshowBtn,
    openLayoutPreview,
    closeLayoutPreview,
    openViewBoard,
    openDisasterLibrary,
    deleteDisasterItem,
    addDisasterTextItem,
    switchPage,
    addPage,
    deletePage,
    renamePage,
    saveAsTemplate,
    openTemplateModal,
    closeTemplateModal,
    applyTemplate,
    deleteTemplate,
    toggleHistory,
    loadHistory,
    restorePanel,
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
