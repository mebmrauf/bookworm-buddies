<?php
// Fills an empty database with demo content: php tools/seed.php
// People are fictional. Emails use example.com, a domain reserved so mail never reaches anyone.
// Random passwords are written to demo-accounts.php, which git ignores.
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../dbconnect.php';

if ($conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0] > 0) {
    exit("The database already has users. Nothing was changed.\n");
}

// username => [first name, last name, birth date, [[title, author, year, showcased], ...]]
$people = [
    'farhana.akter' => ['Farhana', 'Akter', '1998-03-14', [
        ['The Great Gatsby', 'F. Scott Fitzgerald', 1925, 1],
        ['Pride and Prejudice', 'Jane Austen', 1813, 1],
        ['Jane Eyre', 'Charlotte Bronte', 1847, 1],
        ['Little Women', 'Louisa May Alcott', 1868, 0],
    ]],
    'tanvir_hossain' => ['Tanvir', 'Hossain', '1996-07-22', [
        ['Dune', 'Frank Herbert', 1965, 1],
        ['Foundation', 'Isaac Asimov', 1951, 1],
        ['Neuromancer', 'William Gibson', 1984, 1],
        ['The Martian', 'Andy Weir', 2011, 0],
    ]],
    'sadia.islam' => ['Sadia', 'Islam', '2000-11-05', [
        ['The Kite Runner', 'Khaled Hosseini', 2003, 1],
        ['A Thousand Splendid Suns', 'Khaled Hosseini', 2007, 1],
        ['The God of Small Things', 'Arundhati Roy', 1997, 1],
    ]],
    'arif_chowdhury' => ['Arif', 'Chowdhury', '1993-01-30', [
        ['Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 2011, 1],
        ['Thinking, Fast and Slow', 'Daniel Kahneman', 2011, 1],
        ['Guns, Germs, and Steel', 'Jared Diamond', 1997, 1],
        ['The Selfish Gene', 'Richard Dawkins', 1976, 0],
    ]],
    'maliha.karim' => ['Maliha', 'Karim', '1999-05-18', [
        ['Harry Potter and the Philosopher\'s Stone', 'J.K. Rowling', 1997, 1],
        ['The Hobbit', 'J.R.R. Tolkien', 1937, 1],
        ['The Lion, the Witch and the Wardrobe', 'C.S. Lewis', 1950, 1],
        ['Charlotte\'s Web', 'E.B. White', 1952, 0],
    ]],
    'imran_sarker' => ['Imran', 'Sarker', '1995-09-09', [
        ['And Then There Were None', 'Agatha Christie', 1939, 1],
        ['The Hound of the Baskervilles', 'Arthur Conan Doyle', 1902, 1],
        ['Gone Girl', 'Gillian Flynn', 2012, 1],
    ]],
    'priya.das' => ['Priya', 'Das', '1997-12-02', [
        ['Gitanjali', 'Rabindranath Tagore', 1910, 1],
        ['Pather Panchali', 'Bibhutibhushan Bandyopadhyay', 1929, 1],
        ['The Namesake', 'Jhumpa Lahiri', 2003, 1],
        ['Interpreter of Maladies', 'Jhumpa Lahiri', 1999, 0],
    ]],
    'rakib_mahmud' => ['Rakib', 'Mahmud', '1994-04-27', [
        ['1984', 'George Orwell', 1949, 1],
        ['Animal Farm', 'George Orwell', 1945, 1],
        ['Brave New World', 'Aldous Huxley', 1932, 1],
    ]],
    'anika.sultana' => ['Anika', 'Sultana', '2001-08-16', [
        ['To Kill a Mockingbird', 'Harper Lee', 1960, 1],
        ['The Catcher in the Rye', 'J.D. Salinger', 1951, 1],
        ['The Book Thief', 'Markus Zusak', 2005, 1],
        ['The Alchemist', 'Paulo Coelho', 1988, 0],
    ]],
    'zubair_ahmed' => ['Zubair', 'Ahmed', '1992-02-11', [
        ['Crime and Punishment', 'Fyodor Dostoevsky', 1866, 1],
        ['War and Peace', 'Leo Tolstoy', 1869, 1],
        ['One Hundred Years of Solitude', 'Gabriel Garcia Marquez', 1967, 1],
    ]],
    'emily.carter' => ['Emily', 'Carter', '1998-10-25', [
        ['Educated', 'Tara Westover', 2018, 1],
        ['Becoming', 'Michelle Obama', 2018, 1],
        ['The Diary of a Young Girl', 'Anne Frank', 1947, 0],
    ]],
    'daniel_brooks' => ['Daniel', 'Brooks', '1991-06-03', [
        ['The Pragmatic Programmer', 'Andrew Hunt and David Thomas', 1999, 1],
        ['Clean Code', 'Robert C. Martin', 2008, 1],
        ['Atomic Habits', 'James Clear', 2018, 1],
    ]],
];

// [book title, requester, status]
$swaps = [
    ['Dune', 'imran_sarker', 'accepted'],
    ['The Kite Runner', 'farhana.akter', 'accepted'],
    ['Thinking, Fast and Slow', 'daniel_brooks', 'accepted'],
    ['The Hobbit', 'anika.sultana', 'pending'],
    ['1984', 'sadia.islam', 'pending'],
    ['Atomic Habits', 'arif_chowdhury', 'pending'],
    ['Gitanjali', 'emily.carter', 'pending'],
    ['Gone Girl', 'priya.das', 'declined'],
    ['The Book Thief', 'zubair_ahmed', 'declined'],
    ['Pride and Prejudice', 'maliha.karim', 'returned'],
    ['Crime and Punishment', 'rakib_mahmud', 'returned'],
    ['Educated', 'tanvir_hossain', 'returned'],
];

// [book title, commenter, comment, owner's reply or null]
$feedback = [
    ['The Great Gatsby', 'rakib_mahmud', 'Short, sad and beautifully written. Worth a second read.', 'Agreed, I notice something new every time.'],
    ['Pride and Prejudice', 'maliha.karim', 'Borrowed this one and the copy was in great shape. Thank you!', 'Glad you enjoyed it!'],
    ['Dune', 'imran_sarker', 'The first hundred pages are slow, but then I could not put it down.', null],
    ['Foundation', 'daniel_brooks', 'Big ideas, and it has aged better than I expected.', 'The sequels are good too if you want them.'],
    ['The Kite Runner', 'priya.das', 'I cried twice. Don\'t read the ending in public.', 'Same here, it stays with you.'],
    ['Sapiens: A Brief History of Humankind', 'tanvir_hossain', 'It changed how I think about money and religion.', null],
    ['Harry Potter and the Philosopher\'s Stone', 'anika.sultana', 'A lovely edition, and still as magical as when I was ten.', 'It\'s my favorite copy.'],
    ['The Hobbit', 'zubair_ahmed', 'A perfect book for a rainy weekend.', null],
    ['And Then There Were None', 'emily.carter', 'I guessed wrong right until the last page.', 'Everyone does!'],
    ['Gitanjali', 'sadia.islam', 'Best read slowly, a few poems at a time.', null],
    ['1984', 'arif_chowdhury', 'Still uncomfortably relevant.', 'That\'s what makes it great.'],
    ['Crime and Punishment', 'rakib_mahmud', 'Heavy, but the best novel I read this year. Thanks for lending it.', 'Any time, happy to share.'],
    ['Educated', 'tanvir_hossain', 'Hard to believe this is a true story.', null],
    ['Clean Code', 'farhana.akter', 'Some advice is dated, but it made me a tidier programmer.', null],
];

$accounts = [];
$books = [];
$userIDs = [];

$conn->begin_transaction();

$addUser = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, date_of_birth) VALUES (?, ?, ?, ?, ?, ?)");
$addBook = $conn->prepare("INSERT INTO books (title, author, year_published) VALUES (?, ?, ?)");
$addCopy = $conn->prepare("INSERT INTO collection (userID, bookID, showcase) VALUES (?, ?, ?)");
$addSwap = $conn->prepare("INSERT INTO swap (requesterID, ownerID, bookID, status) VALUES (?, ?, ?, ?)");
$addFeedback = $conn->prepare("INSERT INTO feedback (userID, bookID, comment, reply) VALUES (?, ?, ?, ?)");

