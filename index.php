<?php
// ==========================================
// SPAプレイグラウンド Pro v4 (管理者用・データ展開修正版)
// ==========================================
$db_file = __DIR__ . '/playground.sqlite';

// SQLite3 データベース自動生成・初期化
try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS snippets (
        id TEXT PRIMARY KEY,
        code TEXT NOT NULL,
        thumbnail TEXT,
        updated_at INTEGER NOT NULL
    )");
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// APIリクエスト処理：ここに入ったら絶対にJSONだけを返して終了する
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['id']) && isset($input['code'])) {
            $stmt = $db->prepare("REPLACE INTO snippets (id, code, thumbnail, updated_at) VALUES (:id, :code, :thumbnail, :updated_at)");
            $stmt->execute([
                ':id' => $input['id'],
                ':code' => $input['code'],
                ':thumbnail' => $input['thumbnail'] ?? null,
                ':updated_at' => time() * 1000
            ]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['id'])) {
            $stmt = $db->prepare("DELETE FROM snippets WHERE id = :id");
            $stmt->execute([':id' => $input['id']]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'get_all') {
        $stmt = $db->query("SELECT id, thumbnail, updated_at FROM snippets ORDER BY updated_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_backup') {
        $stmt = $db->query("SELECT id, code, updated_at FROM snippets ORDER BY updated_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'import') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input)) {
            $db->beginTransaction();
            $stmt = $db->prepare("REPLACE INTO snippets (id, code, thumbnail, updated_at) VALUES (:id, :code, :thumbnail, :updated_at)");
            foreach ($input as $item) {
                if (isset($item['id']) && isset($item['code'])) {
                    $stmt->execute([
                        ':id' => $item['id'],
                        ':code' => $item['code'],
                        ':thumbnail' => $item['thumbnail'] ?? null,
                        ':updated_at' => $item['updated_at'] ?? (time() * 1000)
                    ]);
                }
            }
            $db->commit();
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// URLパラメータ経由の特定スニペット初期ロード
$initial_code = "";
$initial_id = "";
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT id, code FROM snippets WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $initial_id = $res['id'];
        $initial_code = $res['code'];
    }
}
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ローカルSPAプレイグラウンド Pro v4 (Manager)</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        select, button, input[type="file"] { background: #3e3e42; color: #fff; border: 1px solid #555; padding: 6px 10px; border-radius: 4px; font-size: 13px; cursor: pointer; }
        select:hover, button:hover { background: #505055; }
        .btn-run { background: #007bff; border-color: #0069d9; font-weight: bold; }
        .btn-run:hover { background: #0062cc; }
        .btn-danger { background: #dc3545; border-color: #bd2130; }
        .btn-danger:hover { background: #c82333; }
        .btn-toggle { background: #28a745; border-color: #218838; }
        .btn-toggle:hover { background: #218838; }
        .btn-backup { background: #6f42c1; border-color: #593b9b; }
        .btn-backup:hover { background: #5a32a3; }
        .btn-insurance { background: #e83e8c; border-color: #d11268; }
        .btn-insurance:hover { background: #bd105e; }
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
        .hidden-input { display: none; }
        dialog { background: #2d2d30; color: #fff; border: 1px solid #555; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        dialog::backdrop { background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(1px); }
        #snippetDialog { width: 80vw; max-width: 900px; max-height: 80vh; }
        #customConfirmDialog { width: 90vw; max-width: 420px; text-align: center; }
        .confirm-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
        .confirm-buttons button { padding: 8px 20px; font-size: 14px; min-width: 100px; }
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
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
    </style>
</head>
<body>

<header>
    <div class="file-controls">
        <button id="openDialogBtn" style="background:#007bff; font-weight:bold;">📋 作品一覧（サムネ）</button>
        <button id="newBtn">新規作成</button>
        <button id="saveFileBtn">HTML出力</button>
        <button id="loadFileBtn">導入</button>
        <input type="file" id="fileInput" class="hidden-input" accept=".html">
        
        <button id="exportDbBtn" class="btn-backup">📦 DB一括出力</button>
        <button id="importDbBtn" class="btn-backup">📥 DB一括導入</button>
        <input type="file" id="dbFileInput" class="hidden-input" accept=".json">
        
        <button id="recoverInsuranceBtn" class="btn-insurance">🩹 保険から復元</button>

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
        <span id="currentNameDisplay" style="margin-right: 15px; font-size: 13px; color: #aaa;">[新規スニペット]</span>
        <button id="toggleEditorBtn" class="btn-toggle">エディタ非表示</button>
        <button id="deleteBtn" class="btn-danger">削除</button>
        <button id="runBtn" class="btn-run">実行 (Ctrl+Enter)</button>
    </div>
</header>

<div class="main-container">
    <div class="editor-zone" id="editorZone">
        <div class="editor-container">
            <div id="highlightOverlay"><pre><code class="language-html" id="highlightCode"></code></pre></div>
            <textarea id="codeEditor" placeholder="ここにHTML/CSS/JS（SPA）を貼り付けてください..." spellcheck="false"></textarea>
        </div>
    </div>
    <div class="preview-zone" id="previewWrapper"></div>
</div>

<dialog id="snippetDialog">
    <div class="dialog-header">
        <h3>マイ・スニペット一覧 (SQLite3連携)</h3>
        <button id="closeDialogBtn" style="background:#555;">閉じる</button>
    </div>
    <div class="grid-container" id="gridContainer"></div>
</dialog>

<dialog id="customConfirmDialog">
    <h3 id="confirmTitle" style="margin-top: 5px;">確認</h3>
    <p id="confirmMessage" style="color: #ccc; font-size: 14px; line-height: 1.4; white-space: pre-wrap;"></p>
    <div class="confirm-buttons">
        <button id="confirmCancelBtn" style="background: #555; border: 1px solid #777;">キャンセル</button>
        <button id="confirmOkBtn" class="btn-danger">削除する</button>
    </div>
</dialog>

<div id="toast" class="toast"></div>

<script>
    const editor = document.getElementById('codeEditor');
    const highlightCode = document.getElementById('highlightCode');
    const highlightOverlay = document.getElementById('highlightOverlay');
    const editorZone = document.getElementById('editorZone');
    const previewWrapper = document.getElementById('previewWrapper');
    const runBtn = document.getElementById('runBtn');
    const newBtn = document.getElementById('newBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const saveFileBtn = document.getElementById('saveFileBtn');
    const loadFileBtn = document.getElementById('loadFileBtn');
    const fileInput = document.getElementById('fileInput');
    const exportDbBtn = document.getElementById('exportDbBtn');
    const importDbBtn = document.getElementById('importDbBtn');
    const dbFileInput = document.getElementById('dbFileInput');
    const recoverInsuranceBtn = document.getElementById('recoverInsuranceBtn');
    const toggleEditorBtn = document.getElementById('toggleEditorBtn');
    const fontSizeSelect = document.getElementById('fontSizeSelect');
    const openDialogBtn = document.getElementById('openDialogBtn');
    const closeDialogBtn = document.getElementById('closeDialogBtn');
    const snippetDialog = document.getElementById('snippetDialog');
    const gridContainer = document.getElementById('gridContainer');
    const currentNameDisplay = document.getElementById('currentNameDisplay');
    const toast = document.getElementById('toast');

    const customConfirmDialog = document.getElementById('customConfirmDialog');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmOkBtn = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');

    // PHP変数を安全にJSONオブジェクトとして展開
    let currentSnippetId = <?php echo json_encode($initial_id); ?>;
    const initialCodeData = <?php echo json_encode($initial_code); ?>;
    
    const lsBackupKey = "playground_auto_backup";

    // 安全に初期データをエディタにセット
    if (initialCodeData) {
        editor.value = initialCodeData;
        updateHighlight();
        currentNameDisplay.textContent = `[ ${currentSnippetId} ]`;
        setTimeout(runCode, 500);
    }

    function openCustomConfirm(title, message, isDanger = true) {
        return new Promise((resolve) => {
            document.getElementById('confirmTitle').textContent = title;
            confirmMessage.textContent = message;
            confirmOkBtn.className = isDanger ? 'btn-danger' : 'btn-run';
            confirmOkBtn.textContent = isDanger ? '削除する' : '実行する';
            customConfirmDialog.showModal();
            confirmOkBtn.onclick = () => { customConfirmDialog.close(); resolve(true); };
            confirmCancelBtn.onclick = () => { customConfirmDialog.close(); resolve(false); };
        });
    }

    function updateHighlight() {
        let text = editor.value;
        text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        highlightCode.innerHTML = text;
        Prism.highlightElement(highlightCode);
    }

    editor.addEventListener('input', updateHighlight);
    editor.addEventListener('scroll', () => {
        highlightOverlay.scrollTop = editor.scrollTop;
        highlightOverlay.scrollLeft = editor.scrollLeft;
    });

    editor.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + "  " + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 2;
            updateHighlight();
        }
    });

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

        iframe.onload = function() {
            setTimeout(() => {
                try {
                    html2canvas(doc.body, {
                        width: doc.body.scrollWidth,
                        height: doc.body.scrollHeight,
                        logging: false,
                        useCORS: true
                    }).then(canvas => {
                        const thumbCanvas = document.createElement('canvas');
                        thumbCanvas.width = 320;
                        thumbCanvas.height = 180;
                        const ctx = thumbCanvas.getContext('2d');
                        ctx.drawImage(canvas, 0, 0, thumbCanvas.width, thumbCanvas.height);
                        saveToSQLite(thumbCanvas.toDataURL('image/png'));
                    });
                } catch (err) {
                    saveToSQLite(null);
                }
            }, 800);
        };
    }

    function saveToSQLite(thumbnailDataUrl) {
        if (!currentSnippetId) {
            const now = new Date();
            currentSnippetId = "Snippet_" + now.getFullYear() + String(now.getMonth()+1).padStart(2, '0') + String(now.getDate()).padStart(2, '0') + "_" + now.toTimeString().split(' ')[0].replace(/:/g, '');
        }

        const payload = {
            id: currentSnippetId,
            code: editor.value,
            thumbnail: thumbnailDataUrl
        };

        fetch('?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentNameDisplay.textContent = `[ ${currentSnippetId} ]`;
                saveToLocalStorageBackup();
            }
        });
    }

    function saveToLocalStorageBackup() {
        fetch('?action=get_backup', { method: 'POST' })
        .then(res => res.json())
        .then(list => {
            try {
                localStorage.setItem(lsBackupKey, JSON.stringify(list));
            } catch(e) {
                console.warn("LocalStorage容量上限のためローカル保険をスキップしました");
            }
        });
    }

    recoverInsuranceBtn.addEventListener('click', async () => {
        const lsDataStr = localStorage.getItem(lsBackupKey);
        if (!lsDataStr) { showToast("LocalStorage内に保険データが見つかりません。"); return; }

        const confirmed = await openCustomConfirm("保険データからの復元", "LocalStorage内のバックアップをサーバー(SQLite)へ一括書き戻します。よろしいですか？", false);
        if (!confirmed) return;

        fetch('?action=import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: lsDataStr
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) { showToast("保険データからSQLiteへ復元が完了しました！"); newBtn.click(); }
        });
    });

    exportDbBtn.addEventListener('click', () => {
        fetch('?action=get_backup', { method: 'POST' })
        .then(res => res.json())
        .then(allData => {
            const jsonStr = JSON.stringify(allData, null, 2);
            const blob = new Blob([jsonStr], { type: "application/json" });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `playground_sqlite_backup_${new Date().toISOString().slice(0,10)}.json`;
            a.click();
            URL.revokeObjectURL(a.href);
            showToast("DBバックアップを書き出しました！");
        });
    });

    importDbBtn.addEventListener('click', () => { dbFileInput.click(); });
    dbFileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const confirmed = await openCustomConfirm("一括インポートの確認", "JSONデータをインポートします。同名のスニペットは上書きされます。よろしいですか？", false);
        if (!confirmed) { dbFileInput.value = ''; return; }

        const reader = new FileReader();
        reader.onload = function(evt) {
            fetch('?action=import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: evt.target.result
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) { showToast("インポートが完了しました！"); newBtn.click(); }
            });
        };
        reader.readAsText(file);
        dbFileInput.value = '';
    });

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
                    const viewUrl = window.location.origin + window.location.pathname.replace('index.php', '') + "view.php?id=" + encodeURIComponent(data.id);
                    navigator.clipboard.writeText(viewUrl).then(() => { showToast("共有リンクURL(view.php)をコピーしました！"); });
                });
                
                const delBtn = document.createElement('button');
                delBtn.textContent = '削除';
                delBtn.className = 'btn-danger';
                delBtn.addEventListener('click', async (evt) => {
                    evt.stopPropagation();
                    const confirmed = await openCustomConfirm("スニペットの削除", `「${data.id}」をサーバーから完全に削除してもよろしいですか？`);
                    if (confirmed) {
                        fetch('?action=delete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: data.id })
                        })
                        .then(res => res.json())
                        .then(resData => {
                            if (resData.success) { card.remove(); showToast("削除しました"); if(currentSnippetId === data.id) newBtn.click(); }
                        });
                    }
                });
                
                actions.appendChild(copyLinkBtn);
                actions.appendChild(delBtn);
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

    newBtn.addEventListener('click', () => {
        currentSnippetId = "";
        editor.value = '';
        updateHighlight();
        previewWrapper.innerHTML = '';
        currentNameDisplay.textContent = '[新規スニペット]';
        window.history.replaceState(null, '', window.location.origin + window.location.pathname);
        editor.focus();
    });

    deleteBtn.addEventListener('click', async () => {
        if (!currentSnippetId) return;
        const confirmed = await openCustomConfirm("スニペットの削除", `現在編集中の「${currentSnippetId}」を削除しますか？`);
        if (confirmed) {
            fetch('?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentSnippetId })
            })
            .then(res => res.json())
            .then(data => { if (data.success) { showToast("削除しました"); newBtn.click(); } });
        }
    });

    saveFileBtn.addEventListener('click', () => {
        if (!editor.value) return;
        let filename = currentSnippetId || "snippet";
        if (!filename.endsWith(".html")) filename += ".html";
        const blob = new Blob([editor.value], { type: "text/html" });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    });

    loadFileBtn.addEventListener('click', () => { fileInput.click(); });
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            editor.value = evt.target.result;
            updateHighlight();
            currentSnippetId = file.name.replace(".html", "");
            runCode();
        };
        reader.readAsText(file);
        fileInput.value = '';
    });

    runBtn.addEventListener('click', runCode);
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runCode(); }
    });
</script>
</body>
</html>