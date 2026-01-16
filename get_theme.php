<?php

include 'db.php';
if (!$conn) { return 'assets/styles/index.css'; }

// Preview override via query string (for dashboard thumbnails)
$preview = isset($_GET['preview']) ? trim($_GET['preview']) : '';

if (in_array($preview, ['design1','design2','design3','design4'])) {
    $design = $preview;
} else {
    // Fetch active theme (design only)
    $themeRow = $conn->query("SELECT design_name FROM active_theme ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Default values if no theme is set
    $design = $themeRow ? $themeRow['design_name'] : 'design1';
}

// Map design selections to CSS files
$cssFile = 'assets/styles/index.css'; // default

if ($design === 'design1') {
    $cssFile = 'assets/styles/index.css';
} elseif ($design === 'design2') {
    $cssFile = 'assets/styles/index2.css';
} elseif ($design === 'design3') {
    $cssFile = 'assets/styles/index3.css';
} elseif ($design === 'design4') {
    $cssFile = 'assets/styles/index4.css';
}

$GLOBALS['ACTIVE_DESIGN'] = $design;

return $cssFile;
?>
