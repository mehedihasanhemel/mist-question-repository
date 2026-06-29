<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAnyLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('Not found'); }

$stmt = db()->prepare("SELECT * FROM qrepo_files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); exit('File not found'); }

$ext     = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
$isPdf   = ($ext === 'pdf');
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']);
$title   = htmlspecialchars($file['title'] ?: $file['original_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;font-family:'Segoe UI',system-ui,sans-serif}
body{background:#525659;display:flex;flex-direction:column}

/* ── Toolbar ── */
#toolbar{
    height:44px;background:#1a2a4a;color:#fff;
    display:flex;align-items:center;gap:.35rem;padding:0 .65rem;
    flex-shrink:0;user-select:none;z-index:10;
    border-bottom:1px solid rgba(255,255,255,.08)
}
.tb-title{
    font-size:.8rem;font-weight:600;color:rgba(255,255,255,.8);
    flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    padding:0 .4rem
}
.tb-btn{
    background:rgba(255,255,255,.1);border:none;color:#fff;border-radius:6px;
    width:30px;height:30px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:.88rem;transition:background .15s;flex-shrink:0
}
.tb-btn:hover:not(:disabled){background:rgba(255,255,255,.22)}
.tb-btn:disabled{opacity:.3;cursor:default}
.tb-sep{width:1px;height:20px;background:rgba(255,255,255,.12);margin:0 .15rem;flex-shrink:0}
.tb-page{
    font-size:.76rem;color:rgba(255,255,255,.7);white-space:nowrap;
    display:flex;align-items:center;gap:.25rem;flex-shrink:0
}
.tb-page input{
    width:34px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
    color:#fff;text-align:center;border-radius:5px;font-size:.76rem;padding:.12rem 0;outline:none
}
.tb-page input:focus{border-color:rgba(255,255,255,.45)}
.tb-zoom{font-size:.74rem;color:rgba(255,255,255,.6);width:40px;text-align:center;flex-shrink:0}
.print-btn{
    background:rgba(201,168,76,.25);border:none;color:#e8c96d;border-radius:6px;
    height:30px;padding:0 .75rem;font-size:.78rem;font-weight:700;cursor:pointer;
    display:flex;align-items:center;gap:.3rem;white-space:nowrap;
    transition:background .15s;flex-shrink:0
}
.print-btn:hover{background:rgba(201,168,76,.45)}

/* ── Page viewport ── */
#viewer{
    flex:1;overflow-y:auto;overflow-x:auto;
    display:flex;flex-direction:column;align-items:center;
    padding:1rem .5rem;gap:.65rem;
}
#viewer canvas{
    display:block;box-shadow:0 4px 20px rgba(0,0,0,.55);
    background:#fff;
}

/* ── States ── */
#loadingOverlay{
    position:absolute;inset:44px 0 0;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    color:rgba(255,255,255,.75);gap:.65rem;font-size:.88rem;background:#525659;
    pointer-events:none;z-index:5
}
.spinner{
    width:34px;height:34px;border:3px solid rgba(255,255,255,.15);
    border-top-color:#c9a84c;border-radius:50%;animation:spin .7s linear infinite
}
@keyframes spin{to{transform:rotate(360deg)}}
#errorOverlay{
    position:absolute;inset:44px 0 0;
    display:none;flex-direction:column;align-items:center;justify-content:center;
    color:rgba(255,255,255,.75);gap:.6rem;font-size:.88rem;text-align:center;
    padding:1rem;z-index:5
}
#errorOverlay i{font-size:2.5rem;color:#f87171}

/* ── Image viewer ── */
#imgViewer{
    flex:1;display:flex;align-items:center;justify-content:center;
    overflow:auto;padding:1rem
}
#imgViewer img{max-width:100%;max-height:100%;box-shadow:0 4px 20px rgba(0,0,0,.55)}

/* ── Print ── */
@media print{
    #toolbar,#loadingOverlay,#errorOverlay{display:none!important}
    html,body{overflow:visible;height:auto;background:#fff}
    #viewer{overflow:visible;padding:0;gap:0;display:block}
    #viewer canvas{
        width:100%!important;height:auto!important;
        box-shadow:none;page-break-after:always;display:block
    }
    #imgViewer{padding:0}
    #imgViewer img{max-width:100%;max-height:none}
}
</style>
</head>
<body>

<!-- Toolbar -->
<div id="toolbar">
    <?php if ($isPdf): ?>
    <button class="tb-btn" id="btnPrev" disabled title="Previous page"><i class="bi bi-chevron-up"></i></button>
    <button class="tb-btn" id="btnNext" disabled title="Next page"><i class="bi bi-chevron-down"></i></button>
    <div class="tb-sep"></div>
    <div class="tb-page">
        <input type="number" id="pageInput" value="1" min="1">
        <span>/ <span id="pageTotal">—</span></span>
    </div>
    <div class="tb-sep"></div>
    <button class="tb-btn" id="btnZoomOut" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
    <span class="tb-zoom" id="zoomLabel">150%</span>
    <button class="tb-btn" id="btnZoomIn"  title="Zoom in"><i class="bi bi-zoom-in"></i></button>
    <button class="tb-btn" id="btnFitW"   title="Fit to width"><i class="bi bi-arrows-expand"></i></button>
    <div class="tb-sep"></div>
    <?php endif; ?>
    <span class="tb-title"><?= $title ?></span>
    <button class="print-btn" id="printBtn">
        <i class="bi bi-printer-fill"></i> Print
    </button>
