mindplay/jsondoc
================

Simple, file-based object/document-database using JSON-files for persistence.

**STATUS**: complete and basically working; needs a proper unit-test.


Overview
--------

Object-graphs are serialized and stored as JSON-documents, in individual files -
the JSON representations (by default) are indented and formatted in a
human-readable and CVS-friendly way.

Object-keys map directly to folders and file-names - for example, an
object stored as 'foo/bar' is saved as '{database name}/foo/bar.json'.

Write and delete-operations are committed using basic transaction semantics,
with simple database-level locking to prevent simultaneous writes, using
early error-detection and automatic roll-back on failure.

Please see "Limitations" below for additional technical details.


API
---

The API consists of two classes:

DocumentStore represents the "connection" to a data-store root-folder,
containing a one or more databases.

DocumentSession represents a session with a data-store - it manages the
loading and saving of objects, and attempts to do so in a transactional
and safe manner, e.g. committing all save/delete operations atomically.


Usage
-----

Connect to a data-store by creating a DocumentStore and pointing it to
an already existing root-folder:

  $store = new DocumentStore($dbpath);

Connect to a database by opening a session, specifying a database-name:

  $session = $store->openSession('sampledb');

Now create objects of any class, and store them:

  $a = new Foo;
  $a->bar = 'Hello, World.';

  $session->store($a, 'foo/bar');

Load objects from the database into the current session:

  $b = $session->load('foo/baz');

Delete unwanted objects:

  $session->delete('foo/baz');

Then commit all pending store/delete-operations to the database:

  $session->commit();


Limitations
-----------

Using individual, flat files for data-storage is *not fast* - this
library (by design) is optimized for consistent storage, quick and
easy implementation, human-readablea and CSV-compatible file-based
storage, in applications where speed is not a critical factor.

The JsonSerializer itself has an important limitation: it is designed
to store self-contained object-graphs only - it does not support shared
or circular object-references. This is by design, and in-tune with good
DDD design practices: an object-graph with shared or circular references
cannot be stored in a predictable format.

This library does not synthesize object keys - which means you must
assign a key when you store a new object. Again, this is by design:

More detailed background on limitations and technical decisions here:

  http://stackoverflow.com/questions/10489876
