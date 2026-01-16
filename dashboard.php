<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: admin_login.php");
  exit;
}

// --- DB CONNECT ---
include 'db.php';

// --- Helpers ---
function redirect_with_msg($loc, $msg = '', $section = '') {
    if ($msg !== '') {
        $_SESSION['flash_msg'] = $msg;
    }
    if ($section !== '') {
        $loc .= (strpos($loc, '?') === false ? '?' : '&') . 'section=' . urlencode($section);
    }
    header("Location: " . $loc);
    exit;
}
function getFlash() {
    if (!empty($_SESSION['flash_msg'])) {
        $m = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        return $m;
    }
    return '';
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_video') {
    $youtube_url = trim($_POST['youtube_url']);
    if ($youtube_url !== '') {
        $video_id = null;
        if (preg_match('/(?:v=|\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/', $youtube_url, $m)) {
            $video_id = $m[1];
        } else if (preg_match('/^([A-Za-z0-9_-]{11})$/', $youtube_url, $m)) {
            $video_id = $m[1];
        }
        if ($video_id) {
            $embed = "https://www.youtube.com/embed/" . $video_id;
            $stmt = $conn->prepare("INSERT INTO videos (youtube_url) VALUES (?)");
            $stmt->execute(array($embed));
            redirect_with_msg('dashboard.php', "✅ Video added successfully.", 'section-videos');
        } else {
            $msg = "❌ Invalid YouTube URL. Please enter a valid link.";
        }
    } else {
        $msg = "⚠️ Please enter a YouTube URL.";
    }
}

/* Upload Banner */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_banner') {
    if (!empty($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/assets/banners/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $name = time() . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['banner_image']['name']));
        $target = $target_dir . $name;
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $target)) {
            // Detect orientation
            list($width, $height) = getimagesize($target);
            $orientation = ($height > $width) ? 'portrait' : 'landscape';
            
            $stmt = $conn->prepare("INSERT INTO banners (image_path, is_hidden, orientation) VALUES (?, 0, ?)");
            $stmt->execute(array($name, $orientation));
            redirect_with_msg('dashboard.php', "✅ Banner uploaded successfully as {$orientation}.", 'section-banners');
        } else {
            $msg = "❌ Failed to upload banner file.";
        }
    } else {
        $msg = "⚠️ Please select a banner file.";
    }
}

/* Manage Employees: add new employee (name + uploaded photo) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $name = trim($_POST['name']);
    if ($name === '') {
        $msg = "⚠️ Please enter employee name.";
    } else {
        $target_dir = __DIR__ . "/assets/employees/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $fileName = null;
        if (!empty($_FILES['employee_image']) && $_FILES['employee_image']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['employee_image']['name']));
            move_uploaded_file($_FILES['employee_image']['tmp_name'], $target_dir . $fileName);
        }

        $stmt = $conn->prepare("INSERT INTO employees (name, image_path) VALUES (?, ?)");
        $stmt->execute(array($name, $fileName));
        redirect_with_msg('dashboard.php', "✅ Employee added successfully.", 'section-employees-manage');
    }
}

/* Manage Employees: update existing employee (name + optional new photo) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
  $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
  $name = isset($_POST['name']) ? trim($_POST['name']) : '';
  if ($id <= 0 || $name === '') {
    $msg = "⚠️ Please provide a valid employee id and name.";
  } else {
    // Fetch current image path
    $stmt = $conn->prepare("SELECT image_path FROM employees WHERE id = ?");
    $stmt->execute(array($id));
    $currentImage = $stmt->fetchColumn();

    $target_dir = __DIR__ . "/assets/employees/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $newImageName = $currentImage; // default keep existing
    if (!empty($_FILES['employee_image']) && $_FILES['employee_image']['error'] === UPLOAD_ERR_OK) {
      // If a new image is uploaded, delete old file if exists
      if ($currentImage && file_exists($target_dir . $currentImage)) {
        @unlink($target_dir . $currentImage);
      }
      $newImageName = time() . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['employee_image']['name']));
      move_uploaded_file($_FILES['employee_image']['tmp_name'], $target_dir . $newImageName);
    }

    // Update record
    $stmt = $conn->prepare("UPDATE employees SET name = ?, image_path = ? WHERE id = ?");
    $stmt->execute(array($name, $newImageName, $id));
    redirect_with_msg('dashboard.php', "✅ Employee updated successfully.", 'section-employees-manage');
  }
}

/* Add Duties (choose existing employee + date range support) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_duty') {
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    $duty_start_dates = isset($_POST['duty_start_date']) ? $_POST['duty_start_date'] : array();
    $duty_end_dates   = isset($_POST['duty_end_date']) ? $_POST['duty_end_date'] : array();
    $duty_starts = isset($_POST['duty_start']) ? $_POST['duty_start'] : array();
    $duty_ends   = isset($_POST['duty_end']) ? $_POST['duty_end'] : array();

    if ($employee_id <= 0 || empty($duty_start_dates)) {
        $msg = "⚠️ Please select an employee and enter at least one date range.";
    } else {
        $stmt = $conn->prepare("INSERT INTO duties (employee_id, duty_date_start, duty_date_end, duty_time_start, duty_time_end) VALUES (?, ?, ?, ?, ?)");
        $added = 0;
        
        foreach ($duty_start_dates as $i => $startDate) {
            $startDate = trim($startDate);
            $endDate = isset($duty_end_dates[$i]) ? trim($duty_end_dates[$i]) : $startDate;
            $timeStart = isset($duty_starts[$i]) ? trim($duty_starts[$i]) : '';
            $timeEnd = isset($duty_ends[$i]) ? trim($duty_ends[$i]) : '';
            
            if ($startDate !== '' && $endDate !== '' && $timeStart !== '' && $timeEnd !== '') {
                // Ensure end date is >= start date
                if (strtotime($endDate) >= strtotime($startDate)) {
                    $stmt->execute(array($employee_id, $startDate, $endDate, $timeStart, $timeEnd));
                    $added++;
                }
            }
        }
        
        if ($added > 0) {
            redirect_with_msg('dashboard.php', "✅ Duty added successfully for $added range(s).", 'section-employees-duty');
        } else {
            $msg = "⚠️ No valid date ranges entered.";
        }
    }
}

/* Update Footer */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_footer') {
    $message = trim($_POST['footer_message']);
    if ($message !== '') {
        $conn->exec("DELETE FROM footer_text");
        $stmt = $conn->prepare("INSERT INTO footer_text (message) VALUES (?)");
        $stmt->execute(array($message));
        redirect_with_msg('dashboard.php', "✅ Footer updated successfully.", 'section-footer');
    } else {
        $msg = "⚠️ Footer text cannot be empty.";
    }
}