</div>

<?php if ($isPdf): ?>

<div id="viewer"></div>
<div id="loadingOverlay"><div class="spinner"></div>Loading…</div>
<div id="errorOverlay"><i class="bi bi-file-earmark-x"></i><span>Could not load this document.</span></div>

<script src="/qrepo/assets/pdfjs/pdf.min.js"></script>
<script>
'use strict';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/qrepo/assets/pdfjs/pdf.worker.min.js';

const FILE_URL = '/qrepo/view.php?id=<?= $id ?>';

const viewer    = document.getElementById('viewer');
const loadOver  = document.getElementById('loadingOverlay');
const errOver   = document.getElementById('errorOverlay');
const btnPrev   = document.getElementById('btnPrev');
const btnNext   = document.getElementById('btnNext');
const btnZoomIn = document.getElementById('btnZoomIn');
const btnZoomOut= document.getElementById('btnZoomOut');
const btnFitW   = document.getElementById('btnFitW');
const pageInput = document.getElementById('pageInput');
const pageTotal = document.getElementById('pageTotal');
const zoomLabel = document.getElementById('zoomLabel');

let pdfDoc   = null;
let scale    = 1.5;
let rendering= false;
let canvases = [];

/* ── Load ── */
pdfjsLib.getDocument({ url: FILE_URL, withCredentials: true }).promise
    .then(pdf => {
        pdfDoc = pdf;
        pageTotal.textContent = pdf.numPages;
        pageInput.max = pdf.numPages;
        loadOver.style.display = 'none';
        renderAll();
    })
    .catch(err => {
        console.error(err);
        loadOver.style.display = 'none';
        errOver.style.display = 'flex';
    });

/* ── Render all pages ── */
async function renderAll() {
    if (rendering) return;
    rendering = true;
    viewer.innerHTML = '';
    canvases = [];
    for (let n = 1; n <= pdfDoc.numPages; n++) {
        const page = await pdfDoc.getPage(n);
        const vp   = page.getViewport({ scale });
        const cvs  = document.createElement('canvas');
        cvs.width  = vp.width;
        cvs.height = vp.height;
        cvs.dataset.page = n;
        viewer.appendChild(cvs);
        canvases.push(cvs);
        await page.render({ canvasContext: cvs.getContext('2d'), viewport: vp }).promise;
    }
    rendering = false;
    zoomLabel.textContent = Math.round(scale * 100) + '%';
    viewer.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

/* ── Scroll spy ── */
let currentPage = 1;
function onScroll() {
    const mid = viewer.scrollTop + viewer.clientHeight / 2;
    let best  = canvases[0];
    for (const c of canvases) { if (c.offsetTop <= mid) best = c; }
    currentPage = parseInt(best.dataset.page);
    pageInput.value = currentPage;
    btnPrev.disabled = (currentPage <= 1);
    btnNext.disabled = (currentPage >= pdfDoc.numPages);
}

function scrollTo(n) {
    const c = canvases[n - 1];
    if (c) viewer.scrollTo({ top: c.offsetTop - 10, behavior: 'smooth' });
}

/* ── Nav ── */
btnPrev.addEventListener('click', () => scrollTo(Math.max(1, currentPage - 1)));
btnNext.addEventListener('click', () => scrollTo(Math.min(pdfDoc.numPages, currentPage + 1)));
pageInput.addEventListener('change', () => {
    const n = Math.max(1, Math.min(parseInt(pageInput.value) || 1, pdfDoc.numPages));
    pageInput.value = n;
    scrollTo(n);
});

/* ── Zoom ── */
function setScale(s) {
    scale = Math.max(0.5, Math.min(4, s));
    renderAll();
}
btnZoomIn.addEventListener('click',  () => setScale(scale + 0.25));
btnZoomOut.addEventListener('click', () => setScale(scale - 0.25));
btnFitW.addEventListener('click', () => {
    if (!pdfDoc) return;
    pdfDoc.getPage(1).then(page => {
        const vp = page.getViewport({ scale: 1 });
        setScale((viewer.clientWidth - 20) / vp.width);
    });
});

/* ── Print ── */
document.getElementById('printBtn').addEventListener('click', () => window.print());
</script>

<?php elseif ($isImage): ?>

<div id="imgViewer">
    <img src="/qrepo/view.php?id=<?= $id ?>" alt="<?= $title ?>">
</div>
<script>
document.getElementById('printBtn').addEventListener('click', () => window.print());
</script>

<?php else: ?>

<div id="errorOverlay" style="display:flex">
    <i class="bi bi-file-earmark-x"></i>
    <span>This file type cannot be previewed in the browser.</span>
</div>
<script>
document.getElementById('printBtn').addEventListener('click', () => {});
</script>

<?php endif; ?>

</body>
</html>
