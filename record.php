<?php
/**
 * record.php
 * Browser voice recording using MediaRecorder API.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid = current_user_id();

// Pending transcribed record
$stmt = db()->prepare(
    'SELECT id FROM summaries WHERE user_id = ? AND status = \'transcribed\' ORDER BY updated_at DESC LIMIT 1'
);
$stmt->execute([$uid]);
$pending = $stmt->fetch();

$page_title = 'Record Voice';
$page_active = 'record';
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<h2 class="mb-4 fw-bold"><i class="fa-solid fa-microphone me-2 text-danger"></i>Record Your Voice</h2>

<?php if ($pending): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-2"></i>
        You have a transcribed recording ready to summarize.
        <a href="summarize.php?id=<?= (int)$pending['id'] ?>" class="fw-bold ms-1">Go to summarize &rarr;</a>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4 text-center">

        <!-- Microphone status -->
        <div id="micStatus" class="mb-3">
            <span class="badge bg-secondary">Checking microphone…</span>
        </div>

        <!-- Recording controls -->
        <div class="d-flex justify-content-center gap-3 mb-4" id="recControls" style="display:none;">
            <button class="btn btn-danger btn-lg circle-btn" id="startBtn" title="Start recording">
                <i class="fa-solid fa-record"></i>
            </button>
            <button class="btn btn-warning circle-btn" id="pauseBtn" title="Pause / Resume" disabled>
                <i class="fa-solid fa-pause"></i>
            </button>
            <button class="btn btn-success circle-btn" id="stopBtn" title="Stop recording" disabled>
                <i class="fa-solid fa-stop"></i>
            </button>
        </div>

        <!-- Timer -->
        <div id="timerDisplay" class="fs-4 fw-bold mb-3" style="display:none;">00:00</div>

        <!-- Audio preview -->
        <div id="previewSection" class="mt-4 d-none">
            <h6 class="fw-bold text-secondary text-uppercase small mb-2">Audio Preview</h6>
            <audio id="audioPreview" controls class="w-100"></audio>
            <div class="mt-3 d-flex justify-content-center gap-2">
                <button class="btn btn-outline-secondary" id="reRecordBtn">
                    <i class="fa-solid fa-rotate-right me-1"></i> Record Again
                </button>
                <button class="btn btn-primary" id="submitRecordBtn">
                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Transcribe &amp; Save
                </button>
            </div>
        </div>

        <!-- Status -->
        <div id="recordStatus" class="mt-4"></div>
    </div>
</div>

<script>
let mediaRecorder = null;
let chunks        = [];
let audioBlob     = null;
let audioURL      = null;
let timerInterval = null;
let timerSeconds  = 0;
let isPaused      = false;

const micStatus     = document.getElementById('micStatus');
const recControls   = document.getElementById('recControls');
const startBtn      = document.getElementById('startBtn');
const pauseBtn      = document.getElementById('pauseBtn');
const stopBtn       = document.getElementById('stopBtn');
const timerDisplay  = document.getElementById('timerDisplay');
const previewSection= document.getElementById('previewSection');
const audioPreview  = document.getElementById('audioPreview');
const reRecordBtn   = document.getElementById('reRecordBtn');
const submitBtn     = document.getElementById('submitRecordBtn');
const statusDiv     = document.getElementById('recordStatus');

// ---- Request microphone on page load ----------------------------------
(async function initMic() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(t => t.stop()); // release immediately
        micStatus.innerHTML = '<span class="badge bg-success"><i class="fa-solid fa-microphone me-1"></i>Microphone ready</span>';
        recControls.style.display = 'flex';
    } catch (err) {
        micStatus.innerHTML = `<span class="badge bg-danger">Microphone not available – ${escapeHtml(err.message || 'permission denied')}</span>`;
        document.getElementById('recordStatus').innerHTML =
            `<div class="alert alert-warning small">
                Microphone access is not available.
                You can <a href="upload.php" class="fw-bold">upload an audio file instead</a>.
            </div>`;
        return;
    }
})();

// ---- Recording logic ----------------------------------------------------
startBtn.addEventListener('click', async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const options = { mimeType: 'audio/webm' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options.mimeType = ''; // let browser pick
        }
        mediaRecorder = new MediaRecorder(stream, options);
        chunks = [];

        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) chunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
            audioBlob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
            audioURL  = URL.createObjectURL(audioBlob);
            audioPreview.src = audioURL;
            previewSection.classList.remove('d-none');
            stopTimer();
            timerDisplay.style.display = 'none';
            showRecordStatus('info', 'Recording saved. Preview above, or submit for transcription.');
            stream.getTracks().forEach(t => t.stop());
        };

        mediaRecorder.start();
        timerSeconds = 0;
        timerDisplay.style.display = 'block';
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);

        startBtn.disabled = true;
        pauseBtn.disabled = false;
        stopBtn.disabled  = false;
        showRecordStatus('info', '<i class="fa-solid fa-circle me-1 text-danger"></i>Recording…');

    } catch (err) {
        showRecordStatus('error', 'Could not start recording: ' + escapeHtml(err.message));
    }
});

pauseBtn.addEventListener('click', () => {
    if (!mediaRecorder) return;
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        isPaused = true;
        pauseBtn.querySelector('i').className = 'fa-solid fa-play';
        showRecordStatus('info', 'Recording paused.');
    } else if (mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        isPaused = false;
        pauseBtn.querySelector('i').className = 'fa-solid fa-pause';
        showRecordStatus('info', 'Recording resumed.');
    }
});

stopBtn.addEventListener('click', () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    startBtn.disabled = false;
    pauseBtn.disabled = true;
    stopBtn.disabled  = true;
});

reRecordBtn.addEventListener('click', () => {
    if (audioURL) URL.revokeObjectURL(audioURL);
    audioBlob = null; audioURL = null; chunks = [];
    previewSection.classList.add('d-none');
    timerDisplay.style.display = 'none';
    statusDiv.innerHTML = '';
    mediaRecorder = null;
});

submitBtn.addEventListener('click', () => {
    if (!audioBlob) return;
    submitRecording();
});

async function submitRecording() {
    const fd = new FormData();
    fd.append('recorded_audio', audioBlob, 'recording.webm');
    fd.append('csrf_token', window.CSRF_TOKEN);

    showRecordStatus('info', 'Uploading and transcribing your recording…');
    submitBtn.disabled = true;

    try {
        const res = await fetch('process_audio.php', {
            method:  'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
            body:    fd,
        });
        const data = await res.json();
        if (data.ok) {
            showRecordStatus('success',
                `Transcription complete! <a href="summarize.php?id=${data.id}">Go to summarize &rarr;</a>`);
        } else {
            showRecordStatus('error', data.message || 'Transcription failed.');
        }
    } catch (err) {
        showRecordStatus('error', 'Network error: ' + err.message);
    } finally {
        submitBtn.disabled = false;
    }
}

// ---- Helpers ------------------------------------------------------------
function updateTimer() {
    timerSeconds++;
    const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
    const s = String(timerSeconds % 60).padStart(2, '0');
    timerDisplay.textContent = `${m}:${s}`;
}
function stopTimer() { clearInterval(timerInterval); }

function showRecordStatus(type, html) {
    const colors = { success:'alert-success', error:'alert-danger', info:'alert-info' };
    statusDiv.innerHTML = `<div class="alert ${colors[type] ?? 'alert-light'} small">${html}</div>`;
}
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
