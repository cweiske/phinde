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
* Instant indexing with WebSub (formerly PubSubHubbub)


============
Dependencies
============
- PHP 5.5+
- elasticsearch 2.0
- gearman
- Console_CommandLine
- Net_URL2


=====
Setup
=====
FIXME: This section is incomplete.


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
