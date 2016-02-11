**********************************
phinde - generic web search engine
**********************************
Self-hosted search engine you can use for your static blog or about
any other website you want search functionality for.

My live instance is at http://search.cweiske.de/ and indexes my
website, blog and all linked URLs.


========
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
- OpenSearch support with HTML and Atom result lists


============
Dependencies
============
- PHP 5.5+
- elasticsearch 2.0
- gearman
- Console_CommandLine
- Net_URL2


============
About phinde
============

Source code
===========
phinde's source code is available from http://git.cweiske.de/phinde.git
or the `mirror on github`__.

__ https://github.com/cweiske/phinde


License
=======
phinde is licensed under the `AGPL v3 or later`__.

__ http://www.gnu.org/licenses/agpl.html


Author
======
phinde was written by `Christian Weiske`__.

__ http://cweiske.de/
