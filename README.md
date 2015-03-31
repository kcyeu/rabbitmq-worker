![RabbitMQ-Worker](http://i.imgur.com/VblCBhb.png)
RabbitMQ-Worker
===============

> RabbitMQ-Worker as daemon written in PHP.

RabbitMQ-Worker runs as a daemon. It consumes and digests messages from RabbitMQ then save result to RedisCluster, RedLock algorithm was adopted for distributed lock.

Table of Contents
-----------------

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Getting Started](#getting-started)
- [Contributing](#contributing)
- [License](#license)

Features
--------

- Redis as data storage
- Shared memory lock which prevents duplicate instance
- Support signal
- Configurable worker count

Prerequisites
-------------

- [PHP-Daemon](kcyeu/PHP-Daemon)
- [PHP-Redlock](kcyeu/php-redlock)
- [php-amqplib](videlalvaro/php-amqplib)
- (Optional) [Composer](https://getcomposer.org/download/)

Getting Started
---------------
The easiest way to get started is to clone the repository:

```bash
git clone git@github.com:kcyeu/rabbitmq-worker.git
```

Then use composer to pull all dependencies:

```bash
composer install
```

That's it!

Usage
---------------
Remember to set credentials in ```src/config.ini```, then add ```-d``` argument to run it in background:

```bash
php src/run.php [-d]
```

Changelog
---------
### 1.0.0 (Mar 30, 2015)
- Debut

Contributing
------------

Pull requests are always welcome. Please open an issue before submitting a pull request.

License
-------

[LGPL-3.0+](https://www.gnu.org/licenses/lgpl.html)


==================================================
© `2015` [Kuo-Cheng Yeu](http://lab.mikuru.tw) All rights reserved.
