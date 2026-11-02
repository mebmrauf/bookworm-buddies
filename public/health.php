<?php
// For the host's health check. Deliberately skips the database, so a brief database hiccup doesn't restart the site.
echo 'ok';
