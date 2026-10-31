<?php
require_once 'auth.php';

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
// % and _ are wildcards in LIKE, so a search for "%" must not match everything.
$pattern = '%' . addcslashes($q, '%_\\') . '%';

$books = $users = null;
if ($q !== '') {
    $books = run("
        SELECT b.title, b.author, b.year_published, u.userID, u.username
        FROM books b
        JOIN collection c ON b.bookID = c.bookID
        JOIN users u ON c.userID = u.userID
        WHERE (b.title LIKE ? OR b.author LIKE ?) AND (c.showcase = 1 OR c.userID = ?)
    ", 'ssi', $pattern, $pattern, $userID)->get_result();

    $users = run("
        SELECT userID, username, first_name, last_name
        FROM users
        WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
    ", 'sss', $pattern, $pattern, $pattern)->get_result();
}

$pageTitle = 'Search Results';
include 'header.php';
?>
<div class="wholebody">
    <?php if ($q === ''): ?>
        <h1>Please enter a search term.</h1>
    <?php else: ?>
        <h1>Search Results for "<?= e($q) ?>"</h1>

        <?php if ($books->num_rows > 0): ?>
            <div class="collections">
                <h2>Books</h2>
                <ul>
                    <?php while ($row = $books->fetch_assoc()): ?>
                        <li>
                            <strong><?= e($row['title']) ?></strong> by <?= e($row['author']) ?>
                            (Published: <?= e($row['year_published']) ?>)<br>
                            Owned by: <a class="link" href="profile.php?userID=<?= $row['userID'] ?>"><?= e($row['username']) ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($users->num_rows > 0): ?>
            <div class="collections">
                <h2>Users</h2>
                <ul>
                    <?php while ($row = $users->fetch_assoc()): ?>
                        <li>
                            <b>Name:</b> <?= e($row['first_name'] . ' ' . $row['last_name']) ?><br>
                            <b>Username:</b> <a class="link" href="profile.php?userID=<?= $row['userID'] ?>"><?= e($row['username']) ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($books->num_rows === 0 && $users->num_rows === 0): ?>
            <p>Nothing found.</p>
        <?php endif; ?>
    <?php endif; ?>
    <button onclick="window.location.href='home.php'" class="btn-primary">Back to Home</button>
</div>
<?php include 'footer.php'; ?>
