<?php
/**
 * summarize.php
 * Select summary type and generate an AI summary for a
 * previously transcribed recording.
 *
 * Accepts a query parameter ?id=<summary_id>.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid = current_user_id();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: history.php');
    exit;
}

// Load record – must belong to this user
$stmt = db()->prepare(
    'SELECT id, user_id, title, audio_filename, transcription_text,
            summary_text, summary_type, status
     FROM summaries
     WHERE id = ? AND user_id = ? AND status <> \'deleted\'
     LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record || empty($record['transcription_text'])) {
    // No transcription yet → go back to history
    header('Location: history.php');
    exit;
}

// Flash message from POST redirect (after generation)
$flash_type  = $_GET['flash'] ?? null;
$flash_msg   = $_GET['flash_msg'] ?? null;

// Pre-fill from existing record
$existing_summary = $record['summary_text'] ?? '';
$existing_type    = $record['summary_type'] ?? 'short';
$existing_custom  = db()->prepare(
    'SELECT custom_instruction FROM summaries WHERE id = ? AND user_id = ?'
);
$existing_custom->execute([$id, $uid]);
$custom_row = $existing_custom->fetch();
$existing_custom_instr = $custom_row ? $custom_row['custom_instruction'] : '';

$type_labels = [
    'short'         => 'Short Summary',
    'detailed'      => 'Detailed Summary',
    'bullets'       => 'Bullet Points',
    'meeting_notes' => 'Meeting Notes',
    'study_notes'   => 'Study Notes',
    'custom'        => 'Custom',
];

$page_title  = 'Summarize';
$page_active = 'summarize';
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<h2 class="mb-1 fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2 text-primary"></i>AI Summary</h2>
<p class="text-secondary mb-4">Transcribing <strong><?= e($record['audio_filename']) ?></strong> – <em><?= e($record['title']) ?></em></p>

<?php if ($flash_type): ?>
    <div class="alert alert-<?= e($flash_type) ?>">
        <?= e($flash_msg) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left column: transcription + form -->
    <div class="col-lg-7">
        <!-- Transcription -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-file-lines me-2"></i>Transcription</span>
                <button class="btn btn-sm btn-outline-secondary" id="copyTranscriptionBtn" title="Copy transcription">
                    <i class="fa-solid fa-copy me-1"></i> Copy
                </button>
            </div>
            <div class="card-body">
                <textarea id="transcriptionText"
                          class="form-control transcription-area"
                          rows="10"
                          readonly><?= e($record['transcription_text']) ?></textarea>
            </div>
        </div>

        <!-- Summary form -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-list-check me-2"></i>Summary Options
            </div>
            <div class="card-body">
                <form id="summarizeForm">
                    <!-- Summary type -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="summaryType">Summary Type</label>
                        <select class="form-select" id="summaryType" name="summary_type" required>
                            <?php foreach ($type_labels as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= $existing_type === $val ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Custom instruction (shown only for "custom" type) -->
                    <div class="mb-3 d-none" id="customInstructionWrap">
                        <label class="form-label fw-semibold" for="customInstruction">
                            Custom Instructions <small class="text-secondary">(optional)</small>
                        </label>
                        <textarea class="form-control" id="customInstruction"
                                  name="custom_instruction" rows="2"
                                  placeholder="e.g. Focus on action items and deadlines…">
<?= e($existing_custom_instr) ?></textarea>
                    </div>

                    <!-- Language -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="language">Output Language</label>
                        <select class="form-select" id="language" name="language">
                            <option value="en" selected>English</option>
                            <option value="zh">中文 (Chinese)</option>
                            <option value="es">Español (Spanish)</option>
                            <option value="fr">Français (French)</option>
                            <option value="de">Deutsch (German)</option>
                            <option value="ja">日本語 (Japanese)</option>
                        </select>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary" id="generateBtn">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>
                        Generate Summary
                    </button>
                </form>

                <!-- Loading spinner -->
                <div id="summaryLoading" class="d-none text-center py-4">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <p class="text-secondary small mb-0">Generating summary… this may take up to 30 seconds.</p>
                </div>

                <!-- Error box -->
                <div id="summaryError" class="alert alert-danger d-none mt-3" role="alert"></div>

                <!-- Existing summary (before regeneration) -->
                <?php if ($existing_summary): ?>
                    <div id="existingSummaryCard" class="card mt-4 border-primary">
                        <div class="card-header bg-white fw-bold">
                            <i class="fa-solid fa-file-lines me-2"></i>
                            Previous Summary
                            <button class="btn btn-sm btn-outline-secondary float-end" id="copySummaryBtn" title="Copy summary">
                                <i class="fa-solid fa-copy me-1"></i> Copy
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="existingSummaryText" class="pre-wrap"><?= e($existing_summary) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right column: new summary result -->
    <div class="col-lg-5">
        <div class="card shadow-sm" id="newSummaryCard" style="display:none;">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-sparkles me-2 text-success"></i>New Summary</span>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary" id="copyNewSummaryBtn" title="Copy">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                    <a class="btn btn-sm btn-outline-secondary" id="downloadSummaryBtn" title="Download">
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div id="newSummaryText" class="pre-wrap"></div>
                <div class="mt-3 d-flex gap-2">
                    <a class="btn btn-outline-success btn-sm" id="viewBtn" target="_blank">
                        <i class="fa-solid fa-eye me-1"></i> View Full Record
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" id="regenerateBtn">
                        <i class="fa-solid fa-rotate-right me-1"></i> Regenerate
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const SUMMARY_ID = <?= $id ?>;

// Show/hide custom instruction field
document.getElementById('summaryType').addEventListener('change', function() {
    const wrap = document.getElementById('customInstructionWrap');
    wrap.classList.toggle('d-none', this.value !== 'custom');
});
// Pre-trigger in case custom was previously selected
document.getElementById('summaryType').dispatchEvent(new Event('change'));

// Copy helpers
document.getElementById('copyTranscriptionBtn').addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('transcriptionText').value);
    flashToast('Transcription copied to clipboard.');
});

document.getElementById('copySummaryBtn')?.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('existingSummaryText').textContent);
    flashToast('Summary copied to clipboard.');
});

document.getElementById('copyNewSummaryBtn')?.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('newSummaryText').textContent);
    flashToast('Summary copied to clipboard.');
});

// Regenerate button
document.getElementById('regenerateBtn')?.addEventListener('click', () => {
    generateSummary(true);
});

// Form submit
document.getElementById('summarizeForm').addEventListener('submit', e => {
    e.preventDefault();
    generateSummary(false);
});

async function generateSummary(isRegen) {
    const summaryType   = document.getElementById('summaryType').value;
    const customInstr   = document.getElementById('customInstruction').value;
    const language      = document.getElementById('language').value;

    const loading  = document.getElementById('summaryLoading');
    const errorBox = document.getElementById('summaryError');
    const newCard  = document.getElementById('newSummaryCard');
    const btn      = document.getElementById('generateBtn');

    // Show loading
    loading.classList.remove('d-none');
    errorBox.classList.add('d-none');
    newCard.style.display = 'none';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('id', SUMMARY_ID);
    fd.append('summary_type', summaryType);
    fd.append('custom_instruction', customInstr);
    fd.append('language', language);
    fd.append('csrf_token', window.CSRF_TOKEN);

    try {
        const res = await fetch('api/generate_summary.php', {
            method:  'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
            body:    fd,
        });
        const data = await res.json();

        if (data.ok) {
            const newSummaryText = document.getElementById('newSummaryText');
            newSummaryText.textContent = data.summary;
            newCard.style.display = '';

            // Update download link
            document.getElementById('downloadSummaryBtn').href =
                `download.php?id=${SUMMARY_ID}&type=summary`;
            document.getElementById('viewBtn').href = `view_summary.php?id=${SUMMARY_ID}`;

            // Hide existing summary card if it was visible
            const existing = document.getElementById('existingSummaryCard');
            if (existing) existing.style.display = 'none';

            // Rebind copy button for new summary
            document.getElementById('copyNewSummaryBtn').onclick = () => {
                navigator.clipboard.writeText(newSummaryText.textContent);
                flashToast('Summary copied.');
            };

            flashToast('Summary generated successfully!', 'success');
        } else {
            errorBox.textContent = data.message || 'Summary generation failed.';
            errorBox.classList.remove('d-none');
        }
    } catch (err) {
        errorBox.textContent = 'Network error: ' + err.message;
        errorBox.classList.remove('d-none');
    } finally {
        loading.classList.add('d-none');
        btn.disabled = false;
    }
}

function flashToast(msg, type = 'info') {
    const bgMap = { success:'#28a745', error:'#dc3545', info:'#0d6efd' };
    Swal.fire({
        toast:      true,
        position:   'top-end',
        showConfirmButton: false,
        timer:      2500,
        timerProgressBar: true,
        background: bgMap[type] || bgMap.info,
        html:       `<i class="fa-solid fa-check me-2"></i>${escapeHtml(msg)}`,
    });
}
function escapeHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
