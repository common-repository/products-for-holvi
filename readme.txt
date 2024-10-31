=== Products for Holvi ===
Contributors: softanalle
Donate link:
Tags: productlist, Holvi
Requires at least: 4.9
Tested up to: 4.9.1
Stable tag: 1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin creates a simple Holvi Web Shop -product listing as WordPress shortcode or widget.


== Description ==

If the standard IFRAME method of product listings from Holvi does not suite your needs, this might solve the problem. This simple plugin creates simple product listing from [Holvi](https://www.holvi.com) -service as WordPress shortcode. Most likely you need to create CSS to more appealing visual layout. And perhaps you wish to have more configuration options.

The generation of shortcode fetches the remote content from Holvi service and re-tagifies the product listing content suitable for textual presentation. The parsing of HTML code is done using Pharse -library, which is included in the plugin files.

Plugin has no admin-interface pages. Configuration is done with attributes embedded into shortcode tag and/or theme CSS.

Plugin does not generate any tracking information.

Plugin does its best not to include any executable code from Holvi -pages.

Plugin does not include JavaScript code. No JavaScript code or -hooks is embedded within generated code.

Currently this plugin does not do any caching, so each request to your page/post will generate a single request to Holvi service.

Plugin generates a GET query to public Holvi -service, for reading the active product list data (as HTML). This does not require any extra tracking-, authentication-, or other site/user specific information embedded into query. The information given through this query, can be modified by WordPress and/or PHP configuration, and is not controlled by this plugin.

The generated HTML output includes a direct "purchase" -link to Holvi product listing for active product, if product is not sold-out. This is a feature and cannot be disabled. 


== Installation ==

0. Get a personalised shop from [Holvi](https://www.holvi.com) -service.
1. Install the plugin files (unzip archive).
2. Activate plugin in the administration console.
3. Add [products_for_holvi] -shortcode to any post/page. Ending tag is optional. you can optionally add a Holvi Products -Widget to any supported widget container.
4. Use shortcode attributes (url, title, sort, strategy) to control the output or in case of widget, fill the values in the admin interface.
5. Implement your own formatting in Theme/CSS to format the output according your wishes.


== Short codes ==

The syntax of the shortcode, in the page/article objects is:

  [products_for_holvi url="http://holvi.com/shop/your-own-shop-name"]

The url should point to your shop public page, where you have the product listing. Do not use administrative interface pages, since this plugin does not know how to authenticate.

Ending tag [/products_for_holvi] is optional. If it is entered, then any content between tags is shown between title and the list of products.

The shortcode attributes are:

*   _url_

    The full URL expression, where to fetch Holvi product data from. This means that the http/https prefix is also required!
    
*   _title_

    Optional title to be shown in top of product listing, formatted with h2 -tags

*   _sort_

    Optional sorting of entries ( none | date | alpha ). Date sort only supports (currently) dd.mm.YYYY -format to work correctly.

*   _strategy_

    How to fetch data ( wordpress | internal | curl ), default WordPress should be ok for most installations. If you encounter problems with HTTPS, try internal or curl. If CURL is not enabled on your WordPress installation, the value (CURL) is not selectable in the widget. For shortcode, the missing CURL library results 'no product data' -output.

*   _show_error_

    For debugging. If selected, the error code is shown, to help in debugging.



== Widget ==

The widget arguments are exactly same than short code, except that you don't need to remember the argument names.

  <br/>


== CSS ==

The CSS classes, used in both shortcode and widget, are:

    div.proforhol-box           - div container for product listing
    h2.proforhol-title          - h2 title of product listing (see attr)

    b.proforhol-list            - "no data" -text container

    ul.proforhol-list           - product listing list
     li.proforhol-list-item     - product info (in list)
      span.proforhol-item-soldout - if product is soldout - title with 'soldout'
      span.proforhol-item-title   - product title
      a.proforhol-item-link       - direct "buy product" -link
      span.proforhol-item-price   - price text
      span.proforhol-item-description - description text

    .proforhol-soldout         - the sold-out item elements all have this class

Hint: Use CSS to hide 'soldout' -items:

    .proforhol-soldout {
      display: none;
    };

Or you could use 'text-decoration: line-through'  to over-line them.


== Important notes ==

As [Holvi](https://holvi.com) uses specific HTML code for the product listings, it is unlikely that this plugin works directly without any modifications with other service(s).

Administrative user should notice that this plugin uses PHP's file_get_contents() -function for remote query. Therefore it is possible to insert a url which would normally be blocked by WordPress url_filtering -rules.


== Support Contact ==

Developer of this plugin (softanalle) has no affiliation with Holvi -service, so all questions towards Holvi functionality should be directed directly to Holvi support.

Bugs, questions, feature requests and such about the plugin are welcome to the developer.


More information about Holvi service can be found:

* https://support.holvi.com/hc/en-gb


== Libs included ==

* Pharse library from: https://github.com/ressio/pharse


== Changelog ==

= 1.3 =
* Bug fixes on remote data query
* Added debug output checkbox (show error code on failed query)

= 1.2 =
* Added alternative methods for remote HTTP queries (Curl, WordPress internal)

= 1.1 =
* Fixed documentation indentation and spelling
* Added widget

= 1.0 =
* First public version of plugin released.
