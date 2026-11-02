<?php
/**
 * dashboard.php
 * Main dashboard with stats, recent activity, and quick actions.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid = current_user_id();

// Statistics
$stmt = db()->prepare(
    'SELECT
        COUNT(*) AS total_summaries,
        SUM(status IN (\'transcribed\', \'summarized\', \'failed\')) AS total_audio,
        SUM(status = \'summarized\')  AS completed,
        SUM(status = \'transcribed\') AS pending,
        SUM(status = \'failed\')      AS failed
    FROM summaries WHERE user_id = ? AND status <> \'deleted\''
);
$stmt->execute([$uid]);
$stats = $stmt->fetch() ?: ['total_summaries' => 0, 'total_audio' => 0, 'completed' => 0, 'pending' => 0, 'failed' => 0];

// Recent summaries (last 5)
$stmt = db()->prepare(
    'SELECT id, title, audio_filename, summary_type, status, created_at
     FROM summaries
     WHERE user_id = ? AND status <> \'deleted\'
     ORDER BY created_at DESC LIMIT 5'
);
$stmt->execute([$uid]);
$recent = $stmt->fetchAll();

// Summary type labels
$type_labels = [
    'short'           => 'Short Summary',
    'detailed'        => 'Detailed Summary',
    'bullets'         => 'Bullet Points',
    'meeting_notes'   => 'Meeting Notes',
    'study_notes'     => 'Study Notes',
    'custom'          => 'Custom',
];

$page_title = 'Dashboard';
$page_active = 'dashboard';
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<h2 class="mb-4 fw-bold">Dashboard</h2>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-file-audio stat-icon text-primary"></i>
                <div class="fs-3 fw-bold"><?= (int)$stats['total_audio'] ?></div>
                <small class="text-secondary">Audio Files</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-align-left stat-icon text-success"></i>
                <div class="fs-3 fw-bold"><?= (int)$stats['completed'] ?></div>
                <small class="text-secondary">Summaries</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-clock stat-icon text-warning"></i>
                <div class="fs-3 fw-bold"><?= (int)$stats['pending'] ?></div>
                <small class="text-secondary">Pending</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-circle-xmark stat-icon text-danger"></i>
                <div class="fs-3 fw-bold"><?= (int)$stats['failed'] ?></div>
                <small class="text-secondary">Failed</small>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="upload.php" class="quick-action card shadow-sm h-100 text-decoration-none text-reset">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                <i class="fa-solid fa-cloud-arrow-up fa-3x text-primary mb-2"></i>
                <h5 class="fw-bold">Upload Audio</h5>
                <small class="text-secondary">MP3, WAV, M4A, WebM</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="record.php" class="quick-action card shadow-sm h-100 text-decoration-none text-reset">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                <i class="fa-solid fa-microphone fa-3x text-danger mb-2"></i>
                <h5 class="fw-bold">Record Voice</h5>
                <small class="text-secondary">Use your microphone</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="history.php" class="quick-action card shadow-sm h-100 text-decoration-none text-reset">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                <i class="fa-solid fa-clock-rotate-left fa-3x text-info mb-2"></i>
                <h5 class="fw-bold">View History</h5>
                <small class="text-secondary">All your summaries</small>
            </div>
        </a>
    </div>
</div>

<!-- Recent Summaries -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-list me-2"></i>Recent Summaries
    </div>
    <div class="card-body">
        <?php if (empty($recent)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                <p>No summaries yet. <a href="upload.php">Upload an audio file</a> to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>File</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="fw-semibold">
                                <a href="view_summary.php?id=<?= (int)$r['id'] ?>" class="text-decoration-none">
                                    <?= e($r['title']) ?>
                                </a>
                            </td>
                            <td class="small text-secondary"><?= e($r['audio_filename']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($type_labels[$r['summary_type']] ?? $r['summary_type']) ?></span></td>
                            <td>
                                <?php
                                    $badge_cls = match($r['status']) {
                                        'summarized' => 'bg-success',
                                        'transcribed'=> 'bg-warning',
                                        'failed'     => 'bg-danger',
                                        default        => 'bg-secondary',
                                    };
                                ?>
                                <span class="badge <?= $badge_cls ?>"><?= e(ucfirst($r['status'])) ?></span>
                            </td>
                            <td class="text-end small text-secondary">
                                <?= e(date('M d, Y H:i', strtotime($r['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
