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
- Date search:

  - ``before:2016-08-30`` - modification date before that day
  - ``after:2016-08-30`` - modified after that day
  - ``date::2016-08-30`` - exact modification day match
- Site search

  - Query: ``foo bar site:example.org/dir/``
  - or use the ``site`` GET parameter:
    ``/?q=foo&site=example.org/dir``
- OpenSearch support with HTML and Atom result lists
- Instant indexing with WebSub (formerly PubSubHubbub)


============
Dependencies
============
- PHP 5.5+
- Elasticsearch 2.0
- MySQL or MariaDB for WebSub subscriptions
- Gearman (Debian 9: ``gearman-job-server``, not ``gearman-server``)
- PHP Gearman extension
- Console_CommandLine
- Net_URL2
- Twig 1.x


=====
Setup
=====
#. Install and run Elasticsearch and Gearman
#. Install ``php-gearman``
#. Get a local copy of the code::

     $ git clone https://git.cweiske.de/phinde.git phinde

#. Install dependencies via composer::

     $ composer install

#. Point your webserver's document root to phinde's ``www`` directory
#. Copy ``data/config.php.dist`` to ``data/config.php`` and adjust it.
   Make sure your add your domain to the crawl whitelist.
#. Create a MySQL database and import the schema from ``data/schema.sql``
#. Run ``bin/setup.php`` which sets up the Elasticsearch schema
#. Put your homepage into the queue::

     $ ./bin/process.php http://example.org/

#. Start at least one worker to process the crawl+index queue::

     $ ./bin/phinde-worker.php

#. Check phinde's status page in your browser.
   The number of open tasks should be > 0, the number of workers also.


Re-index when your site changes
===============================
When your site changed, the search engine needs to re-crawl and re-index
the pages.

Simply tell phinde that something changed by running::

    $ ./bin/process.php http://example.org/foo.htm

phinde supports HTML pages and Atom feeds, so if your blog has a feed
it's enough to let phinde reindex that one.
It will find all linked pages automatically.


Website integration
===================
Adding a simple search form to your website is easy.
It needs two things:

- ``<form>`` tag with an action that points to the phinde instance
- Search text field with name of ``q``.

Example::

  <form method="get" action="http://phinde.example.org">
    <input type="text" name="q" placeholder="Search text"/>
    <button type="submit">Search</button>
  </form>


System service
==============
When using systemd, you can let it run multiple worker instances when
the system boots up:

#. Copy files ``data/systemd/phinde*.service`` into ``/etc/systemd/system/``
#. Adjust user and group names, and the work directories
#. Enable three worker processes::

     $ systemctl daemon-reload
     $ systemctl enable phinde@1
     $ systemctl enable phinde@2
     $ systemctl enable phinde@3
     $ systemctl enable phinde
     $ systemctl start phinde
#. Now three workers are running. Restarting the ``phinde`` service also
   restarts the workers.



Cron job
========
Run ``bin/renew-subscriptions.php`` once a day with cron.
It will renew the WebSub subscriptions.


=====
Howto
=====

Delete index data from one domain::

    $ curl -iv -XDELETE -H 'Content-Type: application/json' -d '{"query":{"term":{"domain":"example.org"}}}' http://127.0.0.1:9200/phinde/_query

That's delete-by-query 2.0, see
https://www.elastic.co/guide/en/elasticsearch/plugins/2.0/delete-by-query-usage.html


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
