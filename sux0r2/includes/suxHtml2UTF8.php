<?php

/**
* suxHtml2UTF8
*
* Forked from / Inspired by:
* Jon Abernathy <jon@chuggnutt.com>: http://www.chuggnutt.com/html2text.php
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class suxHtml2UTF8 {


    // ----------------------------------------------------------------------------
    // Variables
    // ----------------------------------------------------------------------------


    /**
    *  Contains the HTML content to convert.
    *
    *  @param string $html
    */
    public $html;


    /**
    *  Contains the converted, formatted UTF-8 text
    *
    *  @param string $text
    */
    public $text;


    /**
    *  Contains the base URL that relative links should resolve to.
    *
    *  @param string $url
    */
    public $url;


    /**
    *  List of preg* regular expression patterns to search for,
    *  used in conjunction with $replace.
    *
    *  @param array $search
    *  @see $replace
    */
    private array $search = array(
        "/\r/",                                  // Non-legal carriage return
        "/[\n\t]+/",                             // Newlines and tabs
        '/[ ]{2,}/',                             // Runs of spaces, pre-handling
        '/<script[^>]*>.*?<\/script>/si',        // <script>s -- which strip_tags supposedly has problems with
        '/<style[^>]*>.*?<\/style>/si',          // <style>s -- which strip_tags supposedly has problems with
        '/<h[123][^>]*>(.*?)<\/h[123]>/sie',     // H1 - H3
        '/<h[456][^>]*>(.*?)<\/h[456]>/sie',     // H4 - H6
        '/<p[^>]*>/i',                           // <P>
        '/<br[^>]*>/i',                          // <br>
        '/<b[^>]*>(.*?)<\/b>/sie',               // <b>
        '/<strong[^>]*>(.*?)<\/strong>/sie',     // <strong>
        '/<i[^>]*>(.*?)<\/i>/si',                // <i>
        '/<em[^>]*>(.*?)<\/em>/si',              // <em>
        '/(<ul[^>]*>|<\/ul>)/i',                 // <ul> and </ul>
        '/(<ol[^>]*>|<\/ol>)/i',                 // <ol> and </ol>
        '/<li[^>]*>(.*?)<\/li>/si',              // <li> and </li>
        '/<li[^>]*>/i',                          // <li>
        '#<a[\s]+[^>]*?href[\s]?=[\s"\']+(.*?)["\']+.*?>([^<]+|.*?)?</a>#sie', // <a href="">, <a href=''>, and other mutations
        '/<hr[^>]*>/i',                          // <hr>
        '/(<table[^>]*>|<\/table>)/i',           // <table> and </table>
        '/(<tr[^>]*>|<\/tr>)/i',                 // <tr> and </tr>
        '/<td[^>]*>(.*?)<\/td>/si',              // <td> and </td>
        '/<th[^>]*>(.*?)<\/th>/sie',             // <th> and </th>
        '/&(nbsp|#160);/i',                      // Non-breaking space
        '/[ ]{2,}/'                              // Runs of spaces, post-handling
        );


    /**
    *  List of pattern replacements corresponding to patterns searched.
    *
    *  @param array $replace
    *  @see $search
    */
    private array $replace = array(
        '',                                     // Non-legal carriage return
        ' ',                                    // Newlines and tabs
        ' ',                                    // Runs of spaces, pre-handling
        '',                                     // <script>s -- which strip_tags supposedly has problems with
        '',                                     // <style>s -- which strip_tags supposedly has problems with
        "mb_strtoupper(\"\n\n\\1\n\n\")",       // H1 - H3
        "ucwords(\"\n\n\\1\n\n\")",             // H4 - H6
        "\n\n\t",                               // <P>
        "\n",                                   // <br>
        'mb_strtoupper("\\1")',                 // <b>
        'mb_strtoupper("\\1")',                 // <strong>
        '_\\1_',                                // <i>
        '_\\1_',                                // <em>
        "\n\n",                                 // <ul> and </ul>
        "\n\n",                                 // <ol> and </ol>
        "\t* \\1\n",                            // <li> and </li>
        "\n\t* ",                               // <li>
        '$this->buildLinkList("\\1", "\\2")', // <a href="">
        "\n-------------------------\n",        // <hr>
        "\n\n",                                 // <table> and </table>
        "\n",                                   // <tr> and </tr>
        "\t\t\\1\n",                            // <td> and </td>
        "mb_strtoupper(\"\t\t\\1\n\")",         // <th> and </th>
        ' ',                                    // Non-breaking space
        ' '                                     // Runs of spaces, post-handling
        );


    /**
    *  Indicates whether content in the $html variable has been converted yet.
    *
    *  @param boolean $converted
    *  @see $html, $text
    */
    private bool $converted = false;


    /**
    *  Contains URL addresses from links to be rendered in plain UTF-8 text.
    *
    *  @param string $link_list
    *  @see buildLinkList()
    */
    private string $link_list = '';


    /**
    *  Number of valid links detected in the text, used for plain UTF-8 text
    *  display (rendered similar to footnotes).
    *
    *  @param integer $link_count
    *  @see buildLinkList()
    */
    private int $link_count = 0;


    // ----------------------------------------------------------------------------
    // Functions
    // ----------------------------------------------------------------------------


    /**
    *  Constructor.
    *
    *  If the HTML source string (or file) is supplied, the class
    *  will instantiate with that source propagated, all that has
    *  to be done it to call getText().
    *
    *  @param string $source HTML content
    *  @param boolean $from_file Indicates $source is a file to pull content from
    */
    function __construct( $source = '', $from_file = false ) {
        if ( !empty($source) ) {
            $this->setHtml($source, $from_file);
        }
        $this->setBaseUrl();
    }


    /**
    *  Loads source HTML into memory, either from $source string or a file.
    *
    *  @param string $source HTML content
    *  @param boolean $from_file Indicates $source is a file to pull content from
    */
    function setHtml( $source, $from_file = false ) {

        $this->html = $source;
        if ($from_file && file_exists($source)) {
            $this->html = file_get_contents($source);
        }
        $this->converted = false;

    }


    /**
    *  Returns the UTF-8 text, converted from HTML.
    *
    *  @return string
    */
    function getText() {

        if (!$this->converted) {
            $this->convert();
        }
        return $this->text;

    }


    /**
    * Sets a base URL to handle relative links.
    *
    * @param string $url url
    */
    function setBaseUrl( $url = '' ) {
        if ( empty($url) ) {
        	if ( !empty($_SERVER['HTTP_HOST']) ) {
	            $this->url = 'http://' . $_SERVER['HTTP_HOST'];
        	} else {
	            $this->url = '';
	        }
        } else {
            // Strip any trailing slashes for consistency (relative
            // URLs may already start with a slash like "/file.html")
            if ( mb_substr($url, -1) == '/' ) {
                $url = mb_substr($url, 0, -1);
            }
            $this->url = $url;
        }
    }


    /**
    *  Workhorse function that does actual conversion.
    *
    *  First performs custom tag replacement specified by $search and
    *  $replace arrays. Then strips any remaining HTML tags, and reduces whitespace
    *  and newlines to a readable format
    *
    */
    private function convert() {

        // Variables used for building the link list
        $this->link_count = 0;
        $this->link_list = '';

        // Sanity check: Compare the original input to the result of striptags()
        // If they're the same, there must not have been any tags

        if ($this->html == strip_tags($this->html)) {

            /* Mild conversion */

            // Make entities into UTF-8 characters
            $text = trim(html_entity_decode(stripslashes($this->html), ENT_QUOTES, 'UTF-8'));

        }
        else {

            /* Full Html to text conversion */

            // Make entities into UTF-8 characters
            $text = trim(html_entity_decode(stripslashes($this->html), ENT_QUOTES, 'UTF-8'));

            // Run our defined search-and-replace
            $text = (string) @preg_replace($this->search, $this->replace, $text); // Ignore warnings?

            // Strip any other HTML tags
            $text = strip_tags($text);

        }

        // Bring down number of empty lines to 2 max
        $text = preg_replace("/\n\s+\n/", "\n\n", $text);
        $text = preg_replace("/[\n]{3,}/", "\n\n", $text);

        // Add link list
        if ( !empty($this->link_list) ) {
            $text .= "\n\n" . $this->link_list;
        }

        // Get rid of spaces except tabs at the start, all spaces at end
        $text = ltrim($text, " \n\r\0\x0B");
        $text = rtrim($text);

        // Set text
        $this->text = $text;

        // Remeber this
        $this->converted = true;

    }


    /**
    *  Helper function called by preg_replace() on link replacement.
    *
    *  Maintains an internal list of links to be displayed at the end of the
    *  text, with numeric indices to the original point in the text they
    *  appeared. Also makes an effort at identifying and handling absolute
    *  and relative links.
    *
    *  @param string $link URL of the link
    *  @param string $display Part of the text to associate number with
    *  @return string
    */
    private function buildLinkList( $link, $display ) {

		if (mb_substr($link, 0, 7) == 'http://' || mb_substr($link, 0, 8) == 'https://' || mb_substr($link, 0, 7) == 'mailto:') {

            // Absolute href links
            $link = suxFunct::canonicalizeUrl($link);
            $this->link_count++;
            $this->link_list .= "[" . $this->link_count . "] $link\n";
            $additional = ' [' . $this->link_count . ']';

		}
        elseif (mb_substr($link, 0, 11) == 'javascript:') {

			// Ignore javascript links
			$additional = '';

        }
        else {

            // Relative href links
            $this->link_count++;
            $this->link_list .= "[" . $this->link_count . "] ";
            if ( mb_substr($link, 0, 1) != '/' ) {
                $link = '/' . $link;
            }
            $link = suxFunct::canonicalizeUrl($this->url . $link);
            $this->link_list .= "$link\n";
            $additional = ' [' . $this->link_count . ']';

        }

        return $display . $additional;

    }

}

