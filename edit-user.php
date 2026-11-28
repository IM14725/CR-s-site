<?php
declare(strict_types=1);
require_once 'include/auth.php';
require_once 'include/user_helpers.php';

$me = require_staff();
$roles = manageable_roles($me['role']);

// ---------- Load the account being edited ----------
$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$stmt = db()->prepare('SELECT id, role, full_name, reg_number, level, stream FROM users WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch();

// Only accounts this user is allowed to manage (Ministry can't touch Ministry or Admin).
if (!$target || !can_manage($me['role'], $target['role'])) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Account not found or not yours to manage.'];
    header('Location: admin.php');
    exit;
}

$errors = [];
$old = [
    'fullName'  => $target['full_name'],
    'regNumber' => $target['reg_number'],
    'level'     => $target['level'],
    'stream'    => $target['stream'],
    'role'      => $target['role'],
];

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----- Save changes -----
    if ($action === 'update') {
        [$old, $errors] = validate_member($_POST);
        $old['role'] = (string)($_POST['role'] ?? $target['role']);

        if (!can_manage($me['role'], $old['role'])) {
            $errors[] = 'You are not allowed to give this role.';
        }

        if (!$errors) {
            try {
                db()->prepare('UPDATE users SET role = ?, full_name = ?, reg_number = ?, level = ?, stream = ? WHERE id = ?')
                    ->execute([$old['role'], $old['fullName'], $old['regNumber'], $old['level'], $old['stream'], $target['id']]);

                $_SESSION['flash'] = ['type' => 'success', 'text' => "{$old['fullName']} updated."];
                header('Location: admin.php');
                exit;
            } catch (PDOException $e) {
                if (!is_duplicate($e)) {
                    throw $e;
                }
                $errors[] = "{$old['regNumber']} already belongs to another account.";
            }
        }
    }

    // ----- Reset password -----
    if ($action === 'reset_password') {
        $plainPassword = generate_password($target['full_name']);

        db()->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($plainPassword, PASSWORD_DEFAULT), $target['id']]);

        // Clear any lockout so they can log in with the new password straight away.
        db()->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$target['reg_number']]);

        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => "New password for {$target['full_name']} (shown only once): $plainPassword",
        ];
        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" />
</head>
<body>
    <section class="container">
        <h3 id="regHead">Edit <?= e(ROLE_LABELS[$target['role']]) ?>: <?= e($target['full_name']) ?></h3>

        <form method="post" class="formContainer" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($target['id']) ?>">

            <?php foreach ($errors as $error): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endforeach; ?>

            <?php if (count($roles) > 1): ?>
                <div class="formInput">
                    <label for="role">Role: </label>
                    <select name="role" id="role" class="inputText">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r) ?>" <?= $old['role'] === $r ? 'selected' : '' ?>><?= e(ROLE_LABELS[$r]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="role" value="<?= e($target['role']) ?>">
            <?php endif; ?>

            <div class="formInput">
                <label for="fullName">Full Name: </label>
                <input type="text" name="fullName" id="fullName" class="inputText"
                       value="<?= e($old['fullName']) ?>" maxlength="100" required>
            </div>

            <div class="formInput">
                <label for="regNumber">Registration Number: </label>
                <input type="text" name="regNumber" id="regNumber" class="inputText"
                       value="<?= e($old['regNumber']) ?>" maxlength="30" required>
            </div>

            <div class="setTimeContainer">
                <div class="setTime">
                    <label for="level">Level: </label>
                    <select name="level" id="level" class="inputText">
                        <?php foreach (LEVELS as $lvl): ?>
                            <option value="<?= e($lvl) ?>" <?= $old['level'] === $lvl ? 'selected' : '' ?>><?= e(ucfirst($lvl)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="setTime">
                    <label for="stream">Stream: </label>
                    <select name="stream" id="stream" class="inputText">
                        <?php foreach (STREAMS as $s): ?>
                            <option value="<?= e($s) ?>" <?= $old['stream'] === $s ? 'selected' : '' ?>>Stream <?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="reportBtnContainer">
                <a href="admin.php" class="actionBtn">Cancel</a>
                <input type="submit" value="Save Changes" class="reportBtn">
            </div>
        </form>

        <br>

        <form method="post" class="formContainer"
              onsubmit="return confirm(<?= e(json_encode('Reset the password for ' . $target['full_name'] . '? Their old password will stop working.')) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= e($target['id']) ?>">
            <p><small>Forgot their password? This creates a new one and shows it to you once.</small></p>
            <div class="reportBtnContainer">
                <button type="submit" class="actionBtn delete"><i class="fa-solid fa-key"></i> Reset Password</button>
            </div>
        </form>
    </section>
</body>
</html>
