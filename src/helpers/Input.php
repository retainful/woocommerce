<?php

namespace Rnoc\Retainful\Helpers;
if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Input
{
    /**
     * Character set
     *
     * Will be overridden by the constructor.
     *
     * @var    string
     */
    public $charset = 'UTF-8';
    /**
     * IP address of the current user
     *
     * @var    string
     */
    protected $ip_address = FALSE;
    /**
     * Allow GET array flag
     *
     * If set to FALSE, then $_GET will be set to an empty array.
     *
     * @var    bool
     */
    protected $_allow_get_array = TRUE;
    /**
     * Standardize new lines flag
     *
     * If set to TRUE, then newlines are standardized.
     *
     * @var    bool
     */
    protected $_standardize_newlines;
    /**
     * Enable XSS flag
     *
     * Determines whether the XSS filter is always active when
     * GET, POST or COOKIE data is encountered.
     * Set automatically based on config setting.
     *
     * @var    bool
     */
    protected $_enable_xss = TRUE;
    /**
     * List of all HTTP request headers
     *
     * @var array
     */
    protected $headers = array();
    /**
     * Raw input stream data
     *
     * Holds a cache of php://input contents
     *
     * @var    string
     */
    protected $_raw_input_stream;
    /**
     * List of never allowed strings
     *
     * @var    array
     */
    protected $_never_allowed_str = array(
        'document.cookie' => '[removed]',
        '(document).cookie' => '[removed]',
        'document.write' => '[removed]',
        '(document).write' => '[removed]',
        '.parentNode' => '[removed]',
        '.innerHTML' => '[removed]',
        '-moz-binding' => '[removed]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;',
        '<%' => '&lt;&#37;'
    );
    /**
     * List of never allowed regex replacements
     *
     * @var    array
     */
    protected $_never_allowed_regex = array(
        'javascript\s*:',
        '(\(?document\)?|\(?window\)?(\.document)?)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    );
    /**
     * XSS Hash
     *
     * Random Hash for protecting URLs.
     *
     * @var    string
     */
    protected $_xss_hash;
    /**
     * Parsed input stream data
     *
     * Parsed from php://input at runtime
     *
     * @see    CI_Input::input_stream()
     * @var    array
     */
    protected $_input_stream;
    protected $security;
    protected $uni;

    function sanitize_globals(){
        // Sanitize global arrays
        $this->_sanitize_globals();
    }

    /**
     * Sanitize Globals
     */
    protected function _sanitize_globals()
    {
        // Is $_GET data allowed? If not we'll set the $_GET to an empty array
        if ($this->_allow_get_array === FALSE) {
            $_GET = array();
        } elseif (is_array($_GET)) {
            foreach ($_GET as $key => $val) {
                $_GET[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }
        // Clean $_POST Data
        if (is_array($_POST)) {
            foreach ($_POST as $key => $val) {
                $_POST[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }
        // Clean $_COOKIE Data
        if (is_array($_COOKIE)) {
            // Also get rid of specially treated cookies that might be set by a server
            // or silly application, that are of no use to a CI application anyway
            // but that when present will trip our 'Disallowed Key Characters' alarm
            // http://www.ietf.org/rfc/rfc2109.txt
            // note that the key names below are single quoted strings, and are not PHP variables
            unset(
                $_COOKIE['$Version'],
                $_COOKIE['$Path'],
                $_COOKIE['$Domain']
            );
            foreach ($_COOKIE as $key => $val) {
                if (($cookie_key = $this->_clean_input_keys($key)) !== FALSE) {
                    $_COOKIE[$cookie_key] = $this->_clean_input_data($val);
                } else {
                    unset($_COOKIE[$key]);
                }
            }
        }
        // Sanitize PHP_SELF
        $_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);
    }

    /**
     * Clean Keys
     * @param $str
     * @param bool $fatal
     * @return bool
     */
    protected function _clean_input_keys($str, $fatal = TRUE)
    {
        /* Commented this as it sanitize all request values which making conflict with other plugins */
        /*if (!preg_match('/^[a-z0-9:_\/|-]+$/i', $str)) {
            if ($fatal === TRUE) {
                return FALSE;
            } else {
                $this->set_status_header(503);
                echo 'Disallowed Key Characters.';
                exit(7); // EXIT_USER_INPUT
            }
        }*/
        return $str;
    }

    /**
     * Set HTTP Status Header
     * @param int $code
     * @param string $text
     */
    function set_status_header($code = 200, $text = '')
    {
        if (empty($text)) {
            is_int($code) or $code = (int)$code;
            $stati = array(
                100 => 'Continue',
                101 => 'Switching Protocols',
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                422 => 'Unprocessable Entity',
                426 => 'Upgrade Required',
                428 => 'Precondition Required',
                429 => 'Too Many Requests',
                431 => 'Request Header Fields Too Large',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported',
                511 => 'Network Authentication Required',
            );
            if (isset($stati[$code])) {
                $text = $stati[$code];
            }
        }
        $server_protocol = (isset($_SERVER['SERVER_PROTOCOL']) && in_array($_SERVER['SERVER_PROTOCOL'], array('HTTP/1.0', 'HTTP/1.1', 'HTTP/2'), TRUE))
            ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($server_protocol . ' ' . $code . ' ' . $text, TRUE, $code);
    }

    /**
     * Clean Input Data
     * @param $str
     * @return array|string|string[]|null
     */
    protected function _clean_input_data($str)
    {
        if (is_object($str)) {
            return $str;
        }
        if (is_array($str)) {
            $new_array = array();
            foreach (array_keys($str) as $key) {
                $new_array[$this->_clean_input_keys($key)] = $this->_clean_input_data($str[$key]);
            }
            return $new_array;
        }
        /* We strip slashes if magic quotes is on to keep things consistent

           NOTE: In PHP 5.4 get_magic_quotes_gpc() will always return 0 and
                 it will probably not exist in future versions at all.
        */
        // Remove control characters
        $str = $this->remove_invisible_characters($str, FALSE);
        // Standardize newlines if needed
        if ($this->_standardize_newlines === TRUE) {
            return preg_replace('/(?:\r\n|[\r\n])/', PHP_EOL, $str);
        }
        return $str;
    }

    /**
     * Determines if the current version of PHP is equal to or greater than the supplied value
     * @param $version
     * @return mixed
     */
    function is_php($version)
    {
        static $_is_php;
        $version = (string)$version;
        if (!isset($_is_php[$version])) {
            $_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
        }
        return $_is_php[$version];
    }

    /**
     * Remove Invisible Characters
     * @param $str
     * @param bool $url_encoded
     * @return string|string[]|null
     */
    function remove_invisible_characters($str, $url_encoded = TRUE)
    {
        return $str;
        $non_displayables = array();
        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/i';    // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/i';    // url encoded 16-31
            $non_displayables[] = '/%7f/i';    // url encoded 127
        }
        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127
        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);
        return $str;
    }

    /**
     * Fetch an item from POST data with fallback to GET
     * @param $index
     * @param null $xss_clean
     * @param null $default
     * @return mixed
     */
    function post_get($index, $default = NULL, $xss_clean = NULL)
    {
        return isset($_POST[$index])
            ? $this->post($index, $default, $xss_clean)
            : $this->get($index, $default, $xss_clean);
    }

    /**
     * Fetch an item from the POST array
     * @param null $index
     * @param null $default
     * @param null $xss_clean
     * @return mixed
     */
    function post($index = NULL, $default = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_POST, $index, $default, $xss_clean);
    }

    /**
     * check an item found the POST array
     * @param null $index
     * @return mixed
     */
    function has_post($index = NULL)
    {
        return isset($_POST[$index]);
    }

    /**
     * Fetch from array
     * @param $array
     * @param null $index
     * @param null $default
     * @param null $xss_clean
     * @return array|string|null
     */
    protected function _fetch_from_array(&$array, $index = NULL, $default = NULL, $xss_clean = NULL)
    {
        is_bool($xss_clean) or $xss_clean = $this->_enable_xss;
        // If $index is NULL, it means that the whole $array is requested
        $index = (!isset($index) || is_null($index)) ? array_keys($array) : $index;
        // allow fetching multiple keys at once
        if (is_array($index)) {
            $output = array();
            foreach ($index as $key) {
                $output[$key] = $this->_fetch_from_array($array, $key, $default, $xss_clean);
            }
            return $output;
        }
        if (isset($array[$index])) {
            $value = $array[$index];
        } elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) // Does the index contain array notation
        {
            $value = $array;
            for ($i = 0; $i < $count; $i++) {
                $key = trim($matches[0][$i], '[]');
                if ($key === '') // Empty notation will return the value as array
                {
                    break;
                }
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return NULL;
                }
            }
        } else {
            return $default;
        }
        return ($xss_clean === TRUE) ? $this->xss_clean($value) : $value;
    }

    /**
     * XSS Clean
     * @param $str
     * @param bool $is_image
     * @return array|bool|string|string[]|null
     */
    function xss_clean($str, $is_image = FALSE)
    {
        if (is_object($str)) {
            return $str;
        }
        // Is the string an array?
        if (is_array($str)) {
            foreach ($str as $key => &$value) {
                $str[$key] = $this->xss_clean($value);
            }
            return $str;
        }
        // Remove Invisible Characters
        $str = $this->remove_invisible_characters($str);
        /*
         * URL Decode
         *
         * Just in case stuff like this is submitted:
         *
         * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
         *
         * Note: Use rawurldecode() so it does not remove plus signs
         */
        if (stripos($str, '%') !== false) {
            do {
                $oldstr = $str;
                $str = rawurldecode($str);
                $str = preg_replace_callback('#%(?:\s*[0-9a-f]){2,}#i', array($this, '_urldecodespaces'), $str);
            } while ($oldstr !== $str);
            unset($oldstr);
        }
        /*
         * Convert character entities to ASCII
         *
         * This permits our tests below to work reliably.
         * We only convert entities that are within tags since
         * these are the ones that will pose security problems.
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", array($this, '_convert_attribute'), $str);
        $str = preg_replace_callback('/<\w+.*/si', array($this, '_decode_entity'), $str);
        // Remove Invisible Characters Again!
        $str = $this->remove_invisible_characters($str);
        /*
         * Convert all tabs to spaces
         *
         * This prevents strings like this: ja	vascript
         * NOTE: we deal with spaces between characters later.
         * NOTE: preg_replace was found to be amazingly slow here on
         * large blocks of data, so we use str_replace.
         */
        $str = str_replace("\t", ' ', $str);
        // Capture converted string for later comparison
        $converted_string = $str;
        // Remove Strings that are never allowed
        $str = $this->_do_never_allowed($str);
        /*
         * Makes PHP tags safe
         *
         * Note: XML tags are inadvertently replaced too:
         *
         * <?xml
         *
         * But it doesn't seem to pose a problem.
         */
        if ($is_image === TRUE) {
            // Images have a tendency to have the PHP short opening and
            // closing tags every so often so we skip those and only
            // do the long opening tags.
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        } else {
            $str = str_replace(array('<?', '?' . '>'), array('&lt;?', '?&gt;'), $str);
        }
        /*
         * Compact any exploded words
         *
         * This corrects words like:  j a v a s c r i p t
         * These words are compacted back to their correct state.
         */
        $words = array(
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt', 'eval'
        );
        foreach ($words as $word) {
            $word = implode('\s*', str_split($word)) . '\s*';
            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#(' . substr($word, 0, -3) . ')(\W)#is', array($this, '_compact_exploded_words'), $str);
        }
        /*
         * Remove disallowed Javascript in links or img tags
         * We used to do some version comparisons and use of stripos(),
         * but it is dog slow compared to these simplified non-capturing
         * preg_match(), especially if the pattern exists in the string
         *
         * Note: It was reported that not only space characters, but all in
         * the following pattern can be parsed as separators between a tag name
         * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
         * ... however, remove_invisible_characters() above already strips the
         * hex-encoded ones, so we'll skip them below.
         */
        do {
            $original = $str;
            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('#<a(?:rea)?[^a-z0-9>]+([^>]*?)(?:>|$)#si', array($this, '_js_link_removal'), $str);
            }
            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', array($this, '_js_img_removal'), $str);
            }
            if (preg_match('/script|xss/i', $str)) {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
            }
        } while ($original !== $str);
        unset($original);
        /*
         * Sanitize naughty HTML elements
         *
         * If a tag containing any of the words in the list
         * below is found, the tag gets converted to entities.
         *
         * So this: <blink>
         * Becomes: &lt;blink&gt;
         */
        $pattern = '#'
            . '<((?<slash>/*\s*)((?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)|.+)' // tag start and name, followed by a non-tag character
            . '[^\s\042\047a-z0-9>/=]*' // a valid attribute character immediately after the tag would count as a separator
            // optional attributes
            . '(?<attributes>(?:[\s\042\047/=]*' // non-attribute characters, excluding > (tag close) for obvious reasons
            . '[^\s\042\047>/=]+' // attribute characters
            // optional attribute-value
            . '(?:\s*=' // attribute-value separator
            . '(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))' // single, double or non-quoted value
            . ')?' // end optional attribute-value group
            . ')*)' // end optional attributes group
            . '[^>]*)(?<closeTag>\>)?#isS';
        // Note: It would be nice to optimize this for speed, BUT
        //       only matching the naughty elements here results in
        //       false positives and in turn - vulnerabilities!
        do {
            $old_str = $str;
            $str = preg_replace_callback($pattern, array($this, '_sanitize_naughty_html'), $str);
        } while ($old_str !== $str);
        unset($old_str);
        /*
         * Sanitize naughty scripting elements
         *
         * Similar to above, only instead of looking for
         * tags it looks for PHP and JavaScript commands
         * that are disallowed. Rather than removing the
         * code, it simply converts the parenthesis to entities
         * rendering the code un-executable.
         *
         * For example:	eval('some code')
         * Becomes:	eval&#40;'some code'&#41;
         */
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
            '\\1\\2&#40;\\3&#41;',
            $str
        );
        // Same thing, but for "tag functions" (e.g. eval`some code`)
        // See https://github.com/bcit-ci/CodeIgniter/issues/5420
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)`(.*?)`#si',
            '\\1\\2&#96;\\3&#96;',
            $str
        );
        // Final clean up
        // This adds a bit of extra precaution in case
        // something got through the above filters
        $str = $this->_do_never_allowed($str);
        /*
         * Images are Handled in a Special Way
         * - Essentially, we want to know that after all of the character
         * conversion is done whether any unwanted, likely XSS, code was found.
         * If not, we return TRUE, as the image is clean.
         * However, if the string post-conversion does not matched the
         * string post-removal of XSS, then it fails, as there was unwanted XSS
         * code found and removed/changed during processing.
         */
        if ($is_image === TRUE) {
            return ($str === $converted_string);
        }
        return $str;
    }

    /**
     * Do Never Allowed
     * @param $str
     * @return mixed|string|string[]|null
     */
    protected
    function _do_never_allowed($str)
    {
        $str = str_replace(array_keys($this->_never_allowed_str), $this->_never_allowed_str, $str);
        foreach ($this->_never_allowed_regex as $regex) {
            $str = preg_replace('#' . $regex . '#is', '[removed]', $str);
        }
        return $str;
    }

    /**
     * Fetch an item from the GET array
     * @param null $index
     * @param null $default
     * @param null $xss_clean
     * @return mixed
     */
    function get($index = NULL, $default = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_GET, $index, $default, $xss_clean);
    }

    /**
     * Fetch an item from GET data with fallback to POST
     * @param $index
     * @param null $xss_clean
     * @param null $default
     * @return mixed
     */
    function get_post($index, $default = NULL, $xss_clean = NULL)
    {
        return isset($_GET[$index])
            ? $this->get($index, $default, $xss_clean)
            : $this->post($index, $default, $xss_clean);
    }

    /**
     * Fetch an item from the COOKIE array
     * @param null $index
     * @param null $default
     * @param null $xss_clean
     * @return mixed
     */
    function cookie($index = NULL, $default = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_COOKIE, $index, $default, $xss_clean);
    }

    /**
     * Fetch an item from the php://input stream
     * @param null $index
     * @param null $default
     * @param null $xss_clean
     * @return mixed
     */
    function input_stream($index = NULL, $default = NULL, $xss_clean = NULL)
    {
        // Prior to PHP 5.6, the input stream can only be read once,
        // so we'll need to check if we have already done that first.
        if (!is_array($this->_input_stream)) {
            // $this->raw_input_stream will trigger __get().
            parse_str($this->_raw_input_stream, $this->_input_stream);
            is_array($this->_input_stream) or $this->_input_stream = array();
        }
        return $this->_fetch_from_array($this->_input_stream, $index, $default, $xss_clean);
    }

    /**
     * Fetch User Agent string
     * @param null $xss_clean
     * @param null $default
     * @return mixed
     */
    function user_agent($xss_clean = NULL, $default = NULL)
    {
        return $this->_fetch_from_array($_SERVER, 'HTTP_USER_AGENT', $default, $xss_clean);
    }

    /**
     * Get Request Header
     * @param $index
     * @param bool $xss_clean
     * @return string|null
     */
    function get_request_header($index, $xss_clean = FALSE)
    {
        static $headers;
        if (!isset($headers)) {
            empty($this->headers) && $this->request_headers();
            foreach ($this->headers as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }
        $index = strtolower($index);
        if (!isset($headers[$index])) {
            return NULL;
        }
        return ($xss_clean === TRUE)
            ? $this->xss_clean($headers[$index])
            : $headers[$index];
    }

    /**
     * Request Headers
     * @param bool $xss_clean
     * @return mixed
     */
    function request_headers($xss_clean = FALSE)
    {
        // If header is already defined, return it immediately
        if (!empty($this->headers)) {
            return $this->_fetch_from_array($this->headers, NULL, NULL, $xss_clean);
        }
        // In Apache, you can simply call apache_request_headers()
        if (function_exists('apache_request_headers')) {
            $this->headers = apache_request_headers();
        } else {
            isset($_SERVER['CONTENT_TYPE']) && $this->headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            foreach ($_SERVER as $key => $val) {
                if (sscanf($key, 'HTTP_%s', $header) === 1) {
                    // take SOME_HEADER and turn it into Some-Header
                    $header = str_replace('_', ' ', strtolower($header));
                    $header = str_replace(' ', '-', ucwords($header));
                    $this->headers[$header] = $_SERVER[$key];
                }
            }
        }
        return $this->_fetch_from_array($this->headers, NULL, NULL, $xss_clean);
    }

    /**
     * Is AJAX request?
     * @return bool
     */
    function is_ajax_request()
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Get Request Method
     * @param bool $upper
     * @return string
     */
    function method($upper = FALSE)
    {
        return ($upper)
            ? strtoupper($this->server('REQUEST_METHOD'))
            : strtolower($this->server('REQUEST_METHOD'));
    }

    /**
     * Fetch an item from the SERVER array
     * @param $index
     * @param null $xss_clean
     * @param null $default
     * @return mixed
     */
    function server($index, $default = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_SERVER, $index, $default, $xss_clean);
    }

    /**
     * Allows read access to protected properties
     * @param $name
     * @return false|string
     */
    function __get($name)
    {
        if ($name === 'raw_input_stream') {
            isset($this->_raw_input_stream) or $this->_raw_input_stream = file_get_contents('php://input');
            return $this->_raw_input_stream;
        } elseif ($name === 'ip_address') {
            return $this->ip_address;
        }
    }

    /**
     * URL-decode taking spaces into account
     * @param $matches
     * @return string
     */
    protected
    function _urldecodespaces($matches)
    {
        $input = $matches[0];
        $nospaces = preg_replace('#\s+#', '', $input);
        return ($nospaces === $input)
            ? $input
            : rawurldecode($nospaces);
    }

    /**
     * HTML Entity Decode Callback
     * @param $match
     * @return mixed
     * @throws \Exception
     */
    protected
    function _decode_entity($match)
    {
        // Protect GET variables in URLs
        // 901119URL5918AMP18930PROTECT8198
        $match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->xss_hash() . '\\1=\\2', $match[0]);
        // Decode, then un-protect URL GET vars
        return str_replace(
            $this->xss_hash(),
            '&',
            $this->entity_decode($match, $this->charset)
        );
    }

    /**
     * Generates the XSS hash if needed and returns it.
     * @return mixed|string
     * @throws \Exception
     */
    function xss_hash()
    {
        if ($this->_xss_hash === NULL) {
            $rand = $this->get_random_bytes(16);
            $this->_xss_hash = ($rand === FALSE)
                ? md5(uniqid(mt_rand(), TRUE))
                : bin2hex($rand);
        }
        return $this->_xss_hash;
    }

    /**
     * Get random bytes
     * @param $length
     * @return bool|string|void
     * @throws \Exception
     */
    function get_random_bytes($length)
    {
        if (empty($length) or !ctype_digit((string)$length)) {
            return FALSE;
        }
        if (function_exists('random_bytes')) {
            try {
                // The cast is required to avoid TypeError
                return random_bytes((int)$length);
            } catch (\Exception $e) {
                // If random_bytes() can't do the job, we can't either ...
                // There's no point in using fallbacks.
                //log_message('error', $e->getMessage());
                return FALSE;
            }
        }
        if (is_readable('/dev/urandom') && ($fp = fopen('/dev/urandom', 'rb')) !== FALSE) {
            // Try not to waste entropy ...
            $this->is_php('5.4') && stream_set_chunk_size($fp, $length);
            $output = fread($fp, $length);
            fclose($fp);
            if ($output !== FALSE) {
                return $output;
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }
        return FALSE;
    }

    /**
     * HTML Entities Decode
     * @param $str
     * @param null $charset
     * @return mixed|string
     */
    function entity_decode($str, $charset = NULL)
    {
        if (strpos($str, '&') === FALSE) {
            return $str;
        }
        static $_entities;
        isset($charset) or $charset = $this->charset;
        $flag = $this->is_php('5.4')
            ? ENT_COMPAT | ENT_HTML5
            : ENT_COMPAT;
        if (!isset($_entities)) {
            $_entities = array_map('strtolower', get_html_translation_table(HTML_ENTITIES, $flag, $charset));
            // If we're not on PHP 5.4+, add the possibly dangerous HTML 5
            // entities to the array manually
            if ($flag === ENT_COMPAT) {
                $_entities[':'] = '&colon;';
                $_entities['('] = '&lpar;';
                $_entities[')'] = '&rpar;';
                $_entities["\n"] = '&NewLine;';
                $_entities["\t"] = '&Tab;';
            }
        }
        do {
            $str_compare = $str;
            // Decode standard entities, avoiding false positives
            if (preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches)) {
                $replace = array();
                $matches = array_unique(array_map('strtolower', $matches[0]));
                foreach ($matches as &$match) {
                    if (($char = array_search($match . ';', $_entities, TRUE)) !== FALSE) {
                        $replace[$match] = $char;
                    }
                }
                $str = str_replace(array_keys($replace), array_values($replace), $str);
            }
            // Decode numeric & UTF16 two byte entities
            $str = html_entity_decode(
                preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str),
                $flag,
                $charset
            );
            if ($flag === ENT_COMPAT) {
                $str = str_replace(array_values($_entities), array_keys($_entities), $str);
            }
        } while ($str_compare !== $str);
        return $str;
    }

    /**
     * Compact Exploded Words
     * @param $matches
     * @return string
     */
    protected function _compact_exploded_words($matches)
    {
        return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
    }

    /**
     * JS Link Removal
     *
     * Callback function for xss_clean() to sanitize links
     * This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on link-heavy strings
     *
     * @param array
     * @return  string
     */
    protected function _js_link_removal($match)
    {
        return str_replace(
            $match[1],
            preg_replace(
                '#href=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si',
                '',
                $this->_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
            ),
            $match[0]
        );
    }

    /**
     * JS Image Removal
     *
     * Callback function for xss_clean() to sanitize image tags
     * This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings
     *
     * @param array
     * @return  string
     */
    protected function _js_img_removal($match)
    {
        return str_replace(
            $match[1],
            preg_replace(
                '#src=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
                '',
                $this->_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
            ),
            $match[0]
        );
    }

    /**
     * Filter Attributes
     *
     * Filters tag attributes for consistency and safety
     *
     * @param string
     * @return  string
     */
    protected function _filter_attributes($str)
    {
        $out = '';
        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as $match) {
                $out .= preg_replace("#/\*.*?\*/#s", '', $match);
            }
        }
        return $out;
    }

    /**
     * Sanitize Naughty HTML
     * @param $matches
     * @return string
     */
    protected function _sanitize_naughty_html($matches)
    {
        static $naughty_tags = array(
            'alert', 'area', 'prompt', 'confirm', 'applet', 'audio', 'basefont', 'base', 'behavior', 'bgsound',
            'blink', 'body', 'embed', 'expression', 'form', 'frameset', 'frame', 'head', 'html', 'ilayer',
            'iframe', 'input', 'button', 'select', 'isindex', 'layer', 'link', 'meta', 'keygen', 'object',
            'plaintext', 'style', 'script', 'textarea', 'title', 'math', 'video', 'svg', 'xml', 'xss'
        );
        static $evil_attributes = array(
            'on\w+', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'
        );
        // First, escape unclosed tags
        if (empty($matches['closeTag'])) {
            return '&lt;' . $matches[1];
        } // Is the element that we caught naughty? If so, escape it
        elseif (in_array(strtolower($matches['tagName']), $naughty_tags, TRUE)) {
            return '&lt;' . $matches[1] . '&gt;';
        } // For other tags, see if their attributes are "evil" and strip those
        elseif (isset($matches['attributes'])) {
            // We'll store the already filtered attributes here
            $attributes = array();
            // Attribute-catching pattern
            $attributes_pattern = '#'
                . '(?<name>[^\s\042\047>/=]+)' // attribute characters
                // optional attribute-value
                . '(?:\s*=(?<value>[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*)))' // attribute-value separator
                . '#i';
            // Blacklist pattern for evil attribute names
            $is_evil_pattern = '#^(' . implode('|', $evil_attributes) . ')$#i';
            // Each iteration filters a single attribute
            do {
                // Strip any non-alpha characters that may precede an attribute.
                // Browsers often parse these incorrectly and that has been a
                // of numerous XSS issues we've had.
                $matches['attributes'] = preg_replace('#^[^a-z]+#i', '', $matches['attributes']);
                if (!preg_match($attributes_pattern, $matches['attributes'], $attribute, PREG_OFFSET_CAPTURE)) {
                    // No (valid) attribute found? Discard everything else inside the tag
                    break;
                }
                if (
                    // Is it indeed an "evil" attribute?
                    preg_match($is_evil_pattern, $attribute['name'][0])
                    // Or does it have an equals sign, but no value and not quoted? Strip that too!
                    or (trim($attribute['value'][0]) === '')
                ) {
                    $attributes[] = 'xss=removed';
                } else {
                    $attributes[] = $attribute[0][0];
                }
                $matches['attributes'] = substr($matches['attributes'], $attribute[0][1] + strlen($attribute[0][0]));
            } while ($matches['attributes'] !== '');
            $attributes = empty($attributes)
                ? ''
                : ' ' . implode(' ', $attributes);
            return '<' . $matches['slash'] . $matches['tagName'] . $attributes . '>';
        }
        return $matches[0];
    }

    /**
     * Attribute Conversion
     * @param $match
     * @return mixed
     */
    protected function _convert_attribute($match)
    {
        return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
    }
}
