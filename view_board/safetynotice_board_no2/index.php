<?php
$baseUrl = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=1800">
<title>安全掲示板 No.2</title>
<link rel="stylesheet" href="../../assets/css/common.css">
<link rel="stylesheet" href="../../assets/css/view-board.css">
</head>
<body>
<div id="board"></div>
<div id="clock"></div>

<script>const BASE_URL = '<?= $baseUrl ?>';</script>
<script src="../../assets/js/api.js"></script>
<script src="../../assets/js/panel-render.js"></script>
<script>
const BOARD_KEY = 'safety_board_2';

async function applyBoardSize() {
  try {
    const cfg = await API.getBoard(BOARD_KEY);
    const w = cfg.width  || 1800;
    const h = cfg.height || 900;
    document.body.style.width  = w + 'px';
    document.body.style.height = h + 'px';
    const board = document.getElementById('board');
    board.style.width  = w + 'px';
    board.style.height = h + 'px';
    document.querySelector('meta[name="viewport"]').content = `width=${w}`;
  } catch(e) {}
}

async function refresh() {
  try {
    const data = await API.getPanels(BOARD_KEY);
    PanelRender.renderBoard(document.getElementById('board'), data.panels || []);
  } catch(e) {
    console.error('データ取得失敗:', e);
  }
}

function updateClock() {
  const now = new Date();
  const pad = n => String(n).padStart(2,'0');
  document.getElementById('clock').textContent =
    `${now.getFullYear()}/${pad(now.getMonth()+1)}/${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

applyBoardSize();
refresh();
updateClock();
setInterval(updateClock, 1000);
setInterval(refresh, 60000);

let lastVersion = 0;
async function checkVersion() {
  try {
    const res = await fetch(`${BASE_URL}/api/panels.php?board=${BOARD_KEY}&action=version`);
    const json = await res.json();
    const v = json.version || 0;
    if (lastVersion && v !== lastVersion) refresh();
    lastVersion = v;
  } catch(e) {}
}
checkVersion();
setInterval(checkVersion, 5000);
</script>
</body>
</html>
