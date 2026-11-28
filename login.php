<?php
declare(strict_types=1);
require_once 'include/auth.php';

const MAX_ATTEMPTS    = 5;   // wrong passwords per account
const MAX_IP_ATTEMPTS = 30;  // per IP (high, because a campus shares one IP)
const LOCK_MINUTES    = 15;
const DUMMY_HASH      = '$2y$10$/YO.aNgP.NfmoVI62RKbTu0nb/uUIsgkgd1wx3MruTRp7H7NMSnj6';

// Already logged in? Send them to their own page.
if ($user = current_user()) {
    header('Location: ' . home_page($user['role']));
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $raw      = trim((string)($_POST['regNumber'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

    // The format decides which accounts are checked: reg numbers -> CR/Ministry, anything else -> admin.
    $isCr       = preg_match(REG_PATTERN, strtoupper($raw)) === 1;
    $identifier = $isCr ? strtoupper($raw) : $raw;

    // ---- Rate limiting ----
    $since = 'NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE';
    $byId = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > $since");
    $byId->execute([$identifier]);
    $byIp = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > $since");
    $byIp->execute([$ip]);

    if ($byId->fetchColumn() >= MAX_ATTEMPTS || $byIp->fetchColumn() >= MAX_IP_ATTEMPTS) {
        $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
    } else {
        // Reg numbers can only match CR / Ministry accounts; usernames only match admins.
        if ($isCr) {
            $stmt = db()->prepare("SELECT id, role, password FROM users WHERE reg_number = ? AND role IN ('cr', 'ministry')");
        } else {
            $stmt = db()->prepare("SELECT id, role, password FROM users WHERE username = ? AND role = 'admin'");
        }
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        // Always verify (against a dummy hash if no user) so timing doesn't reveal which accounts exist.
        $valid = password_verify($password, $user['password'] ?? DUMMY_HASH) && $user !== false;

        if ($valid) {
            db()->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$identifier]);

            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }

            login_user($user['id']);
            header('Location: ' . home_page($user['role']));
            exit;
        }

        db()->prepare('INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)')->execute([$identifier, $ip]);
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
    <title>Login Page</title>
</head>
<body>
   <section class="container">
      <form action="" method="post" class="formContainer" autocomplete="off">
         <?= csrf_field() ?>
         <div class="NITlogo">
            <img src="./assets/image/nit.jpeg" alt="NIT logo">
         </div>
         <h3 id="NIT">NIT Crs Venue Report</h3>

         <?php if ($error !== ''): ?>
            <p class="errorMessage" role="alert"><?= e($error) ?></p>
         <?php endif; ?>

         <div class="formInput">
            <label for="regNumber">REG Number: </label>
            <input type="text" name="regNumber" placeholder="NIT/BIT/..." id="regNumber"
                   class="inputText" value="<?= e($identifier) ?>" maxlength="50" required>
         </div>

         <div class="formInput">
            <label for="password">Password: </label>
            <input type="password" name="password" placeholder="Enter your password" id="password"
                   class="inputText" maxlength="255" required>
            <div class="showPassword">
               <input type="checkbox" id="showPassword">
               <label for="showPassword"><small>Show Password</small></label>
            </div>
         </div>
         <br>

         <div class="reportBtnContainer">
            <input type="submit" value="Login" class="reportBtn">
         </div>
      </form>
   </section>

   <script>
      const showPassword = document.querySelector('#showPassword');
      const passwordInput = document.querySelector('#password');
      showPassword.addEventListener('change', () => {
         passwordInput.type = showPassword.checked ? 'text' : 'password';
      });
   </script>
</body>
</html>
