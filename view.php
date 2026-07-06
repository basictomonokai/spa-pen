<?php
// ==========================================
// SPAプレイグラウンド Pro v4 (第三者閲覧用・安全データ展開版)
// ==========================================
$db_file = __DIR__ . '/playground.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー");
}

// 閲覧用API (SELECTのみ配置、書き込み系は物理的に記述しない)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'get_all') {
        $stmt = $db->query("SELECT id, thumbnail, updated_at FROM snippets ORDER BY updated_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

// 初期ロード処理
$initial_code = "";
$initial_id = "";
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT id, code FROM snippets WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $initial_id = $res['id'];
        $view_code_raw = $res['code'];
        // view.php専用：閲覧画面ではインラインスクリプトの衝突を防ぐため最低限の処理をして渡す
        $initial_code = $view_code_raw; 
    }
}
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPAプレイグラウンド ビューアー</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: sans-serif; background: #202124; color: #fff; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        header { background: #2d2d30; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #3c3c3c; height: 60px; flex-shrink: 0; }
        .file-controls { display: flex; align-items: center; gap: 8px; }
        select, button { background: #3e3e42; color: #fff; border: 1px solid #555; padding: 6px 10px; border-radius: 4px; font-size: 13px; cursor: pointer; }
        select:hover, button:hover { background: #505055; }
        .btn-toggle { background: #28a745; border-color: #218838; }
        .btn-toggle:hover { background: #218838; }
        .main-container { display: flex; flex: 1; height: calc(100vh - 60px); width: 100%; position: relative; }
        .editor-zone { width: 50%; height: 100%; border-right: 2px solid #3c3c3c; position: relative; background: #1e1e1e; transition: width 0.2s ease-out; }
        .editor-zone.hidden { width: 0% !important; border-right: none; overflow: hidden; }
        .editor-container { position: relative; width: 100%; height: 100%; }
        #codeEditor, #highlightOverlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0; padding: 15px; border: none; font-family: 'Courier New', Courier, monospace; font-size: 14px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; overflow-y: auto; background: transparent; }
        #codeEditor { color: transparent; caret-color: #fff; resize: none; outline: none; z-index: 2; }
        #highlightOverlay { color: #d4d4d4; z-index: 1; pointer-events: none; }
        #highlightOverlay pre, #highlightOverlay code { margin: 0; padding: 0; background: transparent !important; font-family: inherit; font-size: inherit; line-height: inherit; white-space: pre-wrap; word-wrap: break-word; }
        .preview-zone { flex: 1; height: 100%; background: #fff; position: relative; }
        #previewFrame { width: 100%; height: 100%; border: none; background: #fff; }
        dialog { background: #2d2d30; color: #fff; border: 1px solid #555; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); width: 80vw; max-width: 900px; max-height: 80vh; }
        dialog::backdrop { background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(1px); }
        .dialog-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #444; padding-bottom: 10px; }
        .dialog-header h3 { margin: 0; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; overflow-y: auto; max-height: calc(80vh - 100px); padding-bottom: 10px; }
        .snippet-card { background: #1e1e1e; border: 1px solid #444; border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: transform 0.1s, border-color 0.1s; }
        .snippet-card:hover { transform: translateY(-2px); border-color: #007bff; }
        .thumb-wrapper { width: 100%; aspect-ratio: 16 / 9; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid #333; }
        .thumb-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .thumb-placeholder { color: #666; font-size: 14px; }
        .card-info { padding: 10px; display: flex; flex-direction: column; gap: 8px; }
        .card-title { font-size: 13px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ddd; }
        .card-actions { display: flex; justify-content: space-between; gap: 5px; }
        .card-actions button { padding: 4px 8px; font-size: 11px; flex: 1; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #17a2b8; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
        .placeholder-notice { display: flex; height: 100%; justify-content: center; align-items: center; color: #333; font-size: 16px; font-weight: bold; background: #f8f9fa; text-align: center; padding: 20px; }
    </style>
</head>
<body>

<header>
    <div class="file-controls">
        <button id="openDialogBtn" style="background:#007bff; font-weight:bold;">📋 作品一覧（サムネ）</button>
        <span style="margin-left:10px; font-size:13px; color:#aaa;">文字サイズ:</span>
        <select id="fontSizeSelect" style="min-width:65px; width:65px;">
            <option value="12px">12px</option>
            <option value="14px" selected>14px</option>
            <option value="16px">16px</option>
            <option value="18px">18px</option>
            <option value="20px">20px</option>
            <option value="24px">24px</option>
        </select>
    </div>
    <div>
        <span id="currentNameDisplay" style="margin-right: 15px; font-size: 13px; color: #aaa;">[閲覧モード]</span>
        <button id="toggleEditorBtn" class="btn-toggle">エディタ非表示</button>
    </div>
</header>

<div class="main-container">
    <div class="editor-zone" id="editorZone">
        <div class="editor-container">
            <div id="highlightOverlay"><pre><code class="language-html" id="highlightCode"></code></pre></div>
            <textarea id="codeEditor" readonly placeholder="作品一覧からスニペットを選択してください..." spellcheck="false"></textarea>
        </div>
    </div>
    <div class="preview-zone" id="previewWrapper">
        <div class="placeholder-notice" id="placeholderNotice">左上の「📋 作品一覧」からスニペットを選択してください。</div>
    </div>
</div>

<dialog id="snippetDialog">
    <div class="dialog-header">
        <h3>公開スニペット一覧</h3>
        <button id="closeDialogBtn" style="background:#555;">閉じる</button>
    </div>
    <div class="grid-container" id="gridContainer"></div>
</dialog>

<div id="toast" class="toast"></div>

<script>
    // 1. PHPデータを最上部で安全に取得
    const initialSnippetId = <?php echo json_encode($initial_id); ?>;
    const initialCodeData = <?php echo json_encode($initial_code); ?>;

    // 2. DOM要素の取得
    const editor = document.getElementById('codeEditor');
    const highlightCode = document.getElementById('highlightCode');
    const highlightOverlay = document.getElementById('highlightOverlay');
    const editorZone = document.getElementById('editorZone');
    const previewWrapper = document.getElementById('previewWrapper');
    const toggleEditorBtn = document.getElementById('toggleEditorBtn');
    const fontSizeSelect = document.getElementById('fontSizeSelect');
    const openDialogBtn = document.getElementById('openDialogBtn');
    const closeDialogBtn = document.getElementById('closeDialogBtn');
    const snippetDialog = document.getElementById('snippetDialog');
    const gridContainer = document.getElementById('gridContainer');
    const currentNameDisplay = document.getElementById('currentNameDisplay');
    const toast = document.getElementById('toast');
    const placeholderNotice = document.getElementById('placeholderNotice');

    let currentSnippetId = initialSnippetId || "";

    // 3. 画面構築完了（DOMContentLoaded）した後に初期ロードを実行する
    document.addEventListener('DOMContentLoaded', () => {
        if (initialCodeData) {
            editor.value = initialCodeData;
            updateHighlight();
            currentNameDisplay.textContent = `[ ${currentSnippetId} ]`;
            if (placeholderNotice) placeholderNotice.remove();
            setTimeout(runCode, 200);
        }
    });

    function updateHighlight() {
        if (!editor || !highlightCode) return;
        let text = editor.value;
        text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        highlightCode.innerHTML = text;
        Prism.highlightElement(highlightCode);
    }

    if (editor) {
        editor.addEventListener('scroll', () => {
            highlightOverlay.scrollTop = editor.scrollTop;
            highlightOverlay.scrollLeft = editor.scrollLeft;
        });
    }

    toggleEditorBtn.addEventListener('click', () => {
        editorZone.classList.toggle('hidden');
        toggleEditorBtn.textContent = editorZone.classList.contains('hidden') ? 'エディタ表示' : 'エディタ非表示';
    });

    fontSizeSelect.addEventListener('change', () => {
        editor.style.fontSize = fontSizeSelect.value;
        highlightOverlay.style.fontSize = fontSizeSelect.value;
    });

    function runCode() {
        const code = editor.value;
        if (!code.trim()) return;

        previewWrapper.innerHTML = '';
        const iframe = document.createElement('iframe');
        iframe.id = 'previewFrame';
        iframe.sandbox = "allow-downloads allow-forms allow-modals allow-pointer-lock allow-popups allow-presentation allow-same-origin allow-scripts";
        previewWrapper.appendChild(iframe);

        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(code);
        doc.close();
    }

    openDialogBtn.addEventListener('click', () => {
        gridContainer.innerHTML = '';
        fetch('?action=get_all', { method: 'POST' })
        .then(res => res.json())
        .then(snippets => {
            snippets.forEach(data => {
                const card = document.createElement('div');
                card.className = 'snippet-card';
                
                const thumbWrapper = document.createElement('div');
                thumbWrapper.className = 'thumb-wrapper';
                if (data.thumbnail) {
                    const img = document.createElement('img');
                    img.src = data.thumbnail;
                    thumbWrapper.appendChild(img);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'thumb-placeholder';
                    placeholder.textContent = 'No Image';
                    thumbWrapper.appendChild(placeholder);
                }
                
                const cardInfo = document.createElement('div');
                cardInfo.className = 'card-info';
                const title = document.createElement('div');
                title.className = 'card-title';
                title.textContent = data.id;
                
                const actions = document.createElement('div');
                actions.className = 'card-actions';
                
                const copyLinkBtn = document.createElement('button');
                copyLinkBtn.textContent = '共有リンクをコピー';
                copyLinkBtn.style.background = '#17a2b8';
                copyLinkBtn.addEventListener('click', (evt) => {
                    evt.stopPropagation();
                    // 現在のホスト名＋パス名（view.php単体）を維持してパラメータを付与
                    const viewUrl = window.location.origin + window.location.pathname + "?id=" + encodeURIComponent(data.id);
                    navigator.clipboard.writeText(viewUrl).then(() => { showToast("リンクをコピーしました！"); });
                });
                
                actions.appendChild(copyLinkBtn);
                cardInfo.appendChild(title);
                cardInfo.appendChild(actions);
                card.appendChild(thumbWrapper);
                card.appendChild(cardInfo);
                
                card.addEventListener('click', () => {
                    window.location.search = "?id=" + encodeURIComponent(data.id);
                });
                
                gridContainer.appendChild(card);
            });
        });
        snippetDialog.showModal();
    });

    closeDialogBtn.addEventListener('click', () => { snippetDialog.close(); });

    function showToast(msg) {
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 2500);
    }
</script>
</body>
</html>