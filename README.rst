Features
========
- Crawler and indexer with the ability to run many in parallel
- Shows and highlights text that contains search words
- Boolean search queries:

  - ``foo bar`` searches for ``foo AND bar``
  - ``foo OR bar``
  - ``title:foo`` searches for ``foo`` only in the page title
- Facets for tag, domain, language and type
- Site search

  - Query: ``foo bar site:example.org/dir/``
  - or use the ``site`` GET parameter:
    ``/?q=foo&site=example.org/dir``

Dependencies
============
- PHP 5.5+
- elasticsearch 2.0
- gearman
- Net_URL2
