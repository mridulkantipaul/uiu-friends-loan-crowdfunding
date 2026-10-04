<?php
/** Expects $page_title to be set before include */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' · ' : '' ?><?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= isset($base) ? $base : '' ?>assets/css/style.css">
<?php if (!empty($use_admin_css)): ?><link rel="stylesheet" href="<?= isset($base) ? $base : '' ?>assets/css/admin.css"><?php endif; ?>
<script src="<?= isset($base) ? $base : '' ?>assets/js/main.js" defer></script>
</head>
<body>
