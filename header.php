<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/design.css">
    <link rel="stylesheet" href="./css/signin.css">
    <title><?= e($pageTitle) ?> - Bookworm Buddies</title>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><a href="home.php">BOOKWORM BUDDIES</a></div>
        <div id="search-box">
            <form id="search-form" action="search.php" method="GET">
                <input type="text" id="search-bar" name="q" placeholder="Search..." aria-label="Search" required>
                <button type="submit" id="search-btn" aria-label="Search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><circle cx="10" cy="10" r="7"/><line x1="15" y1="15" x2="22" y2="22"/></svg>
                </button>
            </form>
        </div>
        <ul class="nav-links">
            <li><a href="./home.php">Home</a></li>
            <li><a href="./availablebooks.php">Available Books</a></li>
            <li><a href="./registerbook.php">Add Book</a></li>
            <li><a href="./requests.php">Requests</a></li>
            <li><a href="./profile.php">My profile</a></li>
        </ul>
    </nav>
    <?php show_flash(); ?>
