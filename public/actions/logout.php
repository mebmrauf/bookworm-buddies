<?php
require_once __DIR__ . '/../../src/bootstrap.php';

require_post('/home.php');
session_unset();
session_destroy();
go('/');
