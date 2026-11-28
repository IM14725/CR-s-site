<?php
declare(strict_types=1);
require_once 'include/auth.php';
require_once 'include/venues.php';
$user = require_any_user();          // CRs, Ministry and Admin can all use this page

const REASONS = ['Class cancelled', 'Lecturer absent', 'Class rescheduled', 'Venue not in use'];
const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

$me = $user['id'];
$errors = [];
$old = ['venue' => '', 'startTime' => '', 'endTime' => '', 'reason' => REASONS[0]];
$showForm = false;

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ----- Report a free venue -----
    if ($action === 'report') {
        $old = [
            'venue'     => preg_replace('/\s+/', ' ', trim((string)($_POST['venue'] ?? ''))),
            'startTime' => (string)($_POST['startTime'] ?? ''),
            'endTime'   => (string)($_POST['endTime'] ?? ''),
            'reason'    => (string)($_POST['reason'] ?? ''),
        ];

        if (!is_official_venue($old['venue'])) {
            $errors[] = 'Choose a venue from the list. Start typing, e.g. "B 15" or "Auto Lab".';
        }
        $validTimes = preg_match(TIME_PATTERN, $old['startTime']) && preg_match(TIME_PATTERN, $old['endTime']);
        if (!$validTimes) {
            $errors[] = 'Enter a valid start and end time.';
        } elseif ($old['endTime'] <= $old['startTime']) {
            $errors[] = 'End time must be after start time.';
        } elseif ($old['endTime'] <= date('H:i')) {
            $errors[] = 'This time slot has already ended.';
        }
        if (!in_array($old['reason'], REASONS, true)) {
            $errors[] = 'Choose a valid reason.';
        }

        if (!$errors) {
            db()->prepare('INSERT INTO reports (cr_id, venue, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([$me, $old['venue'], $old['startTime'], $old['endTime'], $old['reason']]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => "{$old['venue']} reported as free."];
            header('Location: index.php');
            exit;
        }
        $showForm = true;
    }

    // ----- Book a venue -----
    if ($action === 'book') {
        $reportId = (string)($_POST['report_id'] ?? '');

        // Only today's reports that haven't expired can be booked.
        $stmt = db()->prepare('SELECT venue FROM reports
                               WHERE id = ? AND DATE(created_at) = CURDATE() AND end_time > CURTIME()');
        $stmt->execute([$reportId]);
        $venue = $stmt->fetchColumn();

        if ($venue === false) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'That venue is no longer available.'];
        } else {
            try {
                db()->prepare('INSERT INTO bookings (cr_id, report_id) VALUES (?, ?)')->execute([$me, $reportId]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => "You booked $venue."];
            } catch (PDOException $e) {
                // UNIQUE(report_id): someone else booked it first.
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
                $_SESSION['flash'] = ['type' => 'error', 'text' => "Sorry, $venue was just booked by another CR."];
            }
        }
        header('Location: index.php');
        exit;
    }

    // ----- Cancel my booking -----
    if ($action === 'cancel_booking') {
        $stmt = db()->prepare('DELETE FROM bookings WHERE report_id = ? AND cr_id = ?');
        $stmt->execute([(string)($_POST['report_id'] ?? ''), $me]);
        $_SESSION['flash'] = $stmt->rowCount() === 1
            ? ['type' => 'success', 'text' => 'Booking cancelled. The venue is available again.']
            : ['type' => 'error',   'text' => 'Booking not found.'];
        header('Location: index.php');
        exit;
    }

    // ----- Remove my own report -----
    if ($action === 'delete_report') {
        $stmt = db()->prepare('DELETE FROM reports WHERE id = ? AND cr_id = ?');
        $stmt->execute([(string)($_POST['report_id'] ?? ''), $me]);
        $_SESSION['flash'] = $stmt->rowCount() === 1
            ? ['type' => 'success', 'text' => 'Report removed.']
            : ['type' => 'error',   'text' => 'Report not found.'];
        header('Location: index.php');
        exit;
    }
}

