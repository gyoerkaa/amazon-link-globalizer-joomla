 <?php
 /** 
 * @version 1.0
 * @copyright 2013 Woboq UG (haftungsbeschraenkt)
 * @license GPL v2.0
 * @author Attila Gyoerkoes <gyoerkaa@outlook.com>, Markus Goetz <markus@woboq.com>
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

class plgSystemAmazonLinkGlobal extends JPlugin {
    
    private $afwd_tlds = array('com', 'ca', 'uk', 'de', 'fr', 'es', 'it', 'cn', 'jp');
    
    // matches links: <a (someattributes) href="(someurl)" (somemoreattributes)>
    const link_pattern = '#<a\s*([^>]*)\s*href\s*=\s*"([^"]*)"\s*([^>]*)\s*>#';
    const amzn_asin_pattern  = '#(?:http:\/\/)?(?:www\.)?(?:(?:amazon\.com/(?:[\w-&%]+\/)?(?:o\/ASIN|dp|ASIN|gp\/product|exec\/obidos\/ASIN)\/)|(?:amzn\.com\/))([A-Z0-9]{10})(?:[^"]+)?#';
    const amzn_keyw_pattern = '#(?:http:\/\/)?(?:www\.)?(?:amazon\.)(?:com\/)(?:(?:gp\/search\/)|(?:s\/))(?:[^"]*)(?:keywords=)([^"&]*)(?:[^"]*)?#';
     
    function plgSystemAmazonLinkGlobal(&$subject, $config) {
        parent::__construct($subject, $config);    
    }
    
    private function asin_url_replacer($match) {
        $asin = $match[1];       
        $new_url = 'http://a-fwd.com/asin-com='.$asin;
        foreach ($this->afwd_tlds as $tld) {           
            $tid = $this->params->get('amzn_tid_'.$tld, '');
            if ($tid && strlen($tid) > 0)
                $new_url = $new_url.'&'.$tld.'='.urlencode($tid);
        } 
        return $new_url;
    }
    
    private function keyw_url_replacer($match) {
        $keywords = $match[1];       
        $new_url = 'http://a-fwd.com/s='.$keywords;
        foreach ($this->afwd_tlds as $tld) {           
            $tid = $this->params->get('amzn_tid_'.$tld, '');
            if ($tid && strlen($tid) > 0)
                $new_url = $new_url.'&'.$tld.'='.urlencode($tid);
        } 
        return $new_url;
    }    
    
    private function link_replacer($match) {       
        $attributes1 = $match[1];
        $url         = $match[2];
        $attributes2 = $match[3];               
        $found_matches = 0; // count matches
        
        // try replacement for asin links
        $url = preg_replace_callback(self::amzn_asin_pattern, 
                                     Array($this, 'asin_url_replacer'), 
                                     $url,
                                     -1,
                                     $found_matches);
        // try replacement for keyword links
        if ($found_matches <= 0) {
            $url = preg_replace_callback(self::amzn_keyw_pattern, 
                                         Array($this, 'keyw_url_replacer'), 
                                         $url,
                                         -1,
                                         $found_matches);
        }
        // Build link, but don't do anything, if no replacements were made
        if ($found_matches > 0) {            
            // change 'rel' attribute to 'nofollow'
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
            // no 'rel' attribute found, append one
            if ($found_matches <= 0) {
                $attributes2 = $attributes2.' rel="nofollow"';
            }
            // Build the actual link            
            $new_link = '<a '.$attributes1.' href="'.$url.'" '.$attributes2.'>';
            return $new_link;
        }           
        return $match[0];    
    }

    public function onAfterRender()
    {
        // don't run this in the backend
        $app = JFactory::getApplication();
        if($app->isAdmin()) {
            return;
        }
        // process whole html body
        $body = JResponse::getBody();
        $body = preg_replace_callback(self::link_pattern, 
                                      Array($this, 'link_replacer'), 
                                      $body);                
        if ($body != NULL) {
            JResponse::setBody($body);
        }
        return;
    }   
  
}