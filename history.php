<?php
/**
 * history.php
 * Paginated list of all summaries with search and filter.
 *
 * GET parameters:
 *   - page:        1-based page number
 *   - search:      keyword to match against title
 *   - type:        filter by summary_type (short|detailed|bullets|meeting_notes|study_notes|custom|all)
 *   - date_from:   YYYY-MM-DD
 *   - date_to:     YYYY-MM-DD
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
auth_guard();

$uid  = current_user_id();
$PAGE_SIZE = 15;
$page  = max(1, (int)($_GET['page']   ?? 1));
$search= trim($_GET['search']  ?? '');
$type  = $_GET['type']      ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// Build WHERE clause
$where  = "user_id = ? AND status <> 'deleted'";
$params = [$uid];

if ($search !== '') {
    $where .= " AND title LIKE ?";
    $params[] = '%' . $search . '%';
}
if ($type !== 'all' && in_array($type, ['short','detailed','bullets','meeting_notes','study_notes','custom'], true)) {
    $where .= " AND summary_type = ?";
    $params[] = $type;
}
if ($dateFrom !== '') {
    $where .= " AND created_at >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// Total count for pagination
$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM summaries WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $PAGE_SIZE));
$page = min($page, $totalPages);

// Fetch page
$offset = ($page - 1) * $PAGE_SIZE;
$stmt = db()->prepare(
    "SELECT id, title, audio_filename, summary_type, status, created_at
     FROM summaries WHERE {$where}
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?"
);
foreach (array_values($params) as $i => $v) $stmt->bindValue($i + 1, $v);
$stmt->bindValue(count($params) + 1, $PAGE_SIZE, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset,   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Pagination links (preserve query string minus page)
$qs = $_GET;
unset($qs['page']);
$base = 'history.php?' . http_build_query($qs);

$type_labels = [
    'short'         => 'Short',
    'detailed'      => 'Detailed',
    'bullets'       => 'Bullet Points',
    'meeting_notes' => 'Meeting Notes',
    'study_notes'   => 'Study Notes',
    'custom'        => 'Custom',
];

$page_title  = 'History';
$page_active = 'history';
?>
<?php require __DIR__ . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Summary History</h2>
    <a href="upload.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> New Summary
    </a>
</div>

<!-- Filter bar -->
<form method="GET" class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search by title</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       value="<?= e($search) ?>" placeholder="e.g. Project Meeting">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Summary Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="all" <?= $type==='all'?'selected':'' ?>>All types</option>
                    <?php foreach ($type_labels as $v => $l): ?>
                        <option value="<?= e($v) ?>" <?= $type===$v?'selected':'' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Go
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Results -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found
    </div>
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                <p class="mb-0">No summaries found<?= $search ? ' for "<strong>' . e($search) . '</strong>"' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Title</th>
                            <th class="fw-semibold">File</th>
                            <th class="fw-semibold">Type</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold text-end">Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold">
                                <a href="view_summary.php?id=<?= (int)$r['id'] ?>" class="text-decoration-none">
                                    <?= e($r['title']) ?>
                                </a>
                            </td>
                            <td class="small text-secondary"><?= e($r['audio_filename']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= e($type_labels[$r['summary_type']] ?? $r['summary_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php $badge_cls = match($r['status']) {
                                    'summarized'  => 'bg-success',
                                    'transcribed' => 'bg-warning text-dark',
                                    'failed'      => 'bg-danger',
                                    default      => 'bg-secondary',
                                }; ?>
                                <span class="badge <?= $badge_cls ?>"><?= e(ucfirst($r['status'])) ?></span>
                            </td>
                            <td class="text-end small text-secondary">
                                <?= e(date('M d, Y H:i', strtotime($r['created_at']))) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view_summary.php?id=<?= (int)$r['id'] ?>"
                                       class="btn btn-outline-primary" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="download.php?id=<?= (int)$r['id'] ?>&type=summary"
                                       class="btn btn-outline-secondary" title="Download">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteRecord(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['title'])) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= e($base) ?>&page=<?= $page - 1 ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php
                        // Simple sliding-window pagination: show up to 11 page links centred on current
                        $maxVisible = 11;
                        $start = max(1, $page - intdiv($maxVisible, 2));
                        $end   = min($totalPages, $start + $maxVisible - 1);
                        $start = max(1, $end - $maxVisible + 1);
                        if ($start > 1) {
                            // ellipsis after "1"
                            $showFirst = 1;
                            $gapAfterFirst = ($start > 2);
                        } else {
                            $showFirst = 0;
                            $gapAfterFirst = false;
                        }
                        if ($end < $totalPages) {
                            $showLast = $totalPages;
                            $gapBeforeLast = ($end < $totalPages - 1);
                        } else {
                            $showLast = 0;
                            $gapBeforeLast = false;
                        }
                    ?>
                    <?php if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= e($base) ?>&page=1">1</a></li>
                        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e($base) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= e($base) ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= e($base) ?>&page=<?= $page + 1 ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
async function deleteRecord(id, title) {
    const sw = Swal.fire({
        title: 'Delete this summary?',
        text:  '<strong>' + title + '</strong> will be permanently removed.',
        icon:  'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
    });
    if (!sw.isConfirmed) return;

    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', window.CSRF_TOKEN);

    const res  = await fetch('delete_summary.php', {
        method:  'POST',
        headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
        body:    fd,
    });
    const data = await res.json();
    if (data.ok) {
        Swal.fire({ toast:true, position:'top-end', timer:2000, showConfirmButton:false,
                    background:'#28a745', html:'Summary deleted.' });
        setTimeout(() => location.reload(), 800);
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.message || 'Delete failed.' });
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
