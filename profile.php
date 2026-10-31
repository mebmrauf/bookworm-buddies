<?php
require_once 'auth.php';

$profileID = isset($_GET['userID']) ? (int) $_GET['userID'] : $userID;
$isOwn = $profileID === $userID;
// Visitors only ever see showcased books.
$show = 'showcased';
if ($isOwn) {
    $show = in_array($_GET['show'] ?? '', ['showcased', 'swapped'], true) ? $_GET['show'] : 'all';
}

$user = run("SELECT first_name, last_name, email, date_of_birth, username FROM users WHERE userID = ?", 'i', $profileID)->get_result()->fetch_assoc();

$books = [];
$feedback = [];
$swapped = [];

if ($user && $show === 'swapped') {
    $swapped = run("
        SELECT b.title, b.author, b.year_published, u.username, u.userID
        FROM swap s
        JOIN books b ON s.bookID = b.bookID
        JOIN users u ON s.ownerID = u.userID
        WHERE s.requesterID = ? AND s.status = 'accepted'
    ", 'i', $userID)->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user) {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, c.showcase
        FROM collection c JOIN books b ON c.bookID = b.bookID
        WHERE c.userID = ? AND (c.showcase = 1 OR ? = 'all')
        ORDER BY b.bookID DESC
    ", 'is', $profileID, $show)->get_result()->fetch_all(MYSQLI_ASSOC);

    $rows = run("
        SELECT f.feedbackID, f.bookID, f.comment, f.reply, f.userID, u.username
        FROM feedback f
        JOIN users u ON f.userID = u.userID
        JOIN collection c ON f.bookID = c.bookID
        WHERE c.userID = ?
        ORDER BY f.feedbackID
    ", 'i', $profileID)->get_result();
    while ($row = $rows->fetch_assoc()) {
        $feedback[$row['bookID']][] = $row;
    }
}

function action_button($action, $field, $value, $label, $confirm = null, $extra = '') {
    $onsubmit = $confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '';
    return '<form action="' . $action . '" method="post" class="inline-form"' . $onsubmit . '>' . csrf_field()
        . '<input type="hidden" name="' . $field . '" value="' . (int) $value . '">' . $extra
        . '<button type="submit" class="btn-primary">' . $label . '</button></form>';
}

$pageTitle = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'User not found';
include 'header.php';
?>
<div class="wholebody">
    <section class="userinfo">
        <h1>Profile Information:</h1>
        <?php if (!$user): ?>
            <p>User not found.</p>
        <?php else: ?>
            <p>Name: <b><?= e($user['first_name'] . ' ' . $user['last_name']) ?></b></p>
            <p>Username: <b><?= e($user['username']) ?></b></p>
            <?php if ($isOwn): ?>
                <p>Email: <b><?= e($user['email']) ?></b></p>
                <p>Date of Birth: <b><?= e($user['date_of_birth']) ?></b></p>
                <button onclick="window.location.href='updateprofile.php'" class="btn-primary">Update Profile</button>
                <form action="deleteaccount.php" method="post" class="inline-form" onsubmit="return confirm('Delete your account and all your books? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-primary btn-danger">Delete Account</button>
                </form>
                <form action="logout.php" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-primary">Logout</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($user): ?>
    <section class="collections">
        <h2>Book Collection:</h2>

        <?php if ($isOwn): ?>
            <div>
                <button onclick="window.location.href='?show=all'" class="btn-primary">All Books</button>
                <button onclick="window.location.href='?show=showcased'" class="btn-primary">Showcased Books</button>
                <button onclick="window.location.href='?show=swapped'" class="btn-primary">Swapped Books</button>
                <button onclick="window.location.href='registerbook.php'" class="btn-primary">Register New Book</button>
            </div>
        <?php endif; ?>

        <?php if ($show === 'swapped'): ?>
            <h2>Swapped Books:</h2>
            <?php if (!$swapped): ?>
                <p>No swapped books available.</p>
            <?php endif; ?>
            <ul>
                <?php foreach ($swapped as $book): ?>
                    <li>
                        <b>Title:</b> <?= e($book['title']) ?><br>
                        <b>Author:</b> <?= e($book['author']) ?><br>
                        <b>Published Year:</b> <?= e($book['year_published']) ?><br>
                        <b>Owner:</b> <a class="link" href="profile.php?userID=<?= $book['userID'] ?>"><?= e($book['username']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?php if (!$books): ?>
                <p>No books available.</p>
            <?php endif; ?>
            <ul>
                <?php foreach ($books as $book): ?>
                    <li>
                        <b>Title:</b> <?= e($book['title']) ?><br>
                        <b>Author:</b> <?= e($book['author']) ?><br>
                        <b>Published Year:</b> <?= e($book['year_published']) ?>
                        <br><br>

                        <?php if ($isOwn): ?>
                            <button onclick="window.location.href='editbookdetails.php?bookID=<?= $book['bookID'] ?>'" class="btn-primary">Edit</button>
                            <?= action_button('deletebook.php', 'bookID', $book['bookID'], 'Delete', 'Delete this book? This cannot be undone.') ?>
                            <?= action_button('showcase.php', 'bookID', $book['bookID'], $book['showcase'] ? 'Remove from Showcase' : 'Showcase', null, '<input type="hidden" name="showcase" value="' . ($book['showcase'] ? 0 : 1) . '">') ?>
                        <?php else: ?>
                            <form action="submitfeedback.php" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="bookID" value="<?= $book['bookID'] ?>">
                                <textarea name="comment" class="commentbox" maxlength="500" placeholder="Leave your feedback here" aria-label="Your feedback" required></textarea><br>
                                <button type="submit" class="btn-primary">Submit Feedback</button>
                            </form>
                        <?php endif; ?>

                        <h3>Feedback:</h3>
                        <?php if (empty($feedback[$book['bookID']])): ?>
                            <p>No feedback available for this book.</p>
                        <?php endif; ?>
                        <ul>
                            <?php foreach ($feedback[$book['bookID']] ?? [] as $item): ?>
                                <li>
                                    <b><a href="profile.php?userID=<?= $item['userID'] ?>" class="link"><?= e($item['username']) ?></a>:</b>
                                    <?= e($item['comment']) ?><br>

                                    <?php if ($item['userID'] == $userID): ?>
                                        <?= action_button('deletefeedback.php', 'feedbackID', $item['feedbackID'], 'Delete') ?>
                                    <?php endif; ?>

                                    <?php if ($isOwn): ?>
                                        <form action="replyfeedback.php" method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="feedbackID" value="<?= $item['feedbackID'] ?>">
                                            <textarea name="reply" class="commentbox" maxlength="500" placeholder="Reply to this feedback" aria-label="Your reply" required></textarea><br>
                                            <button type="submit" class="btn-primary">Submit Reply</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!empty($item['reply'])): ?>
                                        <div class="reply">
                                            <b>Reply from Book Owner:</b> <?= e($item['reply']) ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
