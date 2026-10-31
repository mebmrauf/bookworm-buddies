    </main>
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Bookworm Buddies &middot; <a class="link" href="/rules.php">Community rules</a></p>
        <p class="footer__note">Book covers belong to their publishers and creators, and are shown only to identify each book.
            <?php if (config('contact_email')): ?>To request removal, email <a class="link" href="mailto:<?= e(config('contact_email')) ?>"><?= e(config('contact_email')) ?></a>.<?php endif; ?>
        </p>
    </footer>
</body>
</html>
