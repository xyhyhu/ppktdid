<?php
include 'db.php';

$dateToday = date('Y-m-d');

// --- AUTO CREATE FOLDERS ---
$folders = array("assets", "assets/banners", "assets/employees", "assets/logo");
foreach ($folders as $folder) {
    if (!is_dir($folder)) mkdir($folder, 0777, true);
}

// --- FETCH VIDEOS ---
$videos = $conn->query("SELECT youtube_url FROM videos ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- FETCH THEME (needed for banner filtering) ---
try {
    $themeRow = $conn->query("SELECT design_name FROM active_theme ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $design = isset($themeRow['design_name']) ? $themeRow['design_name'] : 'design1';
} catch (Exception $e) {
    $design = 'design1';
}

// --- FETCH BANNERS (only visible ones) ---
// Design 1 & 2: landscape only (16:9)
// Design 3 & 4: portrait only (A4)
$orientationFilter = (in_array($design, ['design3', 'design4'])) ? 'portrait' : 'landscape';

try {
    $stmt = $conn->prepare("SELECT image_path FROM banners WHERE is_hidden = 0 AND orientation = ? ORDER BY id DESC");
    $stmt->execute([$orientationFilter]);
    $banners = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // If orientation column doesn't exist, fetch all banners
    $banners = $conn->query("SELECT image_path FROM banners WHERE is_hidden = 0 ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
}

// --- FETCH EMPLOYEES FOR TODAY (NEW: USING duties TABLE) ---
$stmt = $conn->prepare("
    SELECT e.id, e.name, e.image_path, d.duty_date_start, d.duty_date_end, d.duty_time_start, d.duty_time_end
    FROM duties d
    JOIN employees e ON e.id = d.employee_id
    WHERE ? BETWEEN d.duty_date_start AND d.duty_date_end
    ORDER BY d.duty_time_start ASC
");
$stmt->execute([$dateToday]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- FETCH FOOTER TEXT ---
$footer = $conn->query("SELECT message FROM footer_text ORDER BY id DESC LIMIT 1")->fetchColumn();

// --- FETCH ALL ANNOUNCEMENTS (LOOP) ---
$announcements = array();
try {
    $stmt = $conn->query("SELECT text FROM announcements ORDER BY id DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $announcements = array();
}

// --- FETCH API DATA ---
$api_url = "https://ezbook.usm.my/api/get_sd_aduan.php";
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $api_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
$jumlah_aduan_baru = '#';
$jumlah_mohon_baru = '#';
if (is_array($data) && isset($data['success']) && $data['success'] && isset($data['data']) && is_array($data['data'])) {
    $jumlah_aduan_baru = isset($data['data']['jumlah_aduan_baru']) ? $data['data']['jumlah_aduan_baru'] : '#';
    $jumlah_mohon_baru = isset($data['data']['jumlah_mohon_baru']) ? $data['data']['jumlah_mohon_baru'] : '#';
}

// --- FETCH ACTIVE THEME ---
$themeFile = include('get_theme.php');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<title>SERVISDESK PUSAT TRANSFORMASI DIGITAL</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo htmlspecialchars($themeFile); ?>">
    </head>
<body>

<?php $isDesign3 = (isset($ACTIVE_DESIGN) && $ACTIVE_DESIGN === 'design3') || (isset($_GET['preview']) && $_GET['preview'] === 'design3'); ?>
<?php $isDesign4 = (isset($ACTIVE_DESIGN) && $ACTIVE_DESIGN === 'design4') || (isset($_GET['preview']) && $_GET['preview'] === 'design4'); ?>

<?php if ($isDesign3 || $isDesign4): ?>
    <div class="main-grid">
        <div class="left-section">
            <div id="video-wrapper">
                <div id="player"></div>
                <button id="unmuteButton">🔊 Tap to unmute</button>
            </div>

            <div class="bottom-row">
                <?php if (empty($employees)): ?>
                    <div class="employee-card">
                        <p style="margin-top:70px;">Tiada duty hari ini.</p>
                    </div>
                <?php else:
                    $count = 0;
                    foreach ($employees as $emp) {
                        if ($count >= 2) break; ?>
                        <div class="employee-card">
                            <img src="assets/employees/<?php echo htmlspecialchars($emp['image_path']); ?>" alt="Employee">
                            <p><?php echo htmlspecialchars($emp['name']); ?></p>
                            <small><?php echo htmlspecialchars($emp['duty_time_start']); ?> - <?php echo htmlspecialchars($emp['duty_time_end']); ?></small>
                        </div>
                <?php $count++; } endif; ?>

                <div class="card">
                    <img src="assets/images/slider/sdnew.png" alt="SD BR">
                    <h3><?php echo $isDesign4 ? 'Aduan Baru' : 'Aduan Baru'; ?></h3>
                    <h1><?php echo htmlspecialchars($jumlah_aduan_baru); ?></h1>
                </div>

                <div class="card">
                    <img src="assets/images/slider/sponew.png" alt="SPO BR">
                    <h3>Permohonan Baru</h3>
                    <h1><?php echo htmlspecialchars($jumlah_mohon_baru); ?></h1>
                </div>
            </div>
        </div>

        <div class="right-section">
            <div class="logo-area">
                <a href="login.php" style="cursor: pointer;">
                    <img src="assets/logo/logo.png" alt="Logo PPKTDID" style="cursor: pointer;">
                </a>
            </div>
            <div class="banner" id="bannerBox">
                <?php if (empty($banners)): ?>
                    <p style="text-align:center; color:#fff; margin-top:50%;">⚠️ Tiada banner dimuat naik.</p>
                <?php else: foreach($banners as $i => $img): ?>
                    <img src="assets/banners/<?php echo htmlspecialchars($img); ?>" class="<?php echo ($i==0 ? 'active' : ''); ?>">
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <header>
        <div class="header-logo">
            <a href="login.php" style="cursor: pointer;">
                <img src="assets/logo/logo.png" alt="Logo PPKTDID" style="cursor: pointer;">
            </a>
            <h1 class="header-title"> SERVISDESK PUSAT TRANSFORMASI DIGITAL 
            
            </h1>
        </div>
        <div class="status-counters">
            <div class="counter-box">
                <h3>ADUAN BARU</h3>
                <h1><?php echo htmlspecialchars($jumlah_aduan_baru); ?></h1>
            </div>
            <div class="counter-box">
                <h3>PERMOHONAN BARU</h3>
                <h1><?php echo htmlspecialchars($jumlah_mohon_baru); ?></h1>
            </div>
        </div>
    </header>

    <div class="main-grid">
        <div class="video-section">
            <div id="video-wrapper">
                <div id="player"></div>
                <button id="unmuteButton">🔊 Tap to unmute</button>
            </div>
        </div>

        <div class="right-section">
            <div class="employee-duty-area">
                <h2>STAFF ON DUTY</h2>
                <div class="employee-cards-container">
                    <?php
                    if (empty($employees)):
                    ?>
                        <div class="employee-card" style="width: 100%; border-style: dashed; display: flex; align-items: center; justify-content: center;">
                            <p style="text-align:center; font-size: 1.3rem;">NO DUTY</p>
                        </div>
                    <?php
                    else:
                        $count = 0;
                        foreach ($employees as $emp) {
                            if ($count >= 2) break;
                            ?>
                            <div class="employee-card">
                                <img src="assets/employees/<?php echo htmlspecialchars($emp['image_path']); ?>" alt="Employee">
                                <div class="employee-card-overlay">
                                    <p><?php echo htmlspecialchars($emp['name']); ?></p>
                                    <small><?php echo htmlspecialchars($emp['duty_time_start']); ?> - <?php echo htmlspecialchars($emp['duty_time_end']); ?></small>
                                </div>
                            </div>
                            <?php
                            $count++;
                        }
                    endif;
                    ?>
                </div>
            </div>
            
            <div class="banner" id="bannerBox">
                <?php if (empty($banners)): ?>
                    <p style="text-align:center; color:#bbb; margin-top:50%;">⚠️ Tiada banner dimuat naik.</p>
                <?php else: foreach($banners as $i => $img): ?>
                    <img src="assets/banners/<?php echo htmlspecialchars($img); ?>" class="<?php echo ($i==0 ? 'active' : ''); ?>">
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="announcement-bar">
    <?php if ($isDesign3 || $isDesign4): ?>
        <img src="assets/logo/announce.jpg" alt="Announcement Icon">
    <?php endif; ?>
    <p id="announcementText"><?php echo !empty($announcements[0]) ? htmlspecialchars($announcements[0]) : '📢 Tiada pengumuman terkini.'; ?></p>
</div>

<footer>
    <div id="dateBox"><?php echo strtoupper(date('l')) . '<br>' . strtoupper(date('d F Y')); ?></div>
    <p class="marquee"><?php echo htmlspecialchars($footer ? $footer : 'Selamat datang ke PPKTDID | USM Engineering Campus'); ?></p>
    <div id="clock"></div>
</footer>

<script>
// --- JAVASCRIPT ---
var IS_DESIGN3 = <?php echo $isDesign3 ? 'true' : 'false'; ?>;
var IS_DESIGN4 = <?php echo $isDesign4 ? 'true' : 'false'; ?>;
var videos = <?php echo json_encode($videos); ?>;
var currentVideo = 0;
var player;

// --- AUTO REFRESH: Track API data and theme ---
var currentAduanBaru = '<?php echo addslashes($jumlah_aduan_baru); ?>';
var currentMohonBaru = '<?php echo addslashes($jumlah_mohon_baru); ?>';
var currentTheme = '<?php echo isset($ACTIVE_DESIGN) ? addslashes($ACTIVE_DESIGN) : 'design1'; ?>';
var currentBannersHash = '<?php echo md5(implode('|', $banners)); ?>';
var currentDutiesHash = '<?php echo md5(json_encode($employees)); ?>';
var currentVideosHash = '<?php echo md5(implode('|', $videos)); ?>';
var currentAnnouncementsHash = '<?php echo md5(implode('|', $announcements)); ?>';
var currentFooterHash = '<?php echo md5($footer ?: ''); ?>';
var isPreview = <?php echo isset($_GET['preview']) ? 'true' : 'false'; ?>;
// --- SIMPLE REFRESH LOGIC (from index1 pattern) ---
if (!isPreview) {
  // Theme change check every 3s (fast reaction)
  setInterval(function(){
    fetch('check_updates.php?check=theme')
      .then(r=>r.json())
      .then(data=>{
        if (data.theme && data.theme !== currentTheme) {
          location.reload();
        }
      })
      .catch(err=>console.log('Theme check failed:', err));
  }, 3000);

  // Banner change check every 3s (orientation-aware hash provided by endpoint)
  setInterval(function(){
    fetch('check_updates.php?check=banners')
      .then(r=>r.json())
      .then(data=>{
        if (data.banners_hash && data.banners_hash !== currentBannersHash) {
          location.reload();
        }
      })
      .catch(err=>console.log('Banners check failed:', err));
  }, 3000);

  // Duties change check every 3s
  setInterval(function(){
    fetch('check_updates.php?check=duties')
      .then(r=>r.json())
      .then(data=>{
        if (data.duties_hash && data.duties_hash !== currentDutiesHash) {
          location.reload();
        }
      })
      .catch(err=>console.log('Duties check failed:', err));
  }, 3000);

  // Videos change check every 3s
  setInterval(function(){
    fetch('check_updates.php?check=videos')
      .then(r=>r.json())
      .then(data=>{
        if (data.videos_hash && data.videos_hash !== currentVideosHash) {
          location.reload();
        }
      })
      .catch(err=>console.log('Videos check failed:', err));
  }, 3000);

  // Announcements change check every 3s
  setInterval(function(){
    fetch('check_updates.php?check=announcements')
      .then(r=>r.json())
      .then(data=>{
        if (data.announcements_hash && data.announcements_hash !== currentAnnouncementsHash) {
          location.reload();
        }
      })
      .catch(err=>console.log('Announcements check failed:', err));
  }, 3000);

  // Footer change check every 3s
  setInterval(function(){
    fetch('check_updates.php?check=footer')
      .then(r=>r.json())
      .then(data=>{
        if (data.footer_hash && data.footer_hash !== currentFooterHash) {
          location.reload();
        }
      })
      .catch(err=>console.log('Footer check failed:', err));
  }, 3000);

  // API counters check every 30s (immediate reload on change)
  setInterval(function(){
    fetch('check_updates.php?check=api')
      .then(r=>r.json())
      .then(data=>{
        if (data.aduan_baru !== currentAduanBaru || data.mohon_baru !== currentMohonBaru) {
          location.reload();
        }
      })
      .catch(err=>console.log('API check failed:', err));
  }, 30000);
}function extractID(url) {
    var match = url.match(/(?:v=|\/embed\/|\.be\/)([A-Za-z0-9_-]{11})/);
    return match ? match[1] : null;
}
var tag = document.createElement('script');
tag.src = "https://www.youtube.com/iframe_api";
document.body.appendChild(tag);

function onYouTubeIframeAPIReady() {
    if (!videos || videos.length === 0) return;
    var videoId = extractID(videos[currentVideo]);
    player = new YT.Player('player', {
        videoId: videoId,
        playerVars: { autoplay: 1, controls: 0, mute: 1, rel: 0, modestbranding: 1 },
        events: { 'onStateChange': onPlayerStateChange }
    });
}
function onPlayerStateChange(event) {
    if (event.data === YT.PlayerState.ENDED) {
        currentVideo = (currentVideo + 1) % videos.length;
        var nextID = extractID(videos[currentVideo]);
        player.loadVideoById(nextID);
    }
}
var unmuteButton = document.getElementById('unmuteButton');
// Mute/Unmute toggle functionality
if (unmuteButton) {
    unmuteButton.addEventListener('click', function(){
        if (player) {
            if (player.isMuted()) {
                player.unMute();
                unmuteButton.innerText = '🔊 Unmuted';
            } else {
                player.mute();
                unmuteButton.innerText = '🔇 Muted';
            }
        }
    });
}

// ANNOUNCEMENT rotation
var announcements = <?php echo json_encode($announcements); ?>;
var currentAnnouncement = 0;
var announcementEl = document.getElementById("announcementText");

// Check if the element exists and if there is more than one announcement
if (announcementEl && announcements && announcements.length > 1) {
    
    setInterval(function() {
        // Move to the next announcement index
        currentAnnouncement = (currentAnnouncement + 1) % announcements.length;
        
        // 1. Fade out the text
        announcementEl.style.opacity = 0;
        
        // 2. Wait for the fade-out to finish (500ms matches CSS)
        setTimeout(function() {
            // 3. Change the text
            announcementEl.textContent = announcements[currentAnnouncement];
            
            // 4. Fade back in
            announcementEl.style.opacity = 1;
        }, 500); // This duration MUST match your CSS transition time

    }, 10000); // Change announcement every 10 seconds
}



// banner slideshow
var banners = document.querySelectorAll('.banner img');
var bIndex = 0;
setInterval(function(){
    if (!banners || banners.length === 0) return;
    banners[bIndex].classList.remove('active');
    bIndex = (bIndex + 1) % banners.length;
    banners[bIndex].classList.add('active');
}, 5000);

// clock
function updateClock(){
    var el = document.getElementById('clock');
    if (el) el.innerHTML = new Date().toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

// Toggle banner button removed; visibility managed via dashboard settings.

</script>
</body>
</html>