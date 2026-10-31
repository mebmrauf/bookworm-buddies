<?php
require_once 'auth.php';

// availablebooks.php reuses this page with $onlyAvailable = true.
$onlyAvailable = $onlyAvailable ?? false;
$pageTitle = $onlyAvailable ? 'Available Books' : 'Home';

$books = run("
    SELECT b.bookID, b.title, b.author, b.year_published, u.username, u.userID,
        EXISTS (SELECT 1 FROM swap s WHERE s.bookID = b.bookID AND s.status IN ('pending', 'accepted')) AS unavailable
    FROM collection c
    JOIN books b ON c.bookID = b.bookID
    JOIN users u ON c.userID = u.userID
    WHERE c.showcase = 1 AND u.userID != ?
    " . ($onlyAvailable ? "HAVING unavailable = 0" : "") . "
    ORDER BY b.bookID DESC
", 'i', $userID)->get_result();

include 'header.php';
?>
<div class="wholebody">
    <h1><?= $onlyAvailable ? 'Available Books From All Users' : 'Books From All Users' ?></h1>
    <div class="book-list">
        <?php while ($row = $books->fetch_assoc()): ?>
            <div class="book-box">
                <h2><?= e($row['title']) ?></h2>
                <p><b>Author:</b> <?= e($row['author']) ?></p>
                <p><b>Published Year:</b> <?= e($row['year_published']) ?></p>
                <p><b>Owner:</b> <a class="link" href="profile.php?userID=<?= $row['userID'] ?>"><?= e($row['username']) ?></a></p>
                <?php if ($row['unavailable']): ?>
                    <button class="btn-primary" disabled>Not Available</button>
                <?php else: ?>
                    <form action="requestbook.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="bookID" value="<?= $row['bookID'] ?>">
                        <button type="submit" class="btn-primary">Request For Swap</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php if ($books->num_rows === 0): ?>
            <p>No books found.</p>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
