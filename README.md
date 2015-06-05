mindplay/jsondoc
================

Simple, file-based object/document-database using JSON-files for persistence.

[![Build Status](https://travis-ci.org/mindplay-dk/jsondoc.svg?branch=master)](https://travis-ci.org/mindplay-dk/jsondoc)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/jsondoc/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/jsondoc/?branch=master)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/jsondoc/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/jsondoc/?branch=master)


Overview
--------

Object-graphs are serialized and stored as JSON-documents, in individual files -
the JSON representations are (optionally, by default) indented and formatted in a
human-readable and CVS-friendly way.

Object-keys map directly to folders and file-names - for example, an object
stored as `foo/bar` is saved as `{database name}/foo/bar.json`.

Write and delete-operations are committed using basic transaction semantics,
with simple database-level locking to prevent simultaneous writes, using
early error-detection and automatic roll-back on failure.

Please see "Limitations" below for additional technical details.


API
---

The API consists of two classes:

`DocumentStore` represents the "connection" to a data-store: a root-folder
containing one or more databases.

`DocumentSession` represents a session with one specific database inside a
data-store - it manages the loading and saving of objects, and attempts
to do so in a transactional and safe manner, e.g. committing all
save/delete operations atomically.


Usage
-----

Create a DocumentStore with FilePersistence and point it to an existing folder:

```PHP
$store = new DocumentStore(new FilePersistence($db_path));
```

Ask the DocumentStore to create a DocumentSession:

```PHP
$session = $store->openSession();
```

This will lock the store in shared mode, until you `close()` the session. (it will
also automatically close if it falls out of scope.)

Now create objects of any class, and store them:

```PHP
$a = new Foo;
$a->bar = 'Hello, World.';

$session->store($a, 'foo/bar');
```

Note that the state of the object has been captured in-memory, but the serialized
object does not get written to underlying storage until changes are committed.

Alternatively, you can store an object with a generated UUID under a parent ID:

```PHP
$a = new Foo;
$a->bar = 'Hello again!';

$id = $session->append($a, 'foo', $uuid);

var_dump($uuid); // "029d97a2-7676-45b1-9d49-353bec0d71c0"
var_dump($id);   // "foo/029d97a2-7676-45b1-9d49-353bec0d71c0"
```

Load objects from the database into the current session:

```PHP
$b = $session->load('foo/baz');
```

Delete unwanted objects:

```PHP
$session->delete('foo/baz');
```

Call `commit()` to persist all the pending store/delete-operations:

```PHP
$session->commit();
```

Finally, you should `close()` to explicitly release the lock:

```PHP
$session->close();
```

The `DocumentSession` API also provides a few other operations:

 * `exists($id)` indicates whether a document with the given ID exists in the store.

 * `contains($id)` indicates whether the session contains a document with a given ID.

 * `getId($object)` provides the document ID of an object in the session.

 * `evict($id)` evicts the object/document with the given ID from the session.

 * `flush()` evicts all objects/documents and pending operations from the session.


Limitations
-----------

Using individual, flat files for data-storage is *not fast* - this
library (by design) is optimized for consistent storage, quick and
easy implementation, human-readable and VCS-compatible file-based
storage, in applications where speed is not a critical factor.

The JsonSerializer itself has an important limitation: it is designed
to store self-contained object-graphs only - it does not support shared
or circular object-references. This is by design, and in-tune with good
DDD design practices: an object-graph with shared or circular references
does not have clear transaction boundaries and cannot be stored in a
predictable and consistent way.

This library does not synthesize object keys - which means you must
assign a key when you store a new object. Again, this is by design.

More detailed background on limitations and technical decisions
[here](http://stackoverflow.com/questions/10489876).