// ---------- Read data for the page ----------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Today's venues that haven't expired, with who booked them (if anyone).
$venues = db()->query('
    SELECT r.id, r.cr_id, r.venue, r.start_time, r.end_time, r.reason,
           reporter.full_name AS reported_by,
           b.cr_id            AS booked_by_id,
           booker.full_name   AS booked_by,
           booker.role        AS booker_role,
           booker.level       AS booker_level,
           booker.stream      AS booker_stream
    FROM reports r
    JOIN users reporter      ON reporter.id = r.cr_id
    LEFT JOIN bookings b     ON b.report_id = r.id
    LEFT JOIN users booker   ON booker.id = b.cr_id
    WHERE DATE(r.created_at) = CURDATE() AND r.end_time > CURTIME()
    ORDER BY (b.id IS NOT NULL), r.start_time
')->fetchAll();

/** "Level 8, stream A" for CRs/Ministry, or "Admin" for accounts without a level. */
function member_info(?string $role, ?string $level, ?string $stream): string
{
    if ($level === null) {
        return ROLE_LABELS[$role] ?? '';
    }
    return ucfirst($level) . ', stream ' . $stream;
}

function show_time(string $time): string
{
    return date('g:i a', strtotime($time));   // "14:30:00" -> "2:30 pm"
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIT Venue Report</title>
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
            <?php if ($user['role'] !== 'cr'): ?>
                <a href="admin.php" class="actionBtn"><i class="fa-solid fa-user-gear"></i> <?= e(ROLE_LABELS[$user['role']]) ?> Panel</a>
            <?php endif; ?>
            <form method="post" action="logout.php" class="logoutForm">
                <?= csrf_field() ?>
                <button type="submit" class="actionBtn"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
            </form>
        </nav>
    </header>

    <section class="container">
        <div class="innerContainer">
            <div class="welcome">
                <h2>Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?>!</h2>
                <p>Publish &amp; Book available free venue.</p>
            </div>

            <div class="infContainer">
                <div class="info">
                    <p><?= e($user['reg_number'] ?? $user['username']) ?></p>
                    <p><?= e($user['full_name']) ?></p>
                </div>
                <div class="info">
                    <p><?= e(ROLE_LABELS[$user['role']]) ?></p>
                    <p><?= e(member_info($user['role'], $user['level'], $user['stream'])) ?></p>
                </div>
            </div>
        </div>
        <button type="button" class="reportBtn">
            <i class="fa-solid fa-circle-plus"></i>
            <span>Report Free Venue</span>
        </button>
    </section>

    <?php if ($flash): ?>
        <p class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['text']) ?></p>
    <?php endif; ?>

    <section class="container">
        <div class="upperContainer">
            <h2>Available Venue</h2>

            <?php if (!$venues): ?>
                <h2 class="notFound"><i class="fa-regular fa-face-sad-tear"></i></h2>
                <h2 class="notFound">No free venues reported right now.</h2>
            <?php endif; ?>

            <?php foreach ($venues as $v): ?>
                <?php $booked = $v['booked_by_id'] !== null; ?>
                <div class="venueContainer <?= $booked ? 'booked' : '' ?>" data-report-id="<?= e($v['id']) ?>">
                    <p><span>Venue: </span><span class="venue"><?= e($v['venue']) ?></span></p>
                    <p><span>Status: </span>
                        <span class="status">
                            <?php if (!$booked): ?>
                                Available
                            <?php elseif ($v['booked_by_id'] === $me): ?>
                                Booked by you
                            <?php else: ?>
                                Booked by <?= e($v['booked_by']) ?> (<?= e(member_info($v['booker_role'], $v['booker_level'], $v['booker_stream'])) ?>)
                            <?php endif; ?>
                        </span>
                    </p>
                    <p><small>Reason: <?= e($v['reason']) ?> · Reported by <?= e($v['cr_id'] === $me ? 'you' : $v['reported_by']) ?></small></p>

                    <div class="timeContainer">
                        <div class="time">
                            <p class="icon"><i class="fa-solid fa-hourglass-start"></i></p>
                            <div class="timeInfo">
                                <p><small>Free from:</small></p>
                                <p><small><?= e(show_time($v['start_time'])) ?></small></p>
                            </div>
                        </div>

                        <div class="time">
                            <p class="icon"><i class="fa-solid fa-hourglass-end"></i></p>
                            <div class="timeInfo">
                                <p><small>Until:</small></p>
                                <p><small><?= e(show_time($v['end_time'])) ?></small></p>
                            </div>
                        </div>

                        <?php if (!$booked): ?>
                            <button type="button" class="bookBtn bookNow"><i class="fa-solid fa-bookmark"></i> Book Now</button>
                        <?php elseif ($v['booked_by_id'] === $me): ?>
                            <form method="post" onsubmit="return confirm('Cancel your booking?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="report_id" value="<?= e($v['id']) ?>">
                                <button type="submit" class="bookBtn"><i class="fa-solid fa-xmark"></i> Cancel Booking</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="bookBtn" disabled><i class="fa-solid fa-lock"></i> Booked</button>
                        <?php endif; ?>
                    </div>

                    <?php if ($v['cr_id'] === $me): ?>
                        <form method="post" onsubmit="return confirm('Remove this report? Any booking on it will be removed too.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_report">
                            <input type="hidden" name="report_id" value="<?= e($v['id']) ?>">
                            <button type="submit" class="actionBtn delete"><i class="fa-solid fa-trash"></i> Remove my report</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Report form overlay -->
    <section class="bookFormContainer <?= $showForm ? '' : 'displayNone' ?>">
        <div class="close">
            <button type="button" class="closeBtn"><i class="fa-solid fa-x"></i></button>
        </div>
        <h3>Report Free Venue</h3>
        <form method="post" class="formContainer">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="report">

            <?php foreach ($errors as $error): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endforeach; ?>

            <div class="formInput venueSearch">
                <label for="venue">Venue Name: </label>
                <input type="text" name="venue" placeholder="Search e.g. B 15, LR 01, Auto Lab" id="venue" class="inputText"
                       value="<?= e($old['venue']) ?>" maxlength="50" autocomplete="off" required
                       role="combobox" aria-autocomplete="list" aria-controls="venueSuggestions" aria-expanded="false">
                <ul id="venueSuggestions" class="venueSuggestions" role="listbox" hidden></ul>
            </div>

            <div class="setTimeContainer">
                <div class="setTime">
                    <label for="startTime">Start Time: </label>
                    <input type="time" class="inputText" name="startTime" id="startTime" value="<?= e($old['startTime']) ?>" required>
                </div>
                <div class="setTime">
                    <label for="endTime">End Time: </label>
                    <input type="time" class="inputText" name="endTime" id="endTime" value="<?= e($old['endTime']) ?>" required>
                </div>
            </div>

            <div class="formInput">
                <label for="reason">Reason: </label>
                <select name="reason" id="reason" class="inputText">
                    <?php foreach (REASONS as $r): ?>
                        <option value="<?= e($r) ?>" <?= $old['reason'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="reportBtnContainer">
                <input type="submit" value="Report" class="reportBtn">
            </div>
        </form>
    </section>

    <!-- Booking confirmation overlay -->
    <section class="confirmContainer displayNone">
        <div class="close">
            <button type="button" class="closeBtn"><i class="fa-solid fa-x"></i></button>
        </div>
        <h3>Venue Confirmation</h3>
        <form method="post" class="formContainer">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="report_id" id="confirmReportId">
            <div class="formInput">
                <label for="confirm">Venue: </label>
                <input type="text" id="confirm" class="inputText" readonly>
            </div>
            <div class="confirmBtnContainer">
                <input type="button" value="Cancel" class="reportBtn cancel">
                <input type="submit" value="Confirm" class="reportBtn confirm">
            </div>
        </form>
    </section>

    <footer>
        <p><small>&copy; <span id="currentYear"></span> all right reserved</small></p>
    </footer>
    <script src="assets/main.js"></script>
</body>
</html>
