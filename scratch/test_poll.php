<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'poll_updates';
$_POST['channel_id'] = 1;
$_POST['last_id'] = 0;
session_id('test1234');
// do not session_start here, let ajax.php do it
// but we need $_SESSION populated? We can't if we don't start it.
// Let's create a wrapper that captures output