/* Add Announcement */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_announcement') {
    $text = trim($_POST['announcement']);
    if ($text !== '') {
        $stmt = $conn->prepare("INSERT INTO announcements (text) VALUES (?)");
        $stmt->execute(array($text));
        redirect_with_msg('dashboard.php', "✅ Announcement added successfully.", 'section-ann');
    } else {
        $msg = "⚠️ Please enter announcement text.";
    }
}

// --- Theme Update Handler ---
/* Update Theme */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_theme') {
  $design = isset($_POST['design_name']) ? trim($_POST['design_name']) : '';

  if ($design !== '') {
    $conn->exec("DELETE FROM active_theme"); // Delete old theme
    $stmt = $conn->prepare("INSERT INTO active_theme (design_name) VALUES (?)");
    $stmt->execute(array($design));
    redirect_with_msg('dashboard.php', "✅ Theme updated successfully.", 'section-theme');
  } else {
    $msg = "⚠️ Please select a design.";
  }
}

/* Create Admin */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if ($username === '' || $password === '') {
        $msg = "⚠️ Please provide both username and password.";
    } elseif ($password !== $confirm_password) {
        $msg = "⚠️ Passwords do not match. Please try again.";
    } else {
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
        $checkStmt->execute(array($username));
        $exists = $checkStmt->fetchColumn();
        
        if ($exists > 0) {
            $msg = "❌ Username already exists. Please choose a different username.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute(array($username, $hashed));
                redirect_with_msg('dashboard.php', '✅ Admin created successfully.', 'section-manage-admin');
            } catch (Exception $e) {
                $msg = '❌ Failed to create admin: ' . $e->getMessage();
            }
        }
    }
}

/* Update Admin Password */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin_password') {
    $admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

    if ($admin_id <= 0 || $new_password === '') {
        $msg = "⚠️ Please provide a new password.";
    } elseif ($new_password !== $confirm_new_password) {
        $msg = "⚠️ Passwords do not match. Please try again.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        try {
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute(array($hashed, $admin_id));
            redirect_with_msg('dashboard.php', '✅ Password updated successfully.', 'section-manage-admin');
        } catch (Exception $e) {
            $msg = '❌ Failed to update password: ' . $e->getMessage();
        }
    }
}/* -------------------------
    GET handlers (delete)
    ------------------------- */
