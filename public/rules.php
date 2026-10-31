<?php
require_once __DIR__ . '/../src/bootstrap.php';

const POLICIES_UPDATED = '31 October 2026';

$contact = config('contact_email');
$contactLink = $contact ? '<a class="link" href="mailto:' . e($contact) . '">' . e($contact) . '</a>' : 'the site owner';

$pageTitle = 'Rules and policies';
$pageDescription = 'The rules for using Bookworm Buddies, what can lead to a suspension, how your information is used, and how to contact us.';
include __DIR__ . '/../templates/top.php';
?>
<article class="prose">
    <h1>Community rules and policies</h1>
    <p class="muted">Last updated <?= POLICIES_UPDATED ?></p>
    <p>Bookworm Buddies is a free hobby project that helps readers lend, swap and give away physical books. By creating an account, you agree to everything on this page.</p>

    <nav aria-label="On this page">
        <ul class="prose__contents">
            <li><a class="link" href="#rules">Community rules</a></li>
            <li><a class="link" href="#enforcement">Suspensions and removed content</a></li>
            <li><a class="link" href="#exchanges">Exchanges and safety</a></li>
            <li><a class="link" href="#privacy">Your information</a></li>
            <li><a class="link" href="#copyright">Book covers and copyright</a></li>
            <li><a class="link" href="#contact">Changes and contact</a></li>
        </ul>
    </nav>

    <h2 id="rules">Community rules</h2>
    <h3>Be honest</h3>
    <ul>
        <li>Use your own email address, and only keep one account.</li>
        <li>Only list books you own and can really hand over, and describe them truthfully.</li>
        <li>Only give ratings for exchanges that really happened, and say what really happened.</li>
    </ul>
    <h3>Keep your word</h3>
    <ul>
        <li>Return borrowed books by the agreed date, in the condition you got them. If you need more time, ask the owner before the date passes.</li>
        <li>Once you've accepted a swap or giveaway, hand over your book as agreed.</li>
        <li>If your plans change, tell the other person as soon as you can.</li>
    </ul>
    <h3>Respect people</h3>
    <ul>
        <li>No harassment, threats, hate speech or sexual content, in chat, anywhere on the site, or when contacting someone you met here.</li>
        <li>Use chat to arrange your exchange, not for anything unrelated. If someone shares their details with you, keep them private and only use them for the exchange.</li>
    </ul>
    <h3>Keep it about books</h3>
    <ul>
        <li>No selling, advertising or spam. Bookworm Buddies is for sharing, not trade, though the receiver pays any courier cost.</li>
        <li>Only upload photos you're allowed to use, of the book being listed.</li>
        <li>Don't list anything illegal, including pirated or counterfeit copies.</li>
        <li>Don't try to break, overload or get around the site's protections, or access accounts or data that aren't yours.</li>
    </ul>

    <h2 id="enforcement">Suspensions and removed content</h2>
    <h3>How problems reach us</h3>
    <p>Signed-in members can use the Report link on any book, profile, comment or rating, and on messages sent to them in chat. A person reviews each report. Your report isn't shown to the member you reported. Please don't report people falsely or to get back at them, because that breaks these rules too.</p>

    <h3>What we can remove</h3>
    <p>For a smaller or first-time problem, we may only remove the content involved:</p>
    <ul>
        <li><strong>A cover photo:</strong> the book stays listed, and we email the owner the reason.</li>
        <li><strong>A book:</strong> it's hidden from everyone but its owner, who can't share it again unless we restore it. Any request still waiting on it is closed, and we email the owner the reason.</li>
        <li><strong>A comment, rating or reported chat message:</strong> it's deleted, and this may happen without notice.</li>
    </ul>

    <h3>What can get you suspended</h3>
    <p><strong>We may suspend an account straight away, without a warning, for:</strong></p>
    <ul>
        <li>Threats, harassment, hate speech or sexual content</li>
        <li>Fraud, including taking books with no intention of returning or exchanging them</li>
        <li>Abusing chat, or misusing details someone shared with you</li>
        <li>Listing illegal items</li>
        <li>Hacking, or attempting to get into other people's accounts or data</li>
        <li>Coming back with a new account while suspended</li>
    </ul>
    <p><strong>We may also suspend an account for repeated or serious problems such as:</strong></p>
    <ul>
        <li>Not returning borrowed books, or staying overdue without talking to the owner</li>
        <li>Backing out of accepted exchanges again and again</li>
        <li>Spam, selling or advertising</li>
        <li>Fake accounts, fake listings or fake ratings</li>
        <li>Uploading photos you aren't allowed to use after we've removed one</li>
        <li>Repeated false reports</li>
    </ul>
    <p>These lists are examples, not every possibility. We decide each case on what we can see, so a report alone doesn't mean a suspension, and there's no fixed number of strikes.</p>

    <h3>What a suspension does</h3>
    <ul>
        <li>You're signed out and can't sign in. We email you the reason.</li>
        <li>Your profile and books are hidden from other people.</li>
        <li>Requests still waiting for an answer, both yours and those on your books, are closed.</li>
        <li><strong>Exchanges already accepted stay in place.</strong> You're still expected to return or hand over any book you agreed to.</li>
    </ul>

    <h3>Asking us to look again</h3>
    <p>If you think we got it wrong, email <?= $contactLink ?> from your account's email address. We'll look at it again, but this is a hobby project, so a reply can take time. If we lift a suspension, your account and books come back as they were, apart from any requests that were closed.</p>

    <h2 id="exchanges">Exchanges and safety</h2>
    <ul>
        <li><strong>Loans:</strong> the book stays the owner's. The owner sets a return date, can change it, and marks the book as returned.</li>
        <li><strong>Giveaways:</strong> the book moves to the new reader's account once the owner marks it as given.</li>
        <li><strong>Two-way swaps:</strong> each book moves to its new owner once you've both confirmed the exchange.</li>
        <li><strong>Courier:</strong> the person receiving the book pays. Agree on the cost and the courier before anything is sent.</li>
    </ul>
    <h3>Chat</h3>
    <p>Accepting a request opens a private chat for the two of you to agree on the details. You can read it after the exchange ends, but no longer send messages. New messages can take a few seconds to appear. We may email you when you have an unread message. You don't have to share your phone number or address in chat, and it's safest not to until you're comfortable.</p>
    <h3>Stay safe</h3>
    <ul>
        <li>Meet in a busy public place like a campus, library or café, and tell someone where you're going.</li>
        <li>If you're under 18, get a parent or guardian's permission, and bring an adult to any meeting.</li>
        <li>Check a person's ratings, but remember that they only show what others have said.</li>
        <li>If you feel you're in danger, contact the police first, then report the account here.</li>
    </ul>
    <h3>What we can't do</h3>
    <p>Exchanges happen directly between members. We don't check members' identities or the condition of books, and we don't handle books, couriers or payments. We can't recover a lost or damaged book or settle a dispute, though we can act on the account involved. You take part at your own risk, and the site is provided as it is, without guarantees that it will always be available or free of errors.</p>

    <h2 id="privacy">Your information</h2>
    <h3>Who can see what</h3>
    <ul>
        <li><strong>Anyone, even without an account:</strong> your name, username, city, area, shared books, comments, and any ratings you've given or received. Your profile photo too, if you add one.</li>
        <li><strong>The owner of a book you request:</strong> your note, your chosen handover method and the books you offer.</li>
        <li><strong>The other person in a chat:</strong> what you write there, and anything you choose to share. We don't share your email address or mobile number with other members.</li>
        <li><strong>A book's owner, only for an accepted courier request:</strong> the delivery address of the person receiving it. It's never shared for meet-ups, and an owner's address is never shared.</li>
        <li><strong>Only you:</strong> your date of birth, gender and mobile number, on your own pages.</li>
        <li><strong>The admin:</strong> in the admin area, your name, username, email address, city and area, books, ratings, comments and reports, to run and moderate the site. The admin area doesn't let the admin browse chats, and only shows a chat message if someone in that chat reports it. Whoever runs the site can also technically reach everything in the database, including messages, but only uses it when needed to keep the site working or deal with a problem. Chats aren't end-to-end encrypted.</li>
    </ul>
    <p>Your password is stored only as a one-way scrambled hash, so nobody can read it.</p>

    <h3>What we use it for</h3>
    <ul>
        <li>Running your account and exchanges, and showing other members what's described above</li>
        <li>Sending you email about your account, requests, new messages, return dates and any moderation</li>
        <li>Keeping the site safe, including limiting failed sign-ins using your IP address</li>
    </ul>
    <p>We don't sell your information or show ads. We don't use tracking or analytics cookies. The only cookie is the one that keeps you signed in.</p>

    <h3>Services that handle it for us</h3>
    <ul>
        <li><strong>Render</strong> runs the website.</li>
        <li><strong>Aiven</strong> stores the database.</li>
        <li><strong>Cloudinary</strong> stores and delivers images.</li>
        <li><strong>Resend</strong> sends email.</li>
    </ul>
    <p>Some of these may store information outside Bangladesh. Photos you upload are shrunk first, and hidden data like GPS location is removed.</p>

    <h3>Your choices</h3>
    <ul>
        <li>You can change your details, or leave the optional ones blank, in Settings.</li>
        <li>You can delete your account in Settings. This removes your profile, your books and their photos, your requests and their chats, your comments, and ratings given by or about you. Books that already moved to someone else stay with them.</li>
        <li>Some information can remain for a while afterwards, such as in database backups, the admin action log and reports. A suspended account is kept so the suspension can be reviewed or enforced.</li>
    </ul>

    <h2 id="copyright">Book covers and copyright</h2>
    <p>Book covers belong to their publishers and creators, and are shown only to identify each book. Some come from <a class="link" href="https://openlibrary.org/" rel="noopener">Open Library</a>, and those are credited on the book's page. When you upload a photo, you confirm you're allowed to use it, and you let us store and show it on the site until you remove it.</p>
    <p>If you own the rights to something here and want it removed, email <?= $contactLink ?> with a link to the page and how you're connected to the work. We'll remove it promptly once we've confirmed the request.</p>

    <h2 id="contact">Changes and contact</h2>
    <p>We may update this page as the site changes, and the date at the top shows the latest version. If a change is significant, we'll try to let members know. Continuing to use the site after a change means you accept it.</p>
    <p>For questions, removal requests or anything about your information, contact <?= $contactLink ?>.</p>
</article>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
