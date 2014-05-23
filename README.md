# Scraphper!

**I needed a place to put a [PHP scraper I use from time-to-time](https://github.com/mhulse/scraphper#attribution); I couldn't find the original source code anywhere useful, so I put it here.**

### Features:

* PHP screen-scraping class with caching (including images).
* Includes static methods to extract data out of HTML tables into arrays or XML.
* Supports sending XML requests and custom verbs with support for making WebDAV requests to Microsoft Exchange Server.

### Attribution:

Original code written by [Troy Wolf](http://troywolf.com). I've since put it on [GitHub](https://github.com/mhulse/scraphper) and slightly modified the code work with [Composer](https://getcomposer.org/doc/00-intro.md).

### [Composer](https://getcomposer.org/doc/00-intro.md) installation:

Add Scraphper to your project's `composer.json`:

 ```json
{
  "require": {
    "mhulse/scraphper": "*"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/mhulse/scraphper"
    }
  ]
}
```

Run `$ php composer.phar install` or `$ php composer.phar update`.

Now you can use Scraphper:

```php
require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload.
$h = new Scraphper\Scrape(); // Instantiate a new http object.
```

See [`tests/index.php`](https://github.com/mhulse/scraphper/blob/master/tests/index.php) for more info.

### Local development:

1. Clone to computer.
1. Navigate to `$ cd scraphper/`.
1. Run `$ curl -s http://getcomposer.org/installer | php`
1. Run `$ php composer.phar install`.
1. Fire up Apache and visit `scraphper/tests/`.

**Note to future self:** When ready to release a new batch of changes, don’t forget to [draft a GitHub tag/release](https://github.com/mhulse/scraphper/releases).

### Links:

* [Creating your first Composer/Packagist package](http://grossi.io/2013/creating-your-first-composer-packagist-package/)

## Contributing

Please read the [CONTRIBUTING.md](https://github.com/mhulse/scraphper/blob/master/CONTRIBUTING.md).

## Feedback

[Bugs? Constructive feedback? Questions?](https://github.com/mhulse/scraphper/issues/new?title=Your%20code%20sucks!&body=Here%27s%20why%3A%2)

## Changelog

* [v1.0.0 milestones](https://github.com/mhulse/scraphper/issues?direction=desc&milestone=1&page=1&sort=updated&state=closed)

## [Release history](https://github.com/mhulse/scraphper/releases)

* 2014-05-22   [v0.1.0](https://github.com/mhulse/scraphper/releases/tag/v0.1.0)   Hello world!

---

#### LEGAL

Copyright © 2005 [Troy Wolf](http://troywolf.com), © modifications by [Micky Hulse](http://mky.io) in 2014 (confusing, no?).

<img width="20" height="20" align="absmiddle" src="https://github.global.ssl.fastly.net/images/icons/emoji/octocat.png" alt=":octocat:" title=":octocat:" class="emoji">