// Toggle banner visibility
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['toggle_banner'])) {
    $id = intval($_GET['toggle_banner']);
    $stmt = $conn->prepare("SELECT is_hidden FROM banners WHERE id = ?");
    $stmt->execute(array($id));
    $current = $stmt->fetchColumn();
    $newStatus = ($current == 1) ? 0 : 1;
    $conn->prepare("UPDATE banners SET is_hidden = ? WHERE id = ?")->execute(array($newStatus, $id));
    $msg = ($newStatus == 1) ? "👁️ Banner hidden from display." : "✅ Banner now visible on display.";
    redirect_with_msg('dashboard.php', $msg, 'section-banners');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_type']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $type = $_GET['delete_type'];
    if ($type === 'video') {
        $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute(array($id));
        redirect_with_msg('dashboard.php', "🗑️ Video deleted.", 'section-videos');
    } elseif ($type === 'banner') {
        $stmt = $conn->prepare("SELECT image_path FROM banners WHERE id = ?");
        $stmt->execute(array($id));
        $p = $stmt->fetchColumn();
        if ($p && file_exists(__DIR__ . "/assets/banners/" . $p)) unlink(__DIR__ . "/assets/banners/" . $p);
        $conn->prepare("DELETE FROM banners WHERE id = ?")->execute(array($id));
        redirect_with_msg('dashboard.php', "🗑️ Banner deleted.", 'section-banners');
    } elseif ($type === 'employee') {
        // delete employee image and their duties (duties have FK cascade)
        $stmt = $conn->prepare("SELECT image_path FROM employees WHERE id = ?");
        $stmt->execute(array($id));
        $p = $stmt->fetchColumn();
        if ($p && file_exists(__DIR__ . "/assets/employees/" . $p)) unlink(__DIR__ . "/assets/employees/" . $p);
        $conn->prepare("DELETE FROM employees WHERE id = ?")->execute(array($id));
        redirect_with_msg('dashboard.php', "🗑️ Employee deleted with all schedules.", 'section-employees-manage');
    } elseif ($type === 'duty') {
        $conn->prepare("DELETE FROM duties WHERE id = ?")->execute(array($id));
        redirect_with_msg('dashboard.php', "🗑️ Duty deleted.", 'section-employees-duty');
    } elseif ($type === 'announcement') {
        $conn->prepare("DELETE FROM announcements WHERE id = ?")->execute(array($id));
        redirect_with_msg('dashboard.php', "🗑️ Announcement deleted.", 'section-ann');
    } elseif ($type === 'admin') {
        // Prevent deleting yourself
        $currentAdminId = $_SESSION['admin'];
        if ($id == $currentAdminId) {
            redirect_with_msg('dashboard.php', "❌ You cannot delete your own account.", 'section-manage-admin');
        } else {
            $conn->prepare("DELETE FROM admins WHERE id = ?")->execute(array($id));
            redirect_with_msg('dashboard.php', "🗑️ Admin deleted.", 'section-manage-admin');
        }
    }
}

/* -------------------------
    Fetch data for display
    ------------------------- */
// Auto-delete expired duties (where duty end date/time has passed)
$now = date('Y-m-d H:i:s');
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');
$conn->exec("DELETE FROM duties WHERE duty_date_end < '$currentDate' OR (duty_date_end = '$currentDate' AND duty_time_end < '$currentTime')");

