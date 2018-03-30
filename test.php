<?php
require_once "vendor/autoload.php";

use Opis\Pattern\RegexBuilder;

$b = new RegexBuilder();
$o = new \Opis\Pattern\Builder();
echo $r = $b->getRegex('/{a}/{b}/', ['a' => '[a-z]+', 'b' => '[0-9]+']), PHP_EOL;
echo $o->getRegex('/{a}/{b}', ['a' => '[a-z]+', 'b' => '[0-9]+']), PHP_EOL;
# /{a}{}.html


print_r($b->getValues($r, '/a/1/'));