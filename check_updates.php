<?php
header('Content-Type: application/json');
include 'db.php';
// Check what to update (theme or api or both)
$checkType = isset($_GET['check']) ? $_GET['check'] : 'all';

$response = [];

// --- FETCH ACTIVE THEME (fast check) ---
if ($checkType === 'theme' || $checkType === 'all') {
    $themeRow = $conn->query("SELECT design_name FROM active_theme ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $design = $themeRow ? $themeRow['design_name'] : 'design1';
    $response['theme'] = $design;
}


// --- FETCH API DATA ---
if ($checkType === 'api' || $checkType === 'all') {
    $api_url = "https://ezbook.usm.my/api/get_sd_aduan.php";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $api_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 10 second timeout
    $apiResponse = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($apiResponse, true);
    $jumlah_aduan_baru = '#';
    $jumlah_mohon_baru = '#';

    if (is_array($data) && isset($data['success']) && $data['success'] && isset($data['data']) && is_array($data['data'])) {
        $jumlah_aduan_baru = isset($data['data']['jumlah_aduan_baru']) ? $data['data']['jumlah_aduan_baru'] : '#';
        $jumlah_mohon_baru = isset($data['data']['jumlah_mohon_baru']) ? $data['data']['jumlah_mohon_baru'] : '#';
    }
    $response['aduan_baru'] = $jumlah_aduan_baru;
    $response['mohon_baru'] = $jumlah_mohon_baru;
}

// --- FETCH BANNERS HASH (visible banners only) ---
if ($checkType === 'banners' || $checkType === 'all') {
    try {
        // Get current theme to determine orientation filter
        if (!isset($design)) {
            $themeRow = $conn->query("SELECT design_name FROM active_theme ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $design = $themeRow ? $themeRow['design_name'] : 'design1';
        }
        
        // Design 1 & 2: landscape only, Design 3 & 4: portrait only
        $orientationFilter = (in_array($design, ['design3', 'design4'])) ? 'portrait' : 'landscape';
        
        $stmt = $conn->prepare("SELECT image_path FROM banners WHERE is_hidden = 0 AND orientation = ? ORDER BY id DESC");
        $stmt->execute([$orientationFilter]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $joined = is_array($rows) ? implode('|', $rows) : '';
        $response['banners_hash'] = md5($joined);
    } catch (Exception $e) {
        // Fallback if orientation column doesn't exist
        try {
            $rows = $conn->query("SELECT image_path FROM banners WHERE is_hidden = 0 ORDER BY id DESC")
                        ->fetchAll(PDO::FETCH_COLUMN);
            $joined = is_array($rows) ? implode('|', $rows) : '';
            $response['banners_hash'] = md5($joined);
        } catch (Exception $e2) {
            $response['banners_hash'] = null;
        }
    }
}

// --- CHECK DUTY CHANGES (for today's staff) ---
if ($checkType === 'duties' || $checkType === 'all') {
    try {
        $dateToday = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT e.id, e.name, d.duty_time_start, d.duty_time_end
            FROM duties d
            JOIN employees e ON e.id = d.employee_id
            WHERE d.duty_date = ?
            ORDER BY d.duty_time_start ASC
        ");
        $stmt->execute([$dateToday]);
        $duties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dutiesString = json_encode($duties);
        $response['duties_hash'] = md5($dutiesString);
    } catch (Exception $e) {
        $response['duties_hash'] = null;
    }
}

// --- CHECK VIDEOS CHANGES ---
if ($checkType === 'videos' || $checkType === 'all') {
    try {
        $videos = $conn->query("SELECT youtube_url FROM videos ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $videosString = implode('|', $videos);
        $response['videos_hash'] = md5($videosString);
    } catch (Exception $e) {
        $response['videos_hash'] = null;
    }
}

// --- CHECK ANNOUNCEMENTS CHANGES ---
if ($checkType === 'announcements' || $checkType === 'all') {
    try {
        $announcements = $conn->query("SELECT text FROM announcements ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
        $announcementsString = implode('|', $announcements);
        $response['announcements_hash'] = md5($announcementsString);
    } catch (Exception $e) {
        $response['announcements_hash'] = null;
    }
}

// --- CHECK FOOTER TEXT CHANGES ---
if ($checkType === 'footer' || $checkType === 'all') {
    try {
        $footer = $conn->query("SELECT message FROM footer_text ORDER BY id DESC LIMIT 1")->fetchColumn();
        $response['footer_hash'] = md5($footer ?: '');
    } catch (Exception $e) {
        $response['footer_hash'] = null;
    }
}

// --- RETURN JSON RESPONSE ---
echo json_encode($response);
?>
