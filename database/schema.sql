CREATE TABLE users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    avatar_public_id VARCHAR(255)
);

CREATE TABLE books (
    bookID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    year_published INT NOT NULL,
    cover_public_id VARCHAR(255)
);

-- A book has exactly one owner; two people with the same title are two book rows.
CREATE TABLE collection (
    collectionID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    bookID INT NOT NULL UNIQUE,
    showcase BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (bookID) REFERENCES books(bookID) ON DELETE CASCADE
);

-- activeBookID makes "one open swap per book" a database rule, so two requests at once can't both win.
CREATE TABLE swap (
    swapID INT AUTO_INCREMENT PRIMARY KEY,
    requesterID INT NOT NULL,
    ownerID INT NOT NULL,
    bookID INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'returned') NOT NULL DEFAULT 'pending',
    activeBookID INT GENERATED ALWAYS AS (IF(status IN ('pending', 'accepted'), bookID, NULL)) VIRTUAL,
    UNIQUE (activeBookID),
    FOREIGN KEY (requesterID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (ownerID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (bookID) REFERENCES books(bookID) ON DELETE CASCADE
);

CREATE TABLE feedback (
    feedbackID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    bookID INT NOT NULL,
    comment VARCHAR(500) NOT NULL,
    reply VARCHAR(500),
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (bookID) REFERENCES books(bookID) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
    attemptID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (username, ip, attempted_at)
);
