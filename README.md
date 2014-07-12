# phergie/phergie-irc-plugin-react-feedticker

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for syndicating data from feed items to channels or users.

[![Build Status](https://secure.travis-ci.org/phergie/phergie-irc-plugin-react-feedticker.png?branch=master)](http://travis-ci.org/phergie/phergie-irc-plugin-react-feedticker)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "phergie/phergie-irc-plugin-react-feedticker": "dev-master"
    }
}
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
new \Phergie\Irc\Plugin\React\FeedTicker\Plugin(array(

    // required: list of feed URLs to poll for content
    'urls' => array(
        'http://feeds.mashable.com/Mashable',
        'http://readwrite.com/rss.xml',
        // ...
    ),

    // required: lists of channels or users to receive syndicated feed items
    //           keyed by associated connection mask
    'targets' => array(
        'nick1!user1@host1' => array(
            '#channel1',
            'user1',
            // ...
        ),
        'nick2!user2@host2' => array(
            '#channel2',
            'user2',
            // ...
        ),
    ),

    // optional: time in seconds to wait between polls of feeds for new
    //           content, defaults to 300 (5 minutes)
    'interval' => 300,

    // optional: object implementing \Phergie\Irc\Plugin\React\FeedTicker\FormatterInterface
    //           used to format data from feed items prior to their syndication
    'formatter' => new DefaultFormatter(
        '%title% [ %link% ] by %authorname% at %datemodified%',
        'Y-m-d H:i:s'
    ),

))
```

## Default Formatter

The default formatter, represented by the [`DefaultFormatter` class](https://github.com/phergie/phergie-irc-plugin-react-feedticker/blob/master/src/DefaultFormatter.php), should be sufficient for most use cases. Its constructor accepts two parameters. The first is a string containing placeholders for various data from feed items. Below is a list of the supported placeholders:

* `%authorname%`
* `%authoremail%`
* `%authoruri%`
* `%content%`
* `%datecreated%`
* `%datemodified%`
* `%description%`
* `%id%`
* `%link%`
* `%links%`
* `%permalink%`
* `%title%`
* `%commentcount%`
* `%commentlink%`
* `%commentfeedlink%`

The second parameter is an optional string containing a [date format](http://php.net/manual/en/function.date.php) to use when formatting the value of the `%datecreated%` and `%datemodified%` placeholders. It defaults to the ISO-8601 format.

## Custom Formatters

In cases where `DefaultFormatter` does not meet your needs, you can create your own formatter. This is merely a class that implements [`FormatterInterface`](https://github.com/phergie/phergie-irc-plugin-react-feedticker/blob/master/src/FormatterInterface.php).

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
cd tests
../vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
