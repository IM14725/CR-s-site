<?php
declare(strict_types=1);
require_once 'include/auth.php';

const MAX_ATTEMPTS = 5; //wrong password attempts
const MAX_IP_ATTEMPTS = 30; //wrong password attempts from same IP (campus ip)
const LOCK_MINUTES = 15; //lockout duration in minutes
const DUMMY_HASH = '$2y$10$/YO.aNgP.NfmoVI62RKbTu0nb/uUIsgkgd1wx3MruTRp7H7NMSnj6'; //dummy hash for timing attack prevention

// Check if the user is already logged in
if (($_SESSION['role'] ?? null) === 'admin') { header('Location: admin.php'); exit;}
if (($_SESSION['role'] ?? null) === 'cr') { header('Location: index.php'); exit;}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      verify_csrf();

      $raw  = trim((string)($_POST['regNumber'] ?? ''));
      $password = (string)($_POST['password'] ?? '');
      $ip = $_SERVER['REMOTE_ADDR'] ?? '';

      $isCr  = preg_match(REG_PATTERN, strtoupper($raw)) === 1;
      $identifier = $isCr ? strtoupper($raw) : $raw;

      //RATE LIMITING
      $since = 'NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE';
      $byId = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > $since");
      $byId->execute([$identifier]);
      $byIp = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > $since");
      $byIp->execute([$ip]);

      if ($byId->fetchColumn() >= MAX_ATTEMPTS || $byIp->fetchColumn() >= MAX_IP_ATTEMPTS) {
            $error = 'Too many failed login attempts. Please try again in' . LOCK_MINUTES . ' minutes.';
      } else {
            if ($isCr) {
                  $table = 'crs'; $column = 'reg_number'; $role = 'cr'; $home = 'index.php';
            } else {
                  $table = 'admins'; $column = 'username'; $role = 'admin'; $home = 'admin.php';
            }

            $stmt = db()->prepare("SELECT id, password FROM $table WHERE $column = ?");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();

            // Use dummy hash to prevent timing attacks
            $valid = password_verify($password, $user['password'] ?? DUMMY_HASH) && $user !== false;

            if ($valid) {
                  db()->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$identifier]);

                  if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        db()->prepare("UPDATE $table SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                  }
            login_user((string)$user['id'], $role);
            header("Location: $home");
            exit;
            }
            db()->prepare("INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)")->execute([$identifier, $ip]);
            $error = 'Invalid registration number or password.';
      }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <title>login Page</title>
</head>
<body>
   <section class="container">
      <form action="" method="POST" class="formContainer" autocomplete="off">
            <?= csrf_field() ?>
            <div class="NITlogo">
                  <img src="assets/image/nit.jpeg" alt="NIT Logo" class="logo">
            </div>
            <h3>NIT Crs Venue Report </h3>

            <?php if ($error !== ''): ?>
                  <p class="errorMessage" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <div class="formInput">
                  <label for="regNumber">Registration Number / Username</label>
                  <input type="text" name="regNumber" placeholder="NIT/BBA/20XX/XXX" id="regNumber" class="inputText" value="<?= e($identifier) ?>" maxlength="50" required>
            </div>
            <div class="formInput">
                  <label for="password">Password</label>
                  <input type="password" name="password" placeholder="Enter your password" id="password" class="inputText" maxlength="255" required>
                  <div class="showPassword">
                        <input type="checkbox" name="showPassword" id="showPassword">
                        <label for="showPassword"><small>Show Password</small></label>
                  </div>
            </div>
            <br>

            <div class="reportBtnContainer">
                  <button type="submit" class="reportBtn">Login</button>
            </div>
      </form>
   </section>

      <script>
            const showPassword = document.querySelector('#showPassword');
            const passwordInput = document.querySelector('#password');
   
            showPassword.addEventListener('change', () => {
               if (showPassword.checked) {
                     passwordInput.type = 'text';
               } else {
                     passwordInput.type = 'password';
               }
            });
      </script>
</body>
</html>
