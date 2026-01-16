<?php
session_start();
include 'db.php';
// Removed die check for DB connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "❌ Username atau kata laluan salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - PPKTDID</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body, html {
        height: 100%;
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #4b0082, #7c1dc9);
    }

    body {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .sign-in {
        width: 100%;
        max-width: 400px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        padding: 40px;
        animation: fadeInUp 0.6s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .sign-in h2 {
        text-align: center;
        color: #7a42f4;
        font-size: 28px;
        margin-bottom: 25px;
        font-weight: 600;
    }

    .error-box {
        background-color: #ffe0e0;
        color: #a10000;
        padding: 10px 15px;
        border: 1px solid #ff4e4e;
        border-radius: 8px;
        text-align: center;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    input[type="text"],
    input[type="password"] {
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #ddd;
        font-size: 15px;
        transition: 0.3s;
        width: 100%;
        font-family: 'Poppins', sans-serif;
    }

    input:focus {
        border-color: #7a42f4;
        outline: none;
        box-shadow: 0 0 6px rgba(122, 66, 244, 0.3);
    }

    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .password-wrapper input {
        flex: 1;
    }

    .password-wrapper button {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 10px;
        cursor: pointer;
        padding: 12px;
        min-width: 45px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
    }

    .password-wrapper button:hover {
        border-color: #7a42f4;
        background: #f9f5ff;
    }

    .login-button {
        margin-top: 20px;
        width: 100%;
        padding: 12px;
        background: linear-gradient(to right, #7a42f4, #ff8a00);
        border: none;
        color: white;
        font-size: 16px;
        font-weight: 600;
        border-radius: 10px;
        cursor: pointer;
        transition: transform 0.2s ease;
        font-family: 'Poppins', sans-serif;
    }

    .login-button:hover {
        transform: scale(1.03);
    }

    .footer {
        margin-top: 25px;
        text-align: center;
        font-size: 12px;
        color: #999;
    }

    @media (max-width: 480px) {
        .sign-in {
            padding: 30px 25px;
        }
        
        .sign-in h2 {
            font-size: 24px;
        }
    }
</style>
</head>
<body>

<div class="sign-in">
    <h2>Admin Login</h2>
    
    <?php if (isset($error)): ?>
        <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <input type="text" name="username" placeholder="Masukkan Username" required>
            
            <div class="password-wrapper">
                <input type="password" id="loginPassword" name="password" placeholder="Masukkan Kata Laluan" required>
                <button type="button" onclick="togglePassword('loginPassword', this)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="login-button">LOG IN</button>
    </form>
    
    <div class="footer">© <?= date('Y') ?> PTDDID | USM Engineering Campus</div>
</div>

<script>
function togglePassword(fieldId, btn) {
    var field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
    } else {
        field.type = 'password';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
}
</script>

</body>
</html>
