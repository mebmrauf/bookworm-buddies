    </main>
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Bookworm Buddies &middot; <a class="link" href="/about.php">About</a> &middot; <a class="link" href="/rules.php">Rules and policies</a></p>
    </footer>
    <dialog class="post-dialog" data-post-dialog aria-label="Blog post">
        <button type="button" class="post-dialog__close" data-post-dialog-close aria-label="Close"><?= icon('close') ?></button>
        <div data-post-dialog-body></div>
    </dialog>
    <aside class="install-sheet" data-install-sheet aria-label="Install the app" hidden>
        <div>
            <h2>Install Bookworm Buddies</h2>
            <p data-install-text>Open it from your home screen, like any other app.</p>
        </div>
        <div class="install-sheet__actions">
            <button type="button" class="btn btn--primary" data-install-accept>Install</button>
            <button type="button" class="btn btn--ghost" data-install-dismiss>Not now</button>
        </div>
    </aside>
</body>
</html>
