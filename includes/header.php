<?php
/**
 * includes/header.php
 * Shared HTML head + navbar. Include at the top of every page.
 *
 * The page should set $page_title and $page_active before including
 * this file. $page_active is one of:
 *   'dashboard','upload','record','summarize','history','view'
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

csrf_init();
$page_title  = $page_title  ?? 'AI Voice Summarizer';
$page_active = $page_active ?? '';

function nav_link(string $url, string $label, string $active, string $icon, string $target = ''): string {
    $cls = $active === $target ? 'nav-link active' : 'nav-link';
    return sprintf(
        '<li class="nav-item"><a class="%s" href="%s">%s</a></li>',
        e($cls), e($url),
        '<i class="fa-solid fa-' . e($icon) . ' me-1"></i>' . e($label)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- App CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark app-navbar shadow-sm sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fa-solid fa-microphone me-2"></i>
            <span class="brand-text">VoiceSum</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?= nav_link('dashboard.php',  'Dashboard',   'dashboard',   'house') ?>
                <?= nav_link('upload.php',     'Upload',      'upload',      'upload') ?>
                <?= nav_link('record.php',     'Record',      'record',      'record-vinyl') ?>
                <?= nav_link('summarize.php',  'Summarize',   'summarize',  'wand-magic-sparkles') ?>
                <?= nav_link('history.php',    'History',     'history',    'clock-rotate-left') ?>
                <li class="nav-item ms-lg-3">
                    <a class="nav-link logout-link" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        <span class="d-none d-sm-inline"><?= e(current_user_name()) ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Expose CSRF token to JS for AJAX calls -->
<script>window.CSRF_TOKEN = '<?= e(csrf_token()) ?>';</script>

<div class="container-fluid px-3 px-lg-4 my-3">
