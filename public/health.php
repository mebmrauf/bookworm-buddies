<?php
// For the host's health check. Deliberately skips the database, so a brief Aiven hiccup doesn't restart the site.
echo 'ok';
