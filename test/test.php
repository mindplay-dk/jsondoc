<?php

# This will eventually be a unit-test...

namespace mindplay\jsondoc\test;

header('Content-type: text/plain');

require '../vendor/mindplay/jsonfreeze/mindplay/jsonfreeze/JsonSerializer.php';
require '../mindplay/jsondoc/DocumentException.php';
require '../mindplay/jsondoc/DocumentSession.php';
require '../mindplay/jsondoc/DocumentStore.php';

use mindplay\jsondoc\DocumentStore;

# Sample class:

class Foo
{
  public $bar;
}

# Test:

echo "Executing tests...\n";

$dbpath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'samplestore';

foreach (glob($dbpath . DIRECTORY_SEPARATOR . '*.json') as $path) {
  unlink($path);
  echo "- Clean previous database file: {$path}\n";
}

$store = new DocumentStore($dbpath);

$session = $store->openSession('sampledb');

$a = new Foo;
$a->bar = 'one';

$b = new Foo;
$b->bar = 'two';

$session->store($a, 'foo/a');
$session->store($b, 'foo/b');

if (!$session->contains('foo/a')) {
  die("*** ERROR: first object was incorrectly stored in session");
}

if ($session->load('foo/a') !== $a) {
  die("*** ERROR: can't get first stored object");
}

if ($session->load('foo/b') !== $b) {
  die("*** ERROR: can't get second stored object");
}

if ($session->getId($a) !== 'foo/a') {
  die("*** ERROR: can't get ID of first stored object");
}

$session->commit();

if ($session->load('foo/a') !== $a) {
  die("*** ERROR: can't get first stored object after saving");
}

if ($session->load('foo/b') !== $b) {
  die("*** ERROR: can't get second stored object after saving");
}

if ($session->getId($b) !== 'foo/b') {
  die("*** ERROR: can't get ID of second stored object");
}

$session->close();

unset($a);
unset($b);

if (!file_exists($dbpath.'/sampledb/foo/a.json')) {
  die("*** ERROR: file foo/a.json was not found");
}

if (!file_exists($dbpath.'/sampledb/foo/b.json')) {
  die("*** ERROR: file foo/a.json was not found");
}

$session = $store->openSession('sampledb');

$a = $session->load('foo/a');
$b = $session->load('foo/b');

if ($a->bar !== 'one') {
  die("*** ERROR: object foo/a was not loaded correctly");
}

if ($b->bar !== 'two') {
  die("*** ERROR: object foo/b was not loaded correctly");
}

$session->delete('foo/a');

if (!$session->contains('foo/a')) {
  die("*** ERROR: object foo/a should remain in session until changes are committed");
}

$session->commit();

if ($session->contains('foo/a')) {
  die("*** ERROR: object foo/a was not automatically evicted after commit");
}

$session->evict('foo/b');

if ($session->contains('foo/b')) {
  die("*** ERROR: object foo/b was not correctly evicted from session");
}

$session->commit();

if (file_exists($dbpath.'/sampledb/foo/a.json')) {
  die("*** ERROR: file foo/a.json was not correctly deleted");
}

if (!file_exists($dbpath.'/sampledb/foo/b.json')) {
  die("*** ERROR: file foo/a.json was accidentally deleted");
}

echo "Tests completed.";
