=== Debug Bar Slow Actions ===
Contributors: kovshenin
Donate Link: http://kovshenin.com/beer/
Tags: debug, actions, profiling
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 0.8.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily find out which actions and filters are the slowest during a page load.

== Description ==

This plugin lists the top 100 slowest actions and filters during a page request in WordPress. It helps you figure out performance bottlenecks in themes and plugins.

Requires [Debug Bar](http://wordpress.org/plugins/debug-bar/ "Debug Bar").

Current limitations:

* Does not time nested actions and filters due to a core bug
* Does not time actions and filters before plugins_loaded or muplugins_loaded if placed in mu-plugins
* Does not time actions and callbacks after wp_footer at priority 1000

== Screenshots ==

1. Screenshot

== Changelog ==

= 0.8.2 =
* Fix a couple warnings/notices

= 0.8.1 =
* Add support for closure/anonymous functions
* Show all callbacks hooked to each priority
* Fix minor styles

= 0.8 =
* Code cleanup
* Use wp_footer for output instead of output buffering
* Show callbacks hooked to each action

= 0.7 =
* First version