<?php
require_once 'lib.php';

require_post('home.php');
session_unset();
session_destroy();
go('index.php');
