<?php
/**
 * view_summary.php
 * Detailed view of a single summary record with copy, download,
 * title editing, and delete actions.
 *
 * GET parameter: id = summary record ID
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid = current_user_id();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---- Handle title edit (POST) ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_title') {
    csrf_verify();
    $newTitle = trim($_POST['new_title'] ?? '');
    if (strlen($newTitle) < 1 || strlen($newTitle) > 255) {
        flash_redirect("Invalid title.", 'error');
    }
    $stmt = db()->prepare(
        'UPDATE summaries SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$newTitle, $id, $uid]);
    flash_redirect("Title updated.", 'success');
}

// ---- Load record -------------------------------------------------------
$stmt = db()->prepare(
    'SELECT s.*, u.name AS user_name
     FROM summaries s
     JOIN users u ON u.id = s.user_id
     WHERE s.id = ? AND s.user_id = ? AND s.status <> \'deleted\'
     LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    header('Location: history.php');
    exit;
}

$type_labels = [
    'short'         => 'Short Summary',
    'detailed'      => 'Detailed Summary',
    'bullets'       => 'Bullet Points',
    'meeting_notes' => 'Meeting Notes',
    'study_notes'   => 'Study Notes',
    'custom'        => 'Custom Summary',
];

$flash_type = $_GET['flash'] ?? null;
$flash_msg  = $_GET['flash_msg'] ?? null;

$page_title  = 'View Summary';
$page_active = 'view';

function flash_redirect(string $msg, string $type = 'success'): void {
    header('Location: view_summary.php?id=' . $_GET['id'] .
           '&flash=' . urlencode($type) .
           '&flash_msg=' . urlencode($msg));
    exit;
}
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<?php if ($flash_type): ?>
    <div class="alert alert-<?= e($flash_type) ?>"><?= e($flash_msg) ?></div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="history.php" class="text-decoration-none">History</a></li>
        <li class="breadcrumb-item active"><?= e($record['title']) ?></li>
    </ol>
</nav>

<!-- Title row -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <!-- Editable title -->
        <div id="titleRow" class="d-flex align-items-center gap-2">
            <h2 class="fw-bold mb-0" id="summaryTitle"><?= e($record['title']) ?></h2>
            <button class="btn btn-sm btn-outline-secondary" id="editTitleBtn" title="Edit title">
                <i class="fa-solid fa-pen"></i>
            </button>
        </div>
        <input type="text" id="titleInput" class="form-control mt-2 d-none"
               value="<?= e($record['title']) ?>" maxlength="250"
               style="max-width:480px;">
        <div class="d-flex gap-2 mt-2 d-none" id="titleSaveRow">
            <button class="btn btn-sm btn-primary" id="saveTitleBtn">
                <i class="fa-solid fa-check me-1"></i> Save
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="cancelTitleBtn">Cancel</button>
        </div>
        <small class="text-secondary d-block mt-1">
            <i class="fa-solid fa-file-audio me-1"></i>
            <?= e($record['audio_filename']) ?>
            &nbsp;|&nbsp;
            <i class="fa-solid fa-clock me-1"></i>
            <?= e(date('M d, Y H:i', strtotime($record['created_at']))) ?>
        </small>
    </div>

    <!-- Action buttons -->
    <div class="d-flex gap-2 flex-wrap">
        <a href="summarize.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Summarize
        </a>
        <a href="download.php?id=<?= $id ?>&type=summary" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-download me-1"></i> Download Summary
        </a>
        <a href="download.php?id=<?= $id ?>&type=transcription" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-download me-1"></i> Download Transcription
        </a>
        <button class="btn btn-outline-danger btn-sm" id="deleteFromViewBtn">
            <i class="fa-solid fa-trash me-1"></i> Delete
        </button>
    </div>
</div>

<?php if (empty($record['transcription_text'])): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        This recording has not been transcribed yet.
        <a href="summarize.php?id=<?= $id ?>" class="fw-bold">Go to summarize &rarr;</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Transcription -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-file-lines me-2"></i>Transcription</span>
                <button class="btn btn-sm btn-outline-secondary" id="copyTranscriptionBtn">
                    <i class="fa-solid fa-copy me-1"></i> Copy
                </button>
            </div>
            <div class="card-body">
                <div id="transcriptionContent" class="pre-wrap transcription-area">
                    <?= e($record['transcription_text']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <i class="fa-solid fa-sparkles me-2 text-success"></i>
                    <?= e($type_labels[$record['summary_type']] ?? ucfirst($record['summary_type'])) ?>
                </span>
                <button class="btn btn-sm btn-outline-secondary" id="copySummaryBtn">
                    <i class="fa-solid fa-copy me-1"></i> Copy
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($record['summary_text'])): ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="fa-solid fa-wand-magic-sparkles fa-2x mb-3"></i>
                        <p class="mb-2">No summary generated yet.</p>
                        <a href="summarize.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate Summary
                        </a>
                    </div>
                <?php else: ?>
                    <div id="summaryContent" class="pre-wrap"><?= e($record['summary_text']) ?></div>
                <?php endif; ?>

                <?php if (!empty($record['custom_instruction'])): ?>
                    <div class="alert alert-light border small mt-3">
                        <strong>Custom instruction:</strong>
                        <?= e($record['custom_instruction']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const RECORD_ID = <?= $id ?>;
const CSRF      = window.CSRF_TOKEN;

// ---- Title editing -----------------------------------------------------
const titleRow     = document.getElementById('titleRow');
const titleInput   = document.getElementById('titleInput');
const titleSaveRow = document.getElementById('titleSaveRow');
const editTitleBtn = document.getElementById('editTitleBtn');

editTitleBtn.addEventListener('click', () => {
    titleInput.classList.remove('d-none');
    titleSaveRow.classList.remove('d-none');
    editTitleBtn.style.display = 'none';
    document.getElementById('summaryTitle').style.display = 'none';
    titleInput.focus();
});

document.getElementById('saveTitleBtn').addEventListener('click', () => {
    const val = titleInput.value.trim();
    if (val.length < 1) { alert('Title cannot be empty.'); return; }
    const fd = new FormData();
    fd.append('action', 'edit_title');
    fd.append('new_title', val);
    fd.append('csrf_token', CSRF);
    fetch('view_summary.php?id=' + RECORD_ID, { method:'POST', headers:{'X-CSRF-Token':CSRF}, body:fd })
        .then(r => r.text())
        .then(() => location.reload());
});

document.getElementById('cancelTitleBtn').addEventListener('click', () => {
    titleInput.classList.add('d-none');
    titleSaveRow.classList.add('d-none');
    editTitleBtn.style.display = '';
    document.getElementById('summaryTitle').style.display = '';
});

// ---- Copy buttons ------------------------------------------------------
document.getElementById('copyTranscriptionBtn').addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('transcriptionContent').textContent);
    flashToast('Transcription copied.');
});

const sumBtn = document.getElementById('copySummaryBtn');
if (sumBtn) sumBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('summaryContent').textContent);
    flashToast('Summary copied.');
});

// ---- Delete -------------------------------------------------------------
document.getElementById('deleteFromViewBtn').addEventListener('click', () => {
    Swal.fire({
        title: 'Delete this summary?',
        text:  'This will permanently remove the record and its audio file.',
        icon:  'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        const fd = new FormData();
        fd.append('id', RECORD_ID);
        fd.append('csrf_token', CSRF);
        fetch('delete_summary.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
            body: fd,
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                Swal.fire({ toast:true, position:'top-end', timer:2000, showConfirmButton:false,
                            background:'#28a745', html:'Summary deleted.' });
                setTimeout(() => location.href = 'history.php', 800);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: data.message || 'Delete failed.' });
            }
        })
        .catch(err => Swal.fire({ icon:'error', title:'Network Error', text: err.message }));
    });
});

function flashToast(msg) {
    Swal.fire({
        toast:true, position:'top-end', timer:2200, showConfirmButton:false,
        timerProgressBar:true, background:'#28a745',
        html:`<i class="fa-solid fa-check me-2"></i>${msg}`,
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
