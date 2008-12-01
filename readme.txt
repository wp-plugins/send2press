=== Send2Press ===
Contributors: Olav Kolbu
Donate link: http://www.kolbu.com/donations/
Tags: widget, plugin, send2press, news, send2press news, rss, feed
Requires at least: 2.3.3
Tested up to: 2.6.5
Stable tag: trunk

Displays news items from selectable Send2Press RSS feeds, 
inline, as a widget or in a theme. Multiple feeds allowed. 
Local caching.

== Description ==

Send2Press Newswire is a press release promotion service. They 
provide RSS feeds of all their news, and currently there are about 
90 different feeds available. This plugin allows easy access
to those feeds on your WordPress blog. Either as simple headline 
links or complete with a 5-10 line summary. CSS can
be used to tailor the look and feel.

Please note that even though can do whatever you want with this
plugin, including jumping up and down on it or feeding it to your
hamster, there are restrictions on how you can use Send2Press 
Newswire content and trademarks. So please read the terms of service 
carefully before using this plugin, either on the web at 
http://feeds.send2press.com/terms.shtml or the included text 
file termsofuse.txt.

This plugin works both as a widget, as inline content
replacement and can be called from themes. Any number of 
inline replacements or theme calls allowed, but only one 
widget instance is supported in this release.

For widget use, simply use the widget as any other after
selecting which feed it should display. For inline content
replacement, insert the one or more of the following strings in 
your content and they will be replaced by the relevant news feed.
For theme use, add the do_action function call described below.

1. **`<!--send2press-->`** for the default feed
1. **`<!--send2press#feedname-->`**

Shortcodes can be used if you have WordPress 2.5 or above,
in which case these replacement methods are also available.

1. **`[send2press]`** for the default feed
1. **`[send2press name="feedname"]`**

Calling the plugin from a theme is done with the WP do_action()
system. This will degrade gracefully and not produce errors
or output if plugin is disabled or removed.

1. **`<?php do_action('google_news'); ?>`** for the default feed
1. **`<?php do_action('google_news', 'feedname'); ?>`**

Enable plugin, go to the Send2Press page under 
Dashboard->Settings and read the initial information. Then 
go to the Send2Press page under Dashboard->Manage and 
configure one or more feeds. Then use a widget or insert
relevant strings in your content or theme. 

Additional information:

The available options are as follows. 

**Name:** Optional feed name, that can be used in the 
widget or the inline replacement string to reference
a specific feed. Any feed without a name is considered
"default" and will be used if the replacement strings do
not reference a specific feed. If there are more than
one feed with the same name, a random of these is picked
every time it is used. This also applies to the default
feed(s). 

**Title:** Optional, which when set will be used in the
widget title or as a header above the news items when 
inline. If the title is empty, then a default title
of "Send2Press : &lt;feed type&gt;" is used.

**News type:** The big dropdown list, with all available 
feeds from Send2Press.

**Output Type:** Links only, or complete with summary.

**Max items to show:** How many items from the feed to show, 0 for
all. The default is 10 items.

**Cache time:** The feeds are fetched using WordPress 
builtin MagpieRSS system, which allows for caching of feeds
a given amount of time. Cached feeds are stored in
the backend database. There is a lower limit of two hours 
imposed by Send2Press, but you can set it to something higher.
Cache time is in minutes.

If you want to change the look&feel, the inline table is 
wrapped in a div with the id "send2press-inline" and the
widget is wrapped in an li with id "send2press". Let me 
know if you need more to properly skin it.

**[Download now!](http://downloads.wordpress.org/plugin/send2press.zip)**

[Support](http://www.kolbu.com/2008/12/01/send2press-wordpress-plugin/)

[Donate](http://www.kolbu.com/donations/)


== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Unzip into the `/wp-content/plugins/` directory
1. Activate the plugin through the Dashboard->Plugins admin menu.
1. See configuation pages under Dashboard->Settings, Dashboard->Manage and on the widget page.

Note if you're upgrading from a previous release, there may be
some strangeness the first time you edit an old feed. Try again
and it will work. Or delete the feed and create again, guaranteed
fix. :-)

== Screenshots ==

1. Inline example under the Prosumer theme, replacing &lt;!--send2press--&gt; in content.
2. Small part of the admin Manage page for the plugin.

== Changelog ==

1. 2.3 Initial release, based on my CNN News and Google News plugins v2.3. Numbering scheme to be able to keep all similar plugins in sync.


Known bugs:
  - None at this time