foreach ($people as $username => [$first, $last, $dob, $list]) {
    $password = bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $email = str_replace('_', '.', $username) . '@example.com';
    $addUser->bind_param('ssssss', $username, $hash, $email, $first, $last, $dob);
    $addUser->execute();
    $userIDs[$username] = $conn->insert_id;
    $accounts[] = "$username  $password  ($first $last)";

    foreach ($list as [$title, $author, $year, $showcase]) {
        $addBook->bind_param('ssi', $title, $author, $year);
        $addBook->execute();
        $bookID = $conn->insert_id;
        $addCopy->bind_param('iii', $userIDs[$username], $bookID, $showcase);
        $addCopy->execute();
        $books[$title] = ['id' => $bookID, 'owner' => $username, 'showcase' => $showcase];
    }
}

// Keep the demo consistent with what the site itself allows.
function book_for($title, $person) {
    global $books;
    $book = $books[$title] ?? null;
    if (!$book || !$book['showcase'] || $book['owner'] === $person) {
        throw new RuntimeException("Bad demo data: $title / $person");
    }
    return $book;
}

foreach ($swaps as [$title, $requester, $status]) {
    $book = book_for($title, $requester);
    $addSwap->bind_param('iiis', $userIDs[$requester], $userIDs[$book['owner']], $book['id'], $status);
    $addSwap->execute();
}

foreach ($feedback as [$title, $person, $comment, $reply]) {
    $book = book_for($title, $person);
    $addFeedback->bind_param('iiss', $userIDs[$person], $book['id'], $comment, $reply);
    $addFeedback->execute();
}

$conn->commit();

// A .php file that exits at once, so the passwords can't be downloaded from the website.
file_put_contents(__DIR__ . '/../demo-accounts.php', "<?php exit; ?>\nusername  password  (name)\n" . implode("\n", $accounts) . "\n");

printf("Added %d users, %d books, %d swaps, %d feedback entries.\n", count($people), count($books), count($swaps), count($feedback));
