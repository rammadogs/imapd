<?php

require_once __DIR__.'/vendor/autoload.php';

use TheFox\Imap\Server;
use TheFox\Imap\Event;

// In production you use port 143. On most Unix-like systems you need to be
// root to open a port <1024. It's not recommended to run this script as root.
$server = new Server('127.0.0.1', 20143);
$server->init();
$server->listen();

$eventPreAddMail = new Event(Event::TRIGGER_MAIL_ADD_PRE, null, function($event){
	// Do stuff: handle mail, etc.
});
$server->eventAdd($eventPreAddMail);

$eventAddMail = new Event(Event::TRIGGER_MAIL_ADD, null, function($event){
	// Do stuff: handle mail, etc.
});
$server->eventAdd($eventAddMail);

$eventPostAddMail = new Event(Event::TRIGGER_MAIL_ADD_POST, null, function($event){
	// Do stuff: handle mail, etc.
});
$server->eventAdd($eventPostAddMail);

// `$server->loop()` is only a while-loop with `$server->run()` executed.
// If you also need to process other things in your application as well
// it's recommded to execute `$server->run()` from time to time.
// You need to execute `$server->run()` in your own project to keep the SMTP server updated.
// If you use your own loop to keep everything running consider executing `$server->run()` from time to time.
$server->loop();