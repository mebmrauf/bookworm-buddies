CREATE TABLE users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender VARCHAR(30),
    mobile VARCHAR(20),
    address VARCHAR(255),
    city VARCHAR(40),
    area VARCHAR(60),
    rules_accepted_at DATETIME,
    suspended_at DATETIME,
    suspended_reason VARCHAR(255),
    avatar_public_id VARCHAR(255),
    email_verified_at DATETIME,
    pending_email VARCHAR(254),
    verify_token_hash CHAR(64) UNIQUE,
    verify_expires_at DATETIME,
    verify_sent_at DATETIME,
    INDEX (city)
);

CREATE TABLE books (
    bookID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    year_published INT NOT NULL,
    cover_public_id VARCHAR(255),
    cover_credit VARCHAR(100),
    handover ENUM('meet', 'courier', 'both') NOT NULL DEFAULT 'meet',
    listing_type ENUM('lend', 'give', 'swap') NOT NULL DEFAULT 'lend',
    removed_at DATETIME,
    removed_reason VARCHAR(255)
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
    status ENUM('pending', 'accepted', 'declined', 'returned', 'completed') NOT NULL DEFAULT 'pending',
    kind ENUM('lend', 'give', 'swap') NOT NULL DEFAULT 'lend',
    offered_bookID INT,
    owner_confirmed BOOLEAN NOT NULL DEFAULT 0,
    requester_confirmed BOOLEAN NOT NULL DEFAULT 0,
    due_date DATE,
    reminded_due_at DATETIME,
    reminded_overdue_at DATETIME,
    handover ENUM('meet', 'courier') NOT NULL DEFAULT 'meet',
    message VARCHAR(300),
    activeBookID INT GENERATED ALWAYS AS (IF(status IN ('pending', 'accepted'), bookID, NULL)) VIRTUAL,
    activeOfferedID INT GENERATED ALWAYS AS (IF(status IN ('pending', 'accepted'), offered_bookID, NULL)) VIRTUAL,
    UNIQUE (activeBookID),
    UNIQUE (activeOfferedID),
    FOREIGN KEY (offered_bookID) REFERENCES books(bookID) ON DELETE SET NULL,
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
    username VARCHAR(254) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (username, ip, attempted_at)
);

CREATE TABLE ratings (
    ratingID INT AUTO_INCREMENT PRIMARY KEY,
    swapID INT NOT NULL,
    raterID INT NOT NULL,
    ratedID INT NOT NULL,
    stars TINYINT NOT NULL,
    comment VARCHAR(300),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (swapID, raterID),
    INDEX (ratedID),
    CHECK (stars BETWEEN 1 AND 5),
    FOREIGN KEY (swapID) REFERENCES swap(swapID) ON DELETE CASCADE,
    FOREIGN KEY (raterID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (ratedID) REFERENCES users(userID) ON DELETE CASCADE
);

CREATE TABLE reports (
    reportID INT AUTO_INCREMENT PRIMARY KEY,
    reporterID INT NOT NULL,
    target_type ENUM('book', 'user', 'feedback', 'rating', 'message') NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(40) NOT NULL,
    details VARCHAR(500),
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    UNIQUE (reporterID, target_type, target_id),
    INDEX (status),
    FOREIGN KEY (reporterID) REFERENCES users(userID) ON DELETE CASCADE
);

CREATE TABLE admin_log (
    logID INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(40) NOT NULL,
    target_type VARCHAR(20),
    target_id INT,
    detail VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE messages (
    messageID INT AUTO_INCREMENT PRIMARY KEY,
    swapID INT NOT NULL,
    senderID INT NOT NULL,
    body VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    INDEX (swapID, messageID),
    INDEX (senderID, created_at),
    FOREIGN KEY (swapID) REFERENCES swap(swapID) ON DELETE CASCADE,
    FOREIGN KEY (senderID) REFERENCES users(userID) ON DELETE CASCADE
);
