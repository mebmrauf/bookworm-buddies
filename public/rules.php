<?php
require_once __DIR__ . '/../src/bootstrap.php';

$contact = config('contact_email');
$pageTitle = 'Community rules';
$pageDescription = 'How Bookworm Buddies works, what is not allowed, and how your information is used.';
include __DIR__ . '/../templates/top.php';
?>
<article class="prose">
    <h1>Community rules</h1>
    <p>Bookworm Buddies works because readers trust each other. Please follow these rules. If you break them, we may remove what you posted or suspend your account.</p>

    <h2>Be fair with books</h2>
    <ul>
        <li>Only list books you own and can really hand over.</li>
        <li>Return borrowed books by the agreed date, in the condition you got them. If you need more time, ask the owner.</li>
        <li>Keep your side of a swap or giveaway once you've accepted it.</li>
    </ul>

    <h2>Be respectful</h2>
    <ul>
        <li>No harassment, threats, hate speech or sexual content, in messages, comments, ratings or photos.</li>
        <li>No spam, fake accounts, fake ratings or selling.</li>
        <li>Only upload photos you're allowed to use.</li>
    </ul>

    <h2>Stay safe</h2>
    <ul>
        <li>Meet in a public place like a campus, library or café, and tell a friend where you're going.</li>
        <li>Agree on any courier cost before a book is sent. The person receiving it pays.</li>
        <li>Bookworm Buddies doesn't check members or books, and isn't responsible for lost or damaged books or for what happens at a handover.</li>
    </ul>

    <h2>Reports and suspensions</h2>
    <p>Use the Report link on any book, profile, comment or rating. An admin reviews reports, and can remove content or suspend an account. We'll email you the reason if that happens to you.</p>

    <h2>Your information</h2>
    <ul>
        <li><strong>Public:</strong> your name, username, city and area, shared books, comments and ratings.</li>
        <li><strong>Shared during an accepted swap only:</strong> your email and mobile number. Your delivery address goes only to a book's owner, and only for an accepted courier request.</li>
        <li><strong>Private:</strong> your date of birth and gender. Your password is stored only in scrambled form.</li>
        <li><strong>Services we use:</strong> Aiven stores the database, Cloudinary stores images, Resend sends email and Render runs the site.</li>
        <li><strong>Photos:</strong> we shrink them and remove hidden data like GPS location before storing them.</li>
        <li><strong>Deleting:</strong> you can delete your account in Settings, which removes your books and requests.</li>
    </ul>

    <h2>Book covers and contact</h2>
    <p>Book covers belong to their publishers and creators, and are shown only to identify each book.
        <?php if ($contact): ?>For a removal request, a suspension question or anything else, email <a class="link" href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.<?php endif; ?>
    </p>
</article>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
