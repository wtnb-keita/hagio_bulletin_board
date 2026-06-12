/**
 * パネル描画モジュール（ビュー画面・管理プレビュー共用）
 */

const PanelRender = (() => {

  function escHtml(s) {
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  function isNoticeActive(notice) {
    const today = new Date();
    today.setHours(0,0,0,0);
    if (notice.startDate) {
      const s = new Date(notice.startDate);
      s.setHours(0,0,0,0);
      if (today < s) return false;
    }
    if (notice.endDate) {
      const e = new Date(notice.endDate);
      e.setHours(0,0,0,0);
      if (today > e) return false;
    }
    return true;
  }

  function calcElapsedDays(startDate) {
    const start = new Date(startDate);
    start.setHours(0,0,0,0);
    const now = new Date();
    now.setHours(0,0,0,0);
    return Math.max(0, Math.floor((now - start) / 86400000));
  }

  // ---- 各パネル種別の body HTML ----

  function mediaBody(panel) {
    const c = panel.content || {};
    if (!c.filePath) {
      return `<div class="panel-body"><div class="no-data">コンテンツなし</div></div>`;
    }
    const media = c.fileType === 'application/pdf'
      ? `<iframe src="${c.filePath}#toolbar=0&navpanes=0&scrollbar=0" title="${escHtml(c.fileName)}"></iframe>`
      : `<img src="${c.filePath}" alt="${escHtml(c.fileName)}">`;
    const label = c.label
      ? `<div class="panel-label">${escHtml(c.label)}</div>`
      : '';
    return `<div class="panel-body">${media}${label}</div>`;
  }

  function textBody(panel) {
    const c  = panel.content || {};
    const wm = c.vertical ? 'vertical-rl' : 'horizontal-tb';
    const fs = (c.fontSize || 14) + 'px';
    return `<div class="panel-body" style="--writing-mode:${wm};font-size:${fs}">${escHtml(c.text)}</div>`;
  }

  function accidentBody(panel) {
    const c = panel.content || {};
    const target  = c.targetDays || 1500;
    const elapsed = calcElapsedDays(c.startDate || new Date().toISOString().split('T')[0]);
    const total   = elapsed + (c.initialDays || 0);
    const today   = new Date();
    const month   = today.getMonth() + 1;
    const day     = today.getDate();
    return `
      <div class="panel-body">
        <div class="accident-header">
          <span class="accident-header-text">無災害</span>
          <span class="accident-plus">＋</span>
          <span class="accident-header-text">記録表</span>
        </div>
        <div class="accident-table">
          <div class="accident-row">
            <span class="accident-row-label">目標日数</span>
            <span class="accident-row-value">${target.toLocaleString()}<span class="accident-unit">日</span></span>
          </div>
          <div class="accident-row accent-row">
            <span class="accident-row-label">${month}月&nbsp;${day}日</span>
            <span class="accident-row-value accent">${total.toLocaleString()}<span class="accident-unit">日</span></span>
          </div>
        </div>
      </div>`;
  }

  function noticeBody(panel) {
    const c       = panel.content || {};
    const notices = (c.notices || []).filter(isNoticeActive);
    if (!notices.length) {
      return `<div class="panel-body"><div class="no-data">告知なし</div></div>`;
    }
    const items = notices.map(n => `
      <div class="notice-item level-${n.level || 1}">
        ${n.title ? `<div class="notice-item-title">${escHtml(n.title)}</div>` : ''}
        ${(n.startDate || n.endDate) ? `<div class="notice-item-period">${n.startDate||''}〜${n.endDate||''}</div>` : ''}
        ${n.text ? `<div class="notice-item-text">${escHtml(n.text)}</div>` : ''}
      </div>`).join('');
    return `<div class="panel-body">${items}</div>`;
  }

  function disasterBody(panel) {
    const c = panel.content || {};
    const items = c.items || [];
    if (!items.length) {
      return `<div class="panel-body"><div class="no-data">🚨 速報なし</div></div>`;
    }

    const itemHtml = items.map((item, i) => {
      let inner;
      if (item.fileType && item.fileType.startsWith('image/')) {
        inner = `<img src="${item.filePath}" alt="${escHtml(item.fileName)}" style="width:100%;height:100%;object-fit:contain;display:block;">`;
      } else if (item.fileType === 'application/pdf') {
        inner = `<iframe src="${item.filePath}#toolbar=0&navpanes=0&scrollbar=0" title="${escHtml(item.fileName)}" style="width:100%;height:100%;border:none;background:#fff;"></iframe>`;
      } else {
        inner = `<div style="padding:8px;color:#e0e0e0;font-size:14px;white-space:pre-wrap;word-break:break-all;">${escHtml(item.text||'')}</div>`;
      }
      return `<div class="disaster-item" data-idx="${i}" style="${i===0?'':'display:none'}">${inner}</div>`;
    }).join('');

    const grid = items.length <= 4
      ? (() => {
          const cols = items.length <= 2 ? items.length : 2;
          const rows = Math.ceil(items.length / cols);
          return `<div class="disaster-grid" style="display:grid;grid-template-columns:repeat(${cols},1fr);grid-template-rows:repeat(${rows},1fr);gap:4px;width:100%;height:100%;">
            ${items.map(item => {
              if (item.fileType && item.fileType.startsWith('image/')) {
                return `<div style="overflow:hidden;"><img src="${item.filePath}" alt="${escHtml(item.fileName)}" style="width:100%;height:100%;object-fit:contain;display:block;"></div>`;
              } else if (item.fileType === 'application/pdf') {
                return `<div style="overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;">📄<br><span style="font-size:10px">${escHtml(item.fileName||'PDF')}</span></div>`;
              } else {
                return `<div style="padding:4px;color:#e0e0e0;font-size:11px;overflow:hidden;">${escHtml((item.text||'').slice(0,100))}</div>`;
              }
            }).join('')}
          </div>`;
        })()
      : `<div class="disaster-slideshow" style="position:relative;width:100%;height:100%;">${itemHtml}</div>`;

    return `<div class="panel-body">${grid}</div>`;
  }

  function responsibleBody(panel) {
    const c    = panel.content || {};
    const role = escHtml(c.role || '化学物質管理者');
    const name = escHtml(c.name || '');
    const fs   = (c.fontSize || 40) + 'px';
    return `<div class="panel-body" style="display:flex;width:100%;height:100%;background:#FFD700;padding:0;overflow:hidden;">
      <div style="flex:1;background:#fff;margin:8%;display:flex;align-items:center;justify-content:center;writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#111;overflow:hidden;border:2px solid #e0b800;">${name}</div>
      <div style="writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#111;padding:6% 5% 6% 2%;white-space:nowrap;align-self:stretch;">${role}</div>
    </div>`;
  }

  // ---- 公開API ----

  /**
   * パネルデータから DOM 要素を生成して返す
   * @param {Object} panel
   * @returns {HTMLElement}
   */
  function createPanelElement(panel) {
    const div = document.createElement('div');
    div.className = `panel panel-${panel.type}`;
    div.style.left   = `${panel.x   || 0}px`;
    div.style.top    = `${panel.y   || 0}px`;
    div.style.width  = `${panel.width  || 300}px`;
    div.style.height = `${panel.height || 200}px`;

    const titleHtml = panel.title
      ? `<div class="panel-title">${escHtml(panel.title)}</div>`
      : '';

    const bodyHtml = {
      media:       mediaBody,
      text:        textBody,
      accident:    accidentBody,
      notice:      noticeBody,
      disaster:    disasterBody,
      responsible: responsibleBody,
    }[panel.type]?.(panel) ?? '';

    div.innerHTML = titleHtml + bodyHtml;

    if (panel.type === 'disaster') {
      const c = panel.content || {};
      const items = c.items || [];
      if ((c.slideshowEnabled || items.length > 4) && items.length > 1) {
        const interval = Math.max(1, c.slideshowInterval || 5) * 1000;
        let cur = 0;
        setInterval(() => {
          const els = div.querySelectorAll('.disaster-item');
          if (!els.length) return;
          els[cur].style.display = 'none';
          cur = (cur + 1) % els.length;
          els[cur].style.display = '';
        }, interval);
      }
    }

    return div;
  }

  /**
   * ボード要素にパネルを全描画する
   * @param {HTMLElement} boardEl
   * @param {Object[]} panels
   */
  function renderBoard(boardEl, panels) {
    boardEl.innerHTML = '';
    (panels || []).forEach(p => boardEl.appendChild(createPanelElement(p)));
  }

  return { createPanelElement, renderBoard, escHtml };
})();