$videos = $conn->query("SELECT * FROM videos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$banners = $conn->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$employees = $conn->query("SELECT id, name, image_path FROM employees ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* fetch duties joined with employee info */
$duties = $conn->query("
    SELECT d.*, e.name AS employee_name, e.image_path AS employee_image
    FROM duties d
    JOIN employees e ON e.id = d.employee_id
    ORDER BY d.duty_date_start ASC, d.duty_time_start ASC
")->fetchAll(PDO::FETCH_ASSOC);

$footerRow = $conn->query("SELECT message FROM footer_text ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$footer_message = ($footerRow ? $footerRow['message'] : '');
$announcements = $conn->query("SELECT * FROM announcements ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT id, username FROM admins ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- BARU: Ambil data tema aktif ---
$themeRow = $conn->query("SELECT design_name FROM active_theme ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
// Sediakan 'default' jika tiada tema disimpan.
$active_design = ($themeRow ? $themeRow['design_name'] : 'design1');

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard - PTDDID</title>
<style>
  :root{
    --white: #ffffff;
    --purple: #7a42f4;
    --purple-dark: #5a25c9;
    --orange: #ff8a00;
    --muted: #f3f3f6;
    --card-bg: #ffffffff;
    --text-dark: #1f1f1f;
  }
  *{box-sizing:border-box}
  body{
    margin:0;
    font-family: "Poppins", sans-serif;
    background: var(--muted);
    color: var(--text-dark);
    height:100vh;
    display:flex;
    overflow:hidden;
  }
  /* SIDEBAR */
  .sidebar{
    width:260px;
    background: linear-gradient(180deg,var(--white), #f7f5ff);
    border-right: 1px solid rgba(0,0,0,0.06);
    padding:20px;
    display:flex;
    flex-direction:column;
  }
  .brand{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
  }
  .brand .logo{
    width:44px;height:44px;border-radius:8px;background:linear-gradient(135deg,var(--purple),var(--orange));
    display:flex;align-items:center;justify-content:center;color:white;font-weight:700;
  }
  .brand h1{font-size:16px;margin:0;color:var(--purple-dark)}
  .nav{
    display:flex;flex-direction:column;gap:8px;margin-top:10px;
  }
  .nav button{
    background:transparent;border:none;padding:10px 12px;border-radius:10px;text-align:left;font-weight:600;
    cursor:pointer;color:var(--text-dark);transition:0.15s;
  }
  .nav button.active{background:linear-gradient(90deg, rgba(122,66,244,0.12), rgba(255,138,0,0.06));box-shadow:inset 0 0 0 1px rgba(122,66,244,0.04)}
  .nav small{color:#666;font-weight:600;margin-top:8px;display:block}
  .logout{
    margin-top:auto;padding:10px;border-radius:10px;border:none;background:var(--orange);color:white;font-weight:700;cursor:pointer;
  }

  /* MAIN */
  .main{
    flex:1;overflow:auto;padding:22px 28px;
  }
  .topbar{
    display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;
  }
  .topbar h2{margin:0;color:var(--purple-dark)}
  .flash{padding:10px 14px;border-radius:10px;background:#e9f7ee;color:#116644;font-weight:600;transition:opacity 0.5s ease-out;}
  .flash.fade-out{opacity:0;}

  .grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;
  }
  .card{
    background:var(--card-bg);padding:16px;border-radius:12px;box-shadow:0 6px 18px rgba(22,11,60,0.06);
  }
  label{display:block;font-weight:700;margin-bottom:6px}
  input[type=text], input[type=url], input[type=date], input[type=time], textarea, input[type=file], select{
    width:100%;padding:10px;border-radius:8px;border:1px solid #e6e2f8;background:#fff;margin-bottom:10px;
    font-size:14px;
  }
  textarea{min-height:90px;resize:vertical}
  .btn{
    display:inline-block;padding:10px 14px;border-radius:10px;border:none;background:var(--purple);color:white;font-weight:700;cursor:pointer;margin-top:6px;
  }
  .btn.alt{background:var(--orange);color:white}
  .small{font-size:13px;color:#666}
  .list-item{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid #f0edf9}
  .list-item img{width:72px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0}
  .list-item .meta{flex:1;overflow:hidden;min-width:0}
  .list-item .meta strong{display:block;word-break:break-word;overflow-wrap:anywhere;line-height:1.3}
  .danger{background:#ffe6e6;color:#c62828;border-radius:8px;padding:6px 8px;border:none;cursor:pointer}
  .preview-iframe{width:100%;height:180px;border:0;border-radius:8px;overflow:hidden;pointer-events:none}
  .muted{color:#777;font-size:13px}
  .small-action{font-size:13px;color:var(--purple-dark);cursor:pointer;text-decoration:underline;background:none;border:none}
  .employee-thumb{width:40px;height:40px;border-radius:50%;object-fit:cover}
  /* responsive */
  @media (max-width:900px){
    .sidebar{display:none}
    body{flex-direction:column}
    .main{padding:14px}
  }

  /* employee card styles used inside employee management */
  .date-time-group {
    background: #faf5ff;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    border: 1px solid #E0E7FF;
  }
  .card .small-delete {
    background:#ffefef;color:#b43a3a;border:none;padding:6px 8px;border-radius:8px;cursor:pointer;font-weight:700;
  }
</style>
</head>
<body>

  <aside class="sidebar">
    <div class="brand">
      <div class="logo">PP</div>
      <div>
        <h1>Admin Panel</h1>
        <small class="small">Digital Information Display</small>
      </div>
    </div>

    <nav class="nav" aria-label="Main nav">
      <button class="nav-btn active" data-target="section-videos">🎬 Video</button>
      <button class="nav-btn" data-target="section-banners">🖼️ Banner</button>
      <button class="nav-btn" data-target="section-employees-manage">👤 Manage Employees</button>
      <button class="nav-btn" data-target="section-employees-duty">👥 Manage Duty</button>
      <button class="nav-btn" data-target="section-footer">📝 Footer</button>
      <button class="nav-btn" data-target="section-ann">📢 Announcements</button>
      <button class="nav-btn" data-target="section-theme">🎨 Change Theme</button>
      <button class="nav-btn" data-target="section-manage-admin">🔐 Manage Admin</button>
    </nav>

    <button class="logout" onclick="location.href='logout.php'">Logout</button>
  </aside>

  <main class="main">
    <div class="topbar">
   
      <div>
        <?php if ($flash): ?>
          <div class="flash"><?php echo htmlspecialchars($flash); ?></div>
        <?php elseif ($msg): ?>
          <div class="flash"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <section id="section-videos" class="card section">
      <h3>🎬 Manage Video</h3>
      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="upload_video">
        <label>YouTube URL</label>
        <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." required>
        <button class="btn" type="submit">Add Video</button>
      </form>

      <h4>Video List</h4>
      <div style="margin-top:8px">
        <?php if (count($videos) > 0): ?>
          <div class="grid">
            <?php foreach ($videos as $v): ?>
              <div class="card">
                <iframe class="preview-iframe" src="<?php echo htmlspecialchars($v['youtube_url']); ?>" allowfullscreen></iframe>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
                  <div class="muted">Added: <?php echo htmlspecialchars($v['uploaded_at']); ?></div> 
                  <div>
                    <a class="small-action" href="dashboard.php?delete_type=video&id=<?php echo $v['id']; ?>" onclick="return confirm('Delete this video?')">Delete</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="small muted">No videos uploaded.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="section-banners" class="card section" style="display:none">
      <h3>🖼️ Manage Banner</h3>
      <form method="post" enctype="multipart/form-data" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="upload_banner">
        <label>Select Banner Image</label>
        <input type="file" name="banner_image" accept="image/*" required>
        <button class="btn" type="submit">Upload Banner</button>
      </form>

      <h4>Banner List</h4>
      <div style="margin-top:8px">
        <?php if (count($banners) > 0): 
          // Separate banners by orientation
          $portraitBanners = array_filter($banners, function($b) {
            return (isset($b['orientation']) && $b['orientation'] === 'portrait');
          });
          $landscapeBanners = array_filter($banners, function($b) {
            return (!isset($b['orientation']) || $b['orientation'] === 'landscape');
          });
        ?>
          
          <!-- Two-column container: Portrait (left) | Landscape (right) -->
          <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">

            <!-- Portrait Banners Section -->
            <div style="flex:1 1 360px;background:#e3f2fd;padding:16px;border-radius:10px;min-width:320px;">
              <h4 style="margin-top:0;color:#1976d2;display:flex;align-items:center;gap:8px;font-size:22px;font-weight:700;">
                📱 Portrait Banners (A4)
                <span style="font-size:16px;font-weight:600;color:#444;">(For Design 3 & 4)</span>
              </h4>
              <?php if (count($portraitBanners) > 0): ?>
                <div class="grid">
                  <?php foreach ($portraitBanners as $b): 
                    $isHidden = isset($b['is_hidden']) && $b['is_hidden'] == 1;
                  ?>
                    <div class="card" style="<?php echo $isHidden ? 'opacity:0.5;' : ''; ?>">
                      <img src="<?php echo 'assets/banners/' . htmlspecialchars($b['image_path']); ?>" alt="" style="width:100%;height:180px;object-fit:contain;border-radius:8px;margin-bottom:8px;background:#f5f5f5;">
                      <div style="margin-bottom:8px;">
                        <?php if ($isHidden): ?>
                          <span style="background:#ffe6e6;color:#c62828;padding:2px 6px;border-radius:4px;font-size:11px;">Hidden</span>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                        <div class="muted" style="font-size:11px;">Added: <?php echo htmlspecialchars($b['uploaded_at']); ?></div>
                        <div style="display:flex;gap:6px;">
                          <a class="small-action" href="dashboard.php?toggle_banner=<?php echo $b['id']; ?>" style="font-size:12px;"><?php echo $isHidden ? 'Show' : 'Hide'; ?></a>
                          <a class="small-action" href="dashboard.php?delete_type=banner&id=<?php echo $b['id']; ?>" onclick="return confirm('Delete this banner?')" style="color:#c62828;font-size:12px;">Delete</a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="small muted">No portrait banners uploaded.</p>
              <?php endif; ?>
            </div>

            <!-- Landscape Banners Section -->
            <div style="flex:1 1 360px;background:#fff3e0;padding:16px;border-radius:10px;min-width:320px;">
              <h4 style="margin-top:0;color:#f57c00;display:flex;align-items:center;gap:8px;font-size:22px;font-weight:700;">
                🖼️ Landscape Banners (16:9)
                <span style="font-size:16px;font-weight:600;color:#444;">(For Design 1 & 2)</span>
              </h4>
              <?php if (count($landscapeBanners) > 0): ?>
                <div class="grid">
                  <?php foreach ($landscapeBanners as $b): 
                    $isHidden = isset($b['is_hidden']) && $b['is_hidden'] == 1;
                  ?>
                    <div class="card" style="<?php echo $isHidden ? 'opacity:0.5;' : ''; ?>">
                      <img src="<?php echo 'assets/banners/' . htmlspecialchars($b['image_path']); ?>" alt="" style="width:100%;height:180px;object-fit:contain;border-radius:8px;margin-bottom:8px;background:#f5f5f5;">
                      <div style="margin-bottom:8px;">
                        <?php if ($isHidden): ?>
                          <span style="background:#ffe6e6;color:#c62828;padding:2px 6px;border-radius:4px;font-size:11px;">Hidden</span>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                        <div class="muted" style="font-size:11px;">Added: <?php echo htmlspecialchars($b['uploaded_at']); ?></div>
                        <div style="display:flex;gap:6px;">
                          <a class="small-action" href="dashboard.php?toggle_banner=<?php echo $b['id']; ?>" style="font-size:12px;"><?php echo $isHidden ? 'Show' : 'Hide'; ?></a>
                          <a class="small-action" href="dashboard.php?delete_type=banner&id=<?php echo $b['id']; ?>" onclick="return confirm('Delete this banner?')" style="color:#c62828;font-size:12px;">Delete</a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="small muted">No landscape banners uploaded.</p>
              <?php endif; ?>
            </div>

          </div>
          
        <?php else: ?>
          <p class="small muted">No banners uploaded.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="section-employees-manage" class="card section" style="display:none">
      <h3>👤 Manage Employees</h3>

      <form method="post" enctype="multipart/form-data" style="margin-bottom:14px;">
        <input type="hidden" name="action" value="add_employee">
        <label>Employee Name</label>
        <input type="text" name="name" placeholder="Employee name..." required>
        <label>Photo (optional)</label>
        <input type="file" name="employee_image" accept="image/*">
        <button class="btn" type="submit">Add Employee</button>
      </form>

      <h4>Employee List</h4>
      <?php if (count($employees) > 0): ?>
        <div style="margin-top:8px">
          <div class="grid">
            <?php foreach ($employees as $emp): ?>
              <div class="card" style="text-align:center;">
                <?php if (!empty($emp['image_path']) && file_exists(__DIR__ . '/assets/employees/' . $emp['image_path'])): ?>
                  <img src="<?php echo 'assets/employees/' . htmlspecialchars($emp['image_path']); ?>" alt="" style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;">
                <?php else: ?>
                  <div style="width:100px;height:100px;border-radius:50%;background:#eee;margin:0 auto 12px;"></div>
                <?php endif; ?>
                <strong style="display:block;margin-bottom:10px;"><?php echo htmlspecialchars($emp['name']); ?></strong>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:8px;">
                  <a class="small-action" href="dashboard.php?edit_employee=<?php echo $emp['id']; ?>#section-employees-manage">Edit</a>
                  <a class="small-action" href="dashboard.php?delete_type=employee&id=<?php echo $emp['id']; ?>" onclick="return confirm('Delete this employee and all schedules?')" style="color:#c62828;">Delete</a>
                </div>

                <?php if (isset($_GET['edit_employee']) && intval($_GET['edit_employee']) === intval($emp['id'])): ?>
                  <form method="post" enctype="multipart/form-data" style="margin-top:8px;text-align:left;">
                    <input type="hidden" name="action" value="update_employee">
                    <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                    <label>Employee Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($emp['name']); ?>" required>
                    <label>Replace Photo (optional)</label>
                    <input type="file" name="employee_image" accept="image/*">
                    <button class="btn" type="submit" style="margin-top:8px;">Save Changes</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <p class="small muted">No employees saved.</p>
      <?php endif; ?>
    </section>

    <section id="section-employees-duty" class="card section" style="display:none">
      <h3>👥 Manage Duty</h3>

      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="add_duty">
        <label>Select Employee</label>
        <select name="employee_id" required>
          <option value="">-- Select employee --</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <div id="dateTimeContainer">
          <div class="date-time-group">
            <label>Start Date:</label>
            <input type="date" name="duty_start_date[]" required>

            <label>End Date:</label>
            <input type="date" name="duty_end_date[]" required>

            <label>Start Time:</label>
            <input type="time" name="duty_start[]" required>

            <label>End Time:</label>
            <input type="time" name="duty_end[]" required>
          </div>
        </div>

        <button type="submit" class="btn" style="background:#6366F1;">Save Duty</button>
      </form>

      <h4>📅 Duty Schedule List</h4>
      <?php if (count($duties) > 0): ?>
        <div class="grid" style="margin-top:10px;">
          <?php 
          $today = date('Y-m-d');
          foreach ($duties as $d): 
            $dutyStartDate = $d['duty_date_start'];
            $dutyEndDate = $d['duty_date_end'];
            $status = '';
            $statusColor = '';
            
            if ($dutyEndDate < $today) {
              $status = 'Past';
              $statusColor = 'background:#e0e0e0;color:#666;';
            } elseif ($dutyStartDate <= $today && $dutyEndDate >= $today) {
              $status = 'Active';
              $statusColor = 'background:#4caf50;color:white;';
            } else {
              $status = 'Upcoming';
              $statusColor = 'background:#2196F3;color:white;';
            }
            
            // Format dates
            $startDateObj = new DateTime($dutyStartDate);
            $endDateObj = new DateTime($dutyEndDate);
            $formattedStartDate = $startDateObj->format('M d, Y');
            $formattedEndDate = $endDateObj->format('M d, Y');
            $startDayOfWeek = $startDateObj->format('l');
            $endDayOfWeek = $endDateObj->format('l');
            
            // Display range or single day
            if ($dutyStartDate === $dutyEndDate) {
              $dateDisplay = $formattedStartDate . ' (' . $startDayOfWeek . ')';
            } else {
              $dateDisplay = $formattedStartDate . ' (' . $startDayOfWeek . ') → ' . $formattedEndDate . ' (' . $endDayOfWeek . ')';
            }
            
            // Calculate duration
            $start = new DateTime($d['duty_time_start']);
            $end = new DateTime($d['duty_time_end']);
            $interval = $start->diff($end);
            $duration = $interval->format('%h hrs %i min');
          ?>
            <div class="card" style="position:relative;">
              <span style="position:absolute;top:10px;right:10px;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;<?php echo $statusColor; ?>"><?php echo $status; ?></span>
              
              <div style="text-align:center;margin-bottom:12px;">
                <?php if (!empty($d['employee_image']) && file_exists(__DIR__ . '/assets/employees/' . $d['employee_image'])): ?>
                  <img src="<?php echo 'assets/employees/' . htmlspecialchars($d['employee_image']); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:8px;">
                <?php else: ?>
                  <div style="width:80px;height:80px;border-radius:50%;background:#eee;margin:0 auto 8px;"></div>
                <?php endif; ?>
                <strong style="display:block;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($d['employee_name']); ?></strong>
              </div>
              
              <div style="background:#f5f5f5;padding:10px;border-radius:8px;margin-bottom:10px;">
                <div style="margin-bottom:6px;">
                  <span style="font-weight:600;">📅 <?php echo $dateDisplay; ?></span>
                </div>
                <div style="margin-bottom:6px;">
                  <span style="font-weight:600;">🕐 <?php echo date('g:i A', strtotime($d['duty_time_start'])); ?> - <?php echo date('g:i A', strtotime($d['duty_time_end'])); ?></span>
                </div>
                <div style="color:#666;font-size:13px;">
                  ⏱️ Duration: <?php echo $duration; ?>
                </div>
              </div>
              
              <div style="text-align:center;">
                <a class="small-action" href="dashboard.php?delete_type=duty&id=<?php echo $d['id']; ?>" onclick="return confirm('Delete this duty record?')" style="color:#c62828;">Delete</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="small muted">No duty schedules saved.</p>
      <?php endif; ?>
    </section>

    <section id="section-footer" class="card section" style="display:none">
      <h3>📝 Update Footer Text</h3>
      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="update_footer">
        <label>Footer Text</label>
        <textarea name="footer_message" required><?php echo htmlspecialchars($footer_message); ?></textarea>
        <button class="btn" type="submit">Update Footer</button>
      </form>

      <?php if ($footer_message): ?>
        <div style="margin-top:12px" class="card">
          <strong>Preview (Marquee):</strong>
          <div style="background:linear-gradient(90deg,var(--purple),var(--orange));border-radius:8px;padding:10px;margin-top:8px;color:white;overflow:hidden;white-space:nowrap">
            <div style="display:inline-block;padding-left:100%;animation: marquee 18s linear infinite"><?php echo htmlspecialchars($footer_message); ?></div>
          </div>
        </div>
        <style>
          @keyframes marquee { from { transform: translateX(0%);} to { transform: translateX(-100%);} }
        </style>
      <?php else: ?>
        <p class="small muted">No footer text saved.</p>
      <?php endif; ?>
    </section>

    <section id="section-ann" class="card section" style="display:none">
      <h3>📢 Manage Announcements</h3>

      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="add_announcement">
        <label>Announcement Text</label>
        <textarea name="announcement" placeholder="Enter announcement text..." required></textarea>
        <button class="btn" type="submit">Add Announcement</button>
      </form>

      <h4>Announcement List (loop)</h4>
      <?php if (count($announcements) > 0): ?>
        <div style="margin-top:8px">
          <?php foreach ($announcements as $a): ?>
            <div class="list-item">
              <div class="meta">
                <strong><?php echo htmlspecialchars($a['text']); ?></strong>
                <div class="muted">Added: <?php echo htmlspecialchars($a['created_at']); ?></div>
              </div>
              <div>
                <a class="small-action" href="dashboard.php?delete_type=announcement&id=<?php echo $a['id']; ?>" onclick="return confirm('Delete this announcement?')">Delete</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="small muted" style="margin-top:8px">Note: These announcements can be looped on the main display.</p>
      <?php else: ?>
        <p class="small muted">No announcements available.</p>
      <?php endif; ?>
    </section>

    <section id="section-theme" class="card section" style="display:none">
      <h3>🎨 Change Main Display Theme (Index)</h3>
      <form method="post" style="margin-bottom:12px;">
        <input type="hidden" name="action" value="update_theme">

        <div style="margin-top:10px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
          <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:20px;">
            <label style="display:block; border:1px solid #e6e2f8; border-radius:10px; padding:12px; cursor:pointer;">
              <div style="height:260px; border-radius:8px; overflow:hidden; align-items:center; margin-bottom:8px; background:#000;">
                <iframe class="preview-iframe" style="height:260px;" src="index.php?preview=design1" title="Preview Design 1"></iframe>
              </div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input type="radio" name="design_name" value="design1" <?php echo ($active_design === 'design1') ? 'checked' : ''; ?>>
                <strong>Design 1</strong>
                
              </div>
            </label>

            <label style="display:block; border:1px solid #e6e2f8; border-radius:10px; padding:12px; cursor:pointer;">
              <div style="height:260px; border-radius:8px; overflow:hidden; margin-bottom:8px; background:#000;">
                <iframe class="preview-iframe" style="height:260px;" src="index.php?preview=design2" title="Preview Design 2"></iframe>
              </div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input type="radio" name="design_name" value="design2" <?php echo ($active_design === 'design2') ? 'checked' : ''; ?>>
                <strong>Design 2</strong>
                
              </div>
            </label>

            <label style="display:block; border:1px solid #e6e2f8; border-radius:10px; padding:12px; cursor:pointer;">
              <div style="height:260px;border-radius:8px; overflow:hidden; margin-bottom:8px; background:#000;">
                <iframe class="preview-iframe" style="height:260px;" src="index.php?preview=design3" title="Preview Design 3"></iframe>
              </div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input type="radio" name="design_name" value="design3" <?php echo ($active_design === 'design3') ? 'checked' : ''; ?>>
                <strong>Design 3</strong>
              
              </div>
            </label>

            <label style="display:block; border:1px solid #e6e2f8; border-radius:10px; padding:12px; cursor:pointer;">
              <div style="height:260px; border-radius:8px; overflow:hidden; margin-bottom:8px; background:#000;">
                <iframe class="preview-iframe" style="height:260px;" src="index.php?preview=design4" title="Preview Design 4"></iframe>
              </div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input type="radio" name="design_name" value="design4" <?php echo ($active_design === 'design4') ? 'checked' : ''; ?>>
                <strong>Design 4</strong>
        
              </div>
            </label>
          </div>
        </div>

        <button class="btn" type="submit" style="margin-top:16px;">Save Theme</button>
      </form>
    </section>

    <section id="section-manage-admin" class="card section" style="display:none">
      <h3>🔐 Manage Admin Accounts</h3>
      
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
        
        <!-- Create New Admin Box -->
        <div style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 4px 15px rgba(0,0,0,0.08);">
          <h4 style="margin-top:0;color:#7a42f4;font-size:20px;margin-bottom:20px;">➕ Create New Admin</h4>
          <form method="post">
            <input type="hidden" name="action" value="create_admin">
            
            <div style="margin-bottom:15px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">Username</label>
              <input type="text" name="username" placeholder="Enter username" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;">
            </div>
            
            <div style="margin-bottom:15px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">Password</label>
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="password" id="createAdminPassword" name="password" placeholder="Enter password" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;">
                <button type="button" onclick="togglePasswordField('createAdminPassword', this)" style="background:#fff;border:1px solid #ddd;border-radius:10px;cursor:pointer;padding:12px;min-width:45px;height:46px;display:flex;align-items:center;justify-content:center;transition:0.3s;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
            
            <div style="margin-bottom:20px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">Confirm Password</label>
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="password" id="createAdminConfirmPassword" name="confirm_password" placeholder="Confirm password" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;">
                <button type="button" onclick="togglePasswordField('createAdminConfirmPassword', this)" style="background:#fff;border:1px solid #ddd;border-radius:10px;cursor:pointer;padding:12px;min-width:45px;height:46px;display:flex;align-items:center;justify-content:center;transition:0.3s;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
            
            <button class="btn" type="submit" style="width:100%;background:linear-gradient(to right, #7a42f4, #ff8a00);padding:12px;font-size:16px;">Create Admin</button>
          </form>
        </div>

        <!-- Update Password Box -->
        <div style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 4px 15px rgba(0,0,0,0.08);">
          <h4 style="margin-top:0;color:#7a42f4;font-size:20px;margin-bottom:20px;">🔑 Update Admin Password</h4>
          <form method="post">
            <input type="hidden" name="action" value="update_admin_password">
            
            <div style="margin-bottom:15px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">Select Admin</label>
              <select name="admin_id" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;background:#fff;">
                <option value="">-- Select Admin --</option>
                <?php foreach ($admins as $adm): ?>
                  <option value="<?php echo $adm['id']; ?>"><?php echo htmlspecialchars($adm['username']); ?><?php echo ($adm['id'] == $_SESSION['admin']) ? ' (You)' : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div style="margin-bottom:15px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">New Password</label>
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="password" id="updatePassword" name="new_password" placeholder="Enter new password" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;">
                <button type="button" onclick="togglePasswordField('updatePassword', this)" style="background:#fff;border:1px solid #ddd;border-radius:10px;cursor:pointer;padding:12px;min-width:45px;height:46px;display:flex;align-items:center;justify-content:center;transition:0.3s;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
            
            <div style="margin-bottom:20px;">
              <label style="font-size:14px;color:#666;margin-bottom:6px;display:block;">Confirm New Password</label>
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="password" id="updateConfirmPassword" name="confirm_new_password" placeholder="Confirm new password" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:10px;font-size:15px;transition:0.3s;">
                <button type="button" onclick="togglePasswordField('updateConfirmPassword', this)" style="background:#fff;border:1px solid #ddd;border-radius:10px;cursor:pointer;padding:12px;min-width:45px;height:46px;display:flex;align-items:center;justify-content:center;transition:0.3s;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
            
            <button class="btn" type="submit" style="width:100%;background:linear-gradient(to right, #2196F3, #00bcd4);padding:12px;font-size:16px;">Update Password</button>
          </form>
        </div>

      </div>
      
      <p class="small muted" style="margin-top:20px;text-align:center;">All passwords are securely hashed before storage.</p>
    </section>

  </main>

<script>
  // Sidebar navigation behavior
  var navBtns = document.querySelectorAll('.nav-btn');
  var sections = document.querySelectorAll('.section');
  
  function showSection(sectionId) {
    navBtns.forEach(function(b){ b.classList.remove('active'); });
    sections.forEach(function(s){ s.style.display = 'none'; });
    var targetSection = document.getElementById(sectionId);
    if (targetSection) {
      targetSection.style.display = 'block';
      // Activate corresponding nav button
      navBtns.forEach(function(btn){
        if (btn.dataset.target === sectionId) {
          btn.classList.add('active');
        }
      });
      document.querySelector('.main').scrollTop = 0;
    }
  }
  
  navBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      showSection(btn.dataset.target);
      // Save current section to sessionStorage
      sessionStorage.setItem('activeSection', btn.dataset.target);
    });
  });

  // Check URL for section parameter to preserve section after redirect
  var urlParams = new URLSearchParams(window.location.search);
  var sectionParam = urlParams.get('section');
  if (sectionParam) {
    showSection(sectionParam);
    sessionStorage.setItem('activeSection', sectionParam);
  } else {
    // Check sessionStorage for last active section (for page refresh)
    var savedSection = sessionStorage.getItem('activeSection');
    if (savedSection && document.getElementById(savedSection)) {
      showSection(savedSection);
    } else {
      // Initialize: show first section by default
      document.getElementById('section-videos').style.display = 'block';
      sessionStorage.setItem('activeSection', 'section-videos');
    }
  }
  
  // Auto-dismiss flash messages after 4 seconds
  var flashEl = document.querySelector('.flash');
  if (flashEl) {
    setTimeout(function() {
      flashEl.classList.add('fade-out');
      setTimeout(function() {
        flashEl.style.display = 'none';
      }, 500); // Wait for fade animation to complete
    }, 4000);
  }

  // Toggle password visibility
  function togglePasswordField(fieldId, btn) {
      var field = document.getElementById(fieldId);
      if (field.type === 'password') {
          field.type = 'text';
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
      } else {
          field.type = 'password';
          btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
      }
  }
</script>

</body>
</html>