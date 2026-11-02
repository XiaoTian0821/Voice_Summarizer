<?php
/**
 * upload.php
 * Audio file upload page with drag-and-drop.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid = current_user_id();

// Get any pending transcribed-but-not-summarized record
$stmt = db()->prepare(
    'SELECT id FROM summaries
     WHERE user_id = ? AND status = \'transcribed\'
     ORDER BY updated_at DESC LIMIT 1'
);
$stmt->execute([$uid]);
$pending = $stmt->fetch();

$page_title = 'Upload Audio';
$page_active = 'upload';
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<h2 class="mb-4 fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Upload Audio</h2>

<?php if ($pending): ?>
    <div class="alert alert-info d-flex align-items-center">
        <i class="fa-solid fa-circle-info me-2"></i>
        <div>You have a transcribed recording ready to summarize.
            <a href="summarize.php?id=<?= (int)$pending['id'] ?>" class="fw-bold ms-1">Go to summarize &rarr;</a>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <!-- Drop Zone -->
        <div id="dropZone"
             class="drop-zone text-center p-5 rounded-3 border border-2 border-dashed border-primary-25">
            <i class="fa-solid fa-file-audio fa-4x text-primary mb-3"></i>
            <h5 class="mb-2">Drag &amp; Drop an Audio File</h5>
            <p class="text-secondary mb-3">or click to browse your files</p>
            <input type="file"
                   id="audioFile"
                   accept=".mp3,.wav,.m4a,.webm,audio/mpeg,audio/wav,audio/wave,audio/x-m4a,audio/webm"
                   class="d-none">
            <label for="audioFile" class="btn btn-primary" id="browseBtn">
                <i class="fa-solid fa-folder-open me-1"></i> Browse Files
            </label>
        </div>

        <!-- File Info (hidden by default) -->
        <div id="fileInfo" class="mt-4 d-none">
            <div class="alert alert-light border">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong id="fName"></strong>
                        <span class="text-secondary small ms-2" id="fSize"></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="removeFile">
                        <i class="fa-solid fa-xmark me-1"></i> Remove
                    </button>
                </div>
                <!-- Progress bar -->
                <div class="progress mt-2 d-none" id="progressWrap" style="height:8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="progressBar" role="progressbar" style="width:0%">0%</div>
                </div>
            </div>
        </div>

        <!-- Status messages -->
        <div id="uploadStatus" class="mt-3"></div>

        <!-- Submit -->
        <button type="button" class="btn btn-primary mt-3" id="submitUpload"
                disabled style="display:none;">
            <i class="fa-solid fa-upload me-1"></i> Upload &amp; Transcribe
        </button>
    </div>
</div>

<script>
const MAX_SIZE = <?= MAX_AUDIO_BYTES ?>;
const allowedExt = <?= json_encode(ALLOWED_EXTENSIONS) ?>;

const dropZone     = document.getElementById('dropZone');
const fileInput    = document.getElementById('audioFile');
const fileInfo     = document.getElementById('fileInfo');
const submitBtn    = document.getElementById('submitUpload');
const statusDiv    = document.getElementById('uploadStatus');
let selectedFile   = null;

// Drag-and-drop
['dragover','dragenter'].forEach(evt =>
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    })
);
['dragleave','drop'].forEach(evt =>
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    })
);
dropZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length) handleFile(files[0]);
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) handleFile(fileInput.files[0]);
});

function handleFile(file) {
    // Validate extension
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExt.includes(ext)) {
        showStatus('error', `Unsupported format '.${ext}'. Allowed: ${allowedExt.join(', ')}`);
        return;
    }
    // Validate size
    if (file.size > MAX_SIZE) {
        showStatus('error', `File too large (${(file.size/1048576).toFixed(1)} MB). Max: ${(MAX_SIZE/1048576).toFixed(0)} MB.`);
        return;
    }
    selectedFile = file;
    document.getElementById('fName').textContent = file.name;
    document.getElementById('fSize').textContent = formatSize(file.size);
    fileInfo.classList.remove('d-none');
    submitBtn.style.display = '';
    submitBtn.disabled = false;
    showStatus('success', `Selected: <strong>${escapeHtml(file.name)}</strong> (${formatSize(file.size)})`);
}

document.getElementById('removeFile').addEventListener('click', () => {
    fileInput.value = '';
    selectedFile = null;
    fileInfo.classList.add('d-none');
    submitBtn.style.display = 'none';
    statusDiv.innerHTML = '';
});

submitBtn.addEventListener('click', () => {
    if (!selectedFile) return;
    submitUpload(selectedFile);
});

async function submitUpload(file) {
    const fd = new FormData();
    fd.append('audio_file', file);
    fd.append('csrf_token', window.CSRF_TOKEN);

    showStatus('info', 'Uploading and transcribing… this may take a moment.');
    submitBtn.disabled = true;
    document.getElementById('progressWrap').classList.remove('d-none');
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = '0%';

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'process_audio.php');
        xhr.setRequestHeader('X-CSRF-Token', window.CSRF_TOKEN);

        // XHR for upload progress
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
            }
        };

        xhr.onload = function() {
            const res = JSON.parse(xhr.responseText);
            progressBar.style.width = '100%';
            if (res.ok) {
                showStatus('success',
                    `Transcription complete! <a href="summarize.php?id=${res.id}">Go to summarize &rarr;</a>`);
                submitBtn.style.display = 'none';
            } else {
                showStatus('error', res.message || 'Upload or transcription failed.');
            }
        };

        xhr.onerror = function() {
            showStatus('error', 'Network error during upload. Please try again.');
        };

        xhr.send(fd);
    } catch (err) {
        showStatus('error', 'Something went wrong: ' + err.message);
    }
}

function showStatus(type, html) {
    const colors = { success:'alert-success', error:'alert-danger', info:'alert-info' };
    statusDiv.innerHTML = `<div class="alert ${colors[type] ?? 'alert-light'} small">${html}</div>`;
}

function formatSize(bytes) {
    if (bytes >= 1048576) return (bytes/1048576).toFixed(1) + ' MB';
    return (bytes/1024).toFixed(0) + ' KB';
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
