<?php
require_once __DIR__ . '/vendor/autoload.php';

$blitz = new \Plitz\Bindings\Blitz\Blitz(__DIR__ . '/t2.html');
$blitz->display([
    'user' => [
        'name' => 'Maurus',
        'friends' => [
            ['name' => 'Wouter', 'age' => 21, 'isOnline' => false],
            ['name' => 'Thibaut', 'age' => 26, 'isOnline' => true],
        ]
    ]
]);
