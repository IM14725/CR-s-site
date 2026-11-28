<?php
declare(strict_types=1);
require_once 'include/auth.php';
require_once 'include/user_helpers.php';

$me = require_staff();                         // Admin and Ministry only
$roles = manageable_roles($me['role']);        // admin: ministry + cr, ministry: cr

$errors = [];
$old    = ['fullName' => '', 'regNumber' => '', 'level' => LEVELS[0], 'stream' => STREAMS[0], 'role' => 'cr'];
$showForm = false;

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----- Register a new CR / Ministry member -----
    if ($action === 'register') {
        [$old, $errors] = validate_member($_POST);
        $old['role'] = (string)($_POST['role'] ?? 'cr');

        if (!can_manage($me['role'], $old['role'])) {
            $errors[] = 'You are not allowed to create this type of account.';
        }

        if (!$errors) {
            $plainPassword = generate_password($old['fullName']);
            try {
                db()->prepare('INSERT INTO users (role, full_name, reg_number, level, stream, password) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$old['role'], $old['fullName'], $old['regNumber'], $old['level'], $old['stream'],
                               password_hash($plainPassword, PASSWORD_DEFAULT)]);

                $label = ROLE_LABELS[$old['role']];
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'text' => "{$old['fullName']} registered as $label. Give them this password (shown only once): $plainPassword",
                ];
                header('Location: admin.php');
                exit;
            } catch (PDOException $e) {
                if (!is_duplicate($e)) {
                    throw $e;
                }
                $errors[] = "{$old['regNumber']} is already registered.";
            }
        }
        $showForm = true;
    }

    // ----- Delete a member -----
    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT id, role, full_name FROM users WHERE id = ?');
        $stmt->execute([(string)($_POST['id'] ?? '')]);
        $target = $stmt->fetch();

        if (!$target || !can_manage($me['role'], $target['role'])) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'You cannot delete this account.'];
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$target['id']]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => "{$target['full_name']} deleted."];
        }
        header('Location: admin.php');
        exit;
    }
}

// ---------- Read data for the page ----------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$placeholders = implode(',', array_fill(0, count($roles), '?'));
$search = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT id, role, full_name, reg_number, level, stream FROM users WHERE role IN ($placeholders)";
$params = $roles;
if ($search !== '') {
    $sql .= ' AND reg_number LIKE ?';
    $params[] = '%' . addcslashes(strtoupper($search), '%_\\') . '%';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);

// Group by role: Ministry section first (admin only), then CRs
$members = array_fill_keys($roles, []);
foreach ($stmt->fetchAll() as $row) {
    $members[$row['role']][] = $row;
}
$total = array_sum(array_map('count', $members));

$sectionTitles = ['ministry' => 'Manage Ministry of Education', 'cr' => 'Manage All Crs'];

function programme(?string $reg): string
{
    return explode('/', (string)$reg)[1] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" />
</head>
<body>
    <header>
        <div class="profile">
            <img src="assets/image/nit.jpeg" alt="NIT logo">
        </div>
        <h1>NIT Venue Report System</h1>
        <nav class="topNav">
            <a href="index.php" class="actionBtn"><i class="fa-solid fa-building"></i> Venues</a>
            <form method="post" action="logout.php" class="logoutForm">
                <?= csrf_field() ?>
                <button type="submit" class="actionBtn"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
            </form>
        </nav>
    </header>

    <section class="container">
        <div class="innerContainer">
            <div class="welcome">
                <h2>Welcome, <?= e(explode(' ', $me['full_name'])[0]) ?>!</h2>
                <p><?= e(ROLE_LABELS[$me['role']]) ?> Panel</p>
            </div>
            <div class="infContainer">
                <div class="info">
                    <p><?= e($me['reg_number'] ?? $me['username']) ?></p>
                    <p><?= e($me['full_name']) ?></p>
                </div>
                <div class="info">
                    <p><?= e(ROLE_LABELS[$me['role']]) ?></p>
                    <p><?= $total ?> account<?= $total === 1 ? '' : 's' ?><?= $search !== '' ? ' found' : '' ?></p>
                </div>
            </div>
        </div>
        <button type="button" class="reportBtn">
            <i class="fa-solid fa-circle-plus"></i>
            <span><?= $me['role'] === 'admin' ? 'Add New User' : 'Add New Cr' ?></span>
        </button>
    </section>
    <br>

    <?php if ($flash): ?>
        <p class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['text']) ?></p>
    <?php endif; ?>

    <section class="container">
        <form method="get" class="searchContainer">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by Reg Number"
                   class="inputText serchInput" minlength="3" maxlength="50">
            <button type="submit" class="searchBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($search !== ''): ?>
                <a href="admin.php" class="actionBtn">Clear</a>
            <?php endif; ?>
        </form>
    </section>

    <?php foreach ($members as $role => $list): ?>
        <section class="container">
            <h2><?= e($sectionTitles[$role]) ?></h2>
            <div class="allCrsContainer">
                <?php if (!$list): ?>
                    <h2 class="notFound"><?= $search !== '' ? 'No match.' : 'None registered yet.' ?></h2>
                <?php endif; ?>

                <?php foreach ($list as $m): ?>
                    <div class="crContainer">
                        <p><span>Reg Number: </span> <span class="regNumber"><?= e($m['reg_number']) ?></span></p>
                        <p><span>Name: </span> <span class="userInfo"><?= e($m['full_name']) ?></span></p>
                        <p><span>Programme: </span> <span class="userInfo"><?= e(programme($m['reg_number'])) ?></span></p>
                        <p><span>Level: </span> <span class="userInfo"><?= e(ucfirst((string)$m['level'])) ?>, stream <?= e($m['stream']) ?></span></p>
                        <hr>
                        <div class="actionBtnContainer">
                            <a href="edit-user.php?id=<?= e(urlencode($m['id'])) ?>" class="actionBtn edit">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>
                            <form method="post" onsubmit="return confirm(<?= e(json_encode('Delete ' . $m['full_name'] . '? Their reports and bookings will also be deleted.')) ?>);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                                <button type="submit" class="actionBtn delete"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- Registration form overlay -->
    <section class="bookFormContainer <?= $showForm ? '' : 'displayNone' ?>">
        <div class="close">
            <button type="button" class="closeBtn"><i class="fa-solid fa-x"></i></button>
        </div>
        <h3 id="regHead"><?= $me['role'] === 'admin' ? 'Register New User' : 'Register New Cr' ?></h3>

        <form method="post" class="formContainer" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">

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
                <input type="hidden" name="role" value="cr">
            <?php endif; ?>

            <div class="formInput">
                <label for="fullName">Full Name: </label>
                <input type="text" name="fullName" placeholder="Eg. Issa Semauya" id="fullName" class="inputText"
                       value="<?= e($old['fullName']) ?>" maxlength="100" required>
            </div>

            <div class="formInput">
                <label for="regNumber">Registration Number: </label>
                <input type="text" name="regNumber" placeholder="Eg. NIT/BIT/2024/2441" id="regNumber" class="inputText"
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

            <p><small>A password (first name + 6 random digits) is created when you register and shown once.</small></p>

            <div class="reportBtnContainer">
                <input type="submit" value="Register Now" class="reportBtn">
            </div>
        </form>
    </section>

    <footer>
        <p><small>&copy; <span id="currentYear"></span> all right reserved</small></p>
    </footer>
    <script src="assets/main.js"></script>
</body>
</html>
