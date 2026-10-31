<?php
// The tests run on their own database, so nothing they create can ever appear on the real site.
// Email is off too, unless a run asks for --real-services.
return ['database' => 'bookworm_test']
    + (getenv('BOOKWORM_REAL_SERVICES') ? [] : ['resend' => []])
    + require __DIR__ . '/../config.php';
