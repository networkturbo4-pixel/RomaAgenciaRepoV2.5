<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'get_channel_messages';
$_POST['channel_id'] = 1;

// Mock the session inside the actual script context or just run it via curl if we can.
// Better to just grep the PHP error log if there is one.
