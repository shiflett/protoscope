Protoscope: an HTTP proxy for inspecting and debugging HTTP
===========================================================

Requirements
------------

- [PHP](http://php.net/)

Usage
-----

To start the proxy:

	./protoscope.php

Configure your browser of choice to use an HTTP proxy. By default, Protoscope
binds to `127.0.0.1` on port `4887`.

Browse the Web, and your HTTP requests are shown to you at the bottom of each
page. This is useful for debugging a number of issues, including session
problems, caching problems, etc. HTTP traffic is also sent to `stdout`, which
you can send to a log file if you want by starting the proxy as follows:

	./protoscope.php > /tmp/protoscope.log

This is a work in progress. If you don't know what you're doing, you probably
don't want to use it.

Known Issues
------------

The following issues will be addressed in future releases:

- No support for tunneling, so no SSL.
- No embed support for chunked encoding.

Also see the [open issues][https://github.com/shiflett/protoscope/issues].
