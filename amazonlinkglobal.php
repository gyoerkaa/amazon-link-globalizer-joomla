 <?php
 /** 
  * 
  * @link http://a-fwd.com
  * @version 1.3
  * 
  * @license GPL v2.0
  * @copyright 2013 Woboq UG (haftungsbeschraenkt)
  * @author Attila Gyoerkoes <gyoerkaa@outlook.com>
  * @author Markus Goetz <markus@woboq.com>
  * 
  */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

class plgSystemAmazonLinkGlobal extends JPlugin {

    private $afwd_tlds = array('com', 'ca', 'uk', 'de', 'fr', 'it', 'es', 'jp', 'cn', 'in', 'br', 'au', 'mx');

    /**
     * Regular expression
     * matches: <a (someattributes) href="(someurl)" (somemoreattributes)>
     *
     * @var string
     * @see onAfterRender
     */
    const link_pattern = '#<a\s*([^>]*)\s*href\s*=\s*"([^"]*)"\s*([^>]*)\s*>#';

    /**
     * Regular expression
     * matches URLs to amazon.com containing an asin
     * 
     * @var string
     * @see link_replacer  
     */
    const amzn_asin_pattern  = '#(?:http:\/\/)?(?:www\.)?(?:(?:amazon\.com/(?:[\w-&%]+\/)?(?:o\/ASIN|dp|ASIN|gp\/product|exec\/obidos\/ASIN)\/)|(?:amzn\.com\/))([A-Z0-9]{10})(?:[^"]+)?#';

    /**
     * Regular expression
     * matches URLs to amazon.com containing keywords
     *
     * @var string 
     * @see link_replacer 
     */
    const amzn_keyw_pattern = '#(?:http:\/\/)?(?:www\.)?(?:amazon\.)(?:com\/)(?:(?:gp\/search\/)|(?:s\/))(?:[^"]*)(?:keywords=)([^"&]*)(?:[^"]*)?#';
    
    function plgSystemAmazonLinkGlobal(& $subject, $config){
        parent::__construct($subject, $config);
    }

    /**
     * Callback for preg_replace_callback. If an URL is
     * pointing to amazon.com and containing an asin it will be
     * replaced with a link to a-fwd.com
     *
     * @param preg_replace_callback will be supplying parameters
     * @return string new URL pointing to a-fwd.com
     * @see link_replacer
     */
    private function asin_url_replacer($match) {
        $asin = $match[1];
        $new_url = 'http://a-fwd.com/asin-com='.$asin;
        // Append tracking ids for every country specified
        foreach ($this->afwd_tlds as $tld) {
            $tid = $this->params->get('amzn_tid_'.$tld, '');
            if ($tid && strlen($tid) > 0)
                $new_url = $new_url.'&'.$tld.'='.urlencode($tid);
        }
        return $new_url;
    }

    /**
     * Callback for preg_replace_callback. If an URL is
     * pointing to amazon.com and containing keywords it will be
     * replaced with a link to a-fwd.com
     *
     * @param preg_replace_callback will be supplying parameters
     * @return string new URL pointing to a-fwd.com
     * @see link_replacer
     */
    private function keyw_url_replacer($match) {
        $keywords = $match[1];
        $new_url = 'http://a-fwd.com/s='.$keywords;
        // Append tracking ids for every country specified
        foreach ($this->afwd_tlds as $tld) {
            $tid = $this->params->get('amzn_tid_'.$tld, '');
            if ($tid && strlen($tid) > 0)
                $new_url = $new_url.'&'.$tld.'='.urlencode($tid);
        }
        return $new_url;
    }

    /**
     * Callback for preg_replace_callback. Tries to replace html anchors
     * containing amazon URLs with anchors containing URLs to the
     * a-fwd.com webservice
     * 
     * @param anchor with an URL pointing to amazon.com
     * @return string new achnor with an URL pointing to a-fwd.com
     */
    private function link_replacer($match) {
        $attributes1    = $match[1];
        $url            = $match[2];
        $attributes2    = $match[3];
        $add_url_params = '';
        $found_matches = 0; // count matches
        
        // Try replacing asin links
        if ($this->params->get('enabled_asin_repl', 1) == 1) {
            $url = preg_replace_callback(self::amzn_asin_pattern, 
                                        Array($this, 'asin_url_replacer'), 
                                        $url,
                                        -1,
                                        $found_matches);
        }
        // Try replacing keyword links
        if ( ($found_matches <= 0) && 
             ($this->params->get('enabled_keyw_repl', 1) == 1) ) {
            $url = preg_replace_callback(self::amzn_keyw_pattern, 
                                         Array($this, 'keyw_url_replacer'), 
                                         $url,
                                         -1,
                                         $found_matches);
        }
        // Build link only if replacements were made
        if ($found_matches > 0) {
            // We don't want search engines going there
            // Change 'rel' attribute to 'nofollow'
            $attributes1 = preg_replace('#rel\s*=\s*"[^"]+"#',
                                        'rel="nofollow"',
                                         $attributes1,
                                         -1,
                                         $found_matches);
            if ($found_matches <= 0) {
                $attributes2 = preg_replace('#rel\s*=\s*"[^"]+"#',
                                            'rel="nofollow"',
                                            $attributes2,
                                            -1,
                                            $found_matches);
            }
            // No 'rel' attribute found, append one
            if ($found_matches <= 0) {
                $attributes2 .= ' rel="nofollow"';
            }
            // Additional url parameters
            if ($this->params->get('url_fallback', 1) != '') {
                $add_url_params .= '&fb='.$this->params->get('url_fallback', 1);
            }
            if ($this->params->get('url_ascsubtag') != '') {
                $add_url_params .= '&ascsubtag='.$this->params->get('url_ascsubtag', 1);
            }
            if ($this->params->get('url_custom') != '') {
                $add_url_params .= $this->params->get('url_custom', 1);
            }
            $add_url_params .= '&sc=j';
            
            // Build the actual link
            $new_link = '<a '.$attributes1.' href="'.$url.$add_url_params.'" '.$attributes2.'>';
            return $new_link;
        }
        return $match[0];
    }

    public function onAfterRender()
    {
        // Don't run this in the backend
        $appCms = JFactory::getApplication();
        if($appCms->isAdmin()) {
            return;
        }
        // Process whole html body
        $body = $appCms->getBody();
        $body = preg_replace_callback(self::link_pattern,
                                      Array($this, 'link_replacer'),
                                      $body);
        if ($body != NULL) {
            $appCms->setBody($body);
        }
        
        return;
    }
}
