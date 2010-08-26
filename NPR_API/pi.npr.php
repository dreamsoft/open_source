<?php
//******************************************************************************
// Class Npr
// http://expressionengine.com/docs/development/plugins.html 
//******************************************************************************

// EE Plugin Control Panel Information
$plugin_info = array(
    'pi_name' => 'NPR API',
    'pi_version' => '0.1',
    'pi_author' => "Jerry D'Antonio",
    'pi_author_url' => 'http://www.ideastream.org',
    'pi_description' => 'EE 1.6.x plugin for sending/parsing queries to the NPR API.',
    'pi_usage' => Npr::usage()
);

/**
 * ExpressionEngine plugin for interfacing with the NPR API.
 **/
class Npr
{
    //--------------------------------------------------------------------------
    // ExpressionEngine Data Members
    //--------------------------------------------------------------------------

    public $return_data = '';

    // local pointers to ExpressionEngine global variables
    protected $_DB = NULL;
    protected $_DSP = NULL;
    protected $_FNS = NULL;
    protected $_IN = NULL;
    protected $_LANG = NULL;
    protected $_LOC = NULL;
    protected $_REGX = NULL;
    protected $_SESS = NULL;
    protected $_TMPL = NULL;

    //--------------------------------------------------------------------------
    // cURL Data Members
    //--------------------------------------------------------------------------

    // class constants
    const CURL_TIMEOUT = 10;
    const API_URL = 'http://api.npr.org/';
    const API_QUERY = 'query';
    const API_STATIONS = 'stations';
    const API_TRANSCRIPT = 'transcript';

    // cURL handle
    private $_curl;

    // error variables
    private $_error;
    private $_errno;
    private $_response;
    private $_status;
    
    //--------------------------------------------------------------------------
    // Construction and Destruction
    //--------------------------------------------------------------------------
    
    /**
     * Constructor.
     **/
    function Npr()
    {
        $this->__construct();
    }

    /**
     * Constructor. Maps ExpressionEngine global variables to object
     * member variables. Also initializes the cURL library for network
     * communications.
     **/
    public function __construct()
    {
        global $DB, $DSP, $FNS, $IN, $LANG, $LOC, $REGX, $SESS, $TMPL;

        $this->_DB = $DB;
        $this->_DSP = $DSP;
        $this->_FNS = $FNS;
        $this->_IN = $IN;
        $this->_LANG = $LANG;
        $this->_LOC = $LOC;
        $this->_REGX = $REGX;
        $this->_SESS = $SESS;
        $this->_TMPL = $TMPL;

        $this->_curl = FALSE;
        $this->init();
    }
    
    /**
     * Destructor. Closes cURL connections and releases cURL library.
     **/
    public function __destruct()
    {
        if ($this->is_init()) curl_close($this->_curl);
    }

    //--------------------------------------------------------------------------
    // Operations
    //--------------------------------------------------------------------------

    /**
     * Send a Query request to the NPR API and process the results.
     * 
     * Input parameters are pulled from the ExpressionEngine tag attributes. All
     * API options are valid as tag attributes excelt the 'output' option. There
     * are also a few plugin-specific options available. Refer to the NPR API
     * documentation for complete details. A few of the most notable options are
     * detailed here.
     *
     * Most, but not all, of the output data from the NPR API is accessible
     * within the tag body. Most, but not all, support conditional checks using
     * the 'if' tag. Some of the data elements are accessed through single tags
     * but some require nesting within a tag pair. Most of the data elements
     * within the tag pairs also support conditional processing with the 'if'
     * tag. See the sample tag below for an example of use.
     *
     * The NPR API does support field filtering through the query itself. This
     * tag supports this as well using the requested field values as tag
     * attributes. For most queries it is not necessary to filter output fields
     * in the request since ExpressionEngine template processing can be used to
     * filter the final output. Limiting the number of fields returned by the
     * query will reduce the amount of data transferred and may have an
     * appreciable effect on performace for larger sites, but query caching may
     * have a greater impact.
     *
     * ExpressionEngine supports explicit cachine of tag output for any tag.
     * Including the 'cache' and 'refresh' attributes (see below) in the opening
     * tag and setting the appropriate values will instruct ExpressionEngine to
     * cache the output of the tag processing for a specified time. It is highly
     * recommended that caching be used for most queries since the data returned
     * by NPR is unlilely to change more than once every several minutes and
     * even an hour of caching is unlikely to be noticed by the reader. Caching
     * judiciously will improve the performace of the ExpressionEngine web site
     * and will also reduce the load on the NPR API.
     *
     * The special tag variable 'dump' is provided for use within the template
     * tag for debugging purposes. Dump is a single variable that will dump
     * the raw output of the query into the HTML output formatted as a PHP
     * array. This should not be done in production, only testing.
     *
     * @param apiKey Stations must register with NPR for an API key. The NPR will
     *        not respond to requests without a valid key. This field is required.
     *
     * @param output The output option for normal queries is not supported.
     *        Query output is limited to the template processing.
     *
     * @param cache When set to 'yes' this will cause ExpressionEngine to cache
     *        the output of the template tag processing and use the cached result
     *        for future page loads. This only works in conjunction with the
     *        'refresh' tag parameter. See the ExpressionEngine documentation for
     *        more detail.
     *
     * @param refresh Specifies the number of minutes the cache should be
     *        retained before a new API request is generated. Only works when the
     *        'cache' parameter is used and set to 'yes' to enable caching. See
     *        the ExpressionEngine documentation for more detail.
     * 
     * @link http://www.npr.org/api/queryGenerator.php 
     * @link http://expressionengine.com/legacy_docs/general/caching.html#tag_caching 
     * 
     * {exp:npr:query id="5" startDate="2010-08-11" endDate="2010-08-18" fields="all" numResults="30" apiKey="demo" cache="yes" refresh="60"}
     * 
     * <!--
     * {dump}
     * -->
     * 
     * <h2>{title}</h2>
     * 
     * <ul>
     * <li><strong>ID:</strong> {id}</li>
     * {if title}<li><strong>Title:</strong> {title}</li>{/if}
     * {if subtitle}<li><strong>Subtitle:</strong> {subtitle}</li>{/if}
     * {if shortTitle}<li><strong>Short Title:</strong> {shortTitle}</li>{/if}
     * {if teaser}<li><strong>Teaser:</strong> {teaser}</li>{/if}
     * {if miniTeaser}<li><strong>Mini Teaser:</strong> {miniTeaser}</li>{/if}
     * {if slug}<li><strong>Slug:</strong> {slug}</li>{/if}
     * {if link}<li><strong>Link:</strong> <a href="{link}">{link}</a></li>{/if}
     * {if link}<li><strong>HTML Link:</strong> <a href="{link type="html"}">{link type="html"}</a></li>{/if}
     * {if link}<li><strong>API Link:</strong> <a href="{link type="api"}">{link type="api"}</li></a>{/if}
     * {if link}<li><strong>Short Link:</strong> <a href="{link type="short"}">{link type="short"}</li></a>{/if}
     * {if storyDate}<li><strong>Story Date:</strong> {storyDate format="%F %d %Y"}</li>{/if}
     * {if pubDate}<li><strong>Publication Date:</strong> {pubDate format="%F %d %Y"}</li>{/if}
     * {if lastModifiedDate}<li><strong>Modification Date:</strong> {lastModifiedDate}</li>{/if}
     * {if transcript}<li><strong>Transcript:</strong> {transcript}</li>{/if}
     * </ul>
     * 
     * {if audio}
     *   <h3>Audio:</h3>
     *   <ul>
     *   {audio}
     *     {if id}<li><strong>ID:</strong> {id}</li>{/if}
     *     {if type}<li><strong>Type:</strong> {type}</li>{/if}
     *     {if title}<li><strong>Title:</strong> {title}</li>{/if}
     *     {if duration}<li><strong>Duration:</strong> {duration}</li>{/if}
     *     {if description}<li><strong>Description:</strong> {description}</li>{/if}
     *     {if rightsHolder}<li><strong>Rights Holder:</strong> {rightsHolder}</li>{/if}
     *     {if format}<li><strong>Link:</strong> <a href="{format}">{format}</a></li>{/if}
     *     {if format}<li><strong>MP3 Link:</strong> <a href="{format mp3}">{format mp3}</a></li>{/if}
     *     {if format}<li><strong>WM Link:</strong> <a href="{format wm}">{format wm}</a></li>{/if}
     *     {if format}<li><strong>RM Link:</strong> <a href="{format rm}">{format rm}</a></li>{/if}
     *   {/audio}
     *   </ul>
     * {/if}
     * 
     * {if image}
     *   <h3>Images: </h3>
     *   <ul>
     *   {image}
     *     {if id}<li><strong>ID:</strong> {id}</li>{/if}
     *     {if type}<li><strong>Type:</strong> {type}</li>{/if}
     *     {if width}<li><strong>Width:</strong> {width}</li>{/if}
     *     {if src}<li><strong>Source:</strong> {src}</li>{/if}
     *     {if hasBorder}<li><strong>Has Border:</strong> {hasBorder}</li>{/if}
     *     {if title}<li><strong>Title:</strong> {title}</li>{/if}
     *     {if caption}<li><strong>Caption:</strong> {caption}</li>{/if}
     *     {if producer}<li><strong>Producer:</strong> {producer}</li>{/if}
     *     {if copyright}<li><strong>Copyright:</strong> {copyright}</li>{/if}
     *     {if link}<li><strong>Link:</strong> {link}</li>{/if}
     *     {if provider}<li><strong>Provider:</strong> {provider}</li>{/if}
     *     {if enlargement}<li><strong>Enlargement:</strong> {enlargement}</li>{/if}
     *   {/image}
     *   </ul>
     * 
     *   {image}
     *     {if src}<p><img src="{src}" {if width}width="{width}"{/if} />{/if}
     *     <br />
     *     {if enlargement}<p><img src="{enlargement}" />{/if}
     *   {/image}
     * 
     * {/if}
     * 
     * {if thumbnail}
     *   <h3>Thumbnails:</h3>
     *   <p>
     *   <img src="{thumbnail}" />
     *   <img src="{thumbnail size="medium"}" />
     *   <img src="{thumbnail size="large"}" />
     *   </p>
     * {/if}
     * 
     * {if toenail}
     *   <h3>Toenails:</h3>
     *   <p>
     *   <img src="{toenail}" />
     *   <img src="{toenail size="medium"}" />
     *   <img src="{toenail size="large"}" />
     *   </p>
     * {/if}
     * 
     * {if textWithHtml}
     *   <h3>Text:</h3>
     *   {textWithHtml}
     * {/if}
     * 
     * {/exp:npr:query}
     * 
     * TODO:
     * - show
     * - organization
     * - parent
     * - byline
     **/
    public function query()
    {
        // clear the return data buffer
        $this->return_data = '';

        // send the request
        $raw = $this->send_api_request(self::API_QUERY);
        $data = json_decode($raw, true);

        // loop through the set of stories
        if (is_array($data))
        {
            $count = 1;
            foreach ($data['list']['story'] as $story)
            {
                $tagdata = $this->_TMPL->tagdata;

                // process the story count conditional
                $tagdata = $this->_FNS->prep_conditionals($tagdata, array('count' => $count++));

                // for each story, loop through the set of variable pairs
                foreach ($this->_TMPL->var_pair as $var => $opts)
                {
                    $tagdata = $this->process_story_var_pairs($story, $var, $opts, $tagdata);
                }

                // for each story, loop through the set of single vars
                foreach ($this->_TMPL->var_single as $var => $opts)
                {
                    $tagdata = $this->process_story_var_singles($story, $var, $opts, $tagdata);
                }

                // append this story to the output buffer
                $this->return_data .= $tagdata;
            }
        }

        // return the processed results
        return $this->return_data;
    }

    /**
     * Send a Stations request to the NPR API and process the results.
     * NOT YET IMPLEMENTED
     **/
    public function stations()
    {
        $tagdata = $this->send_api_request(self::API_STATIONS);
        return $tagdata;
    }

    /**
     * Send a Transcript request to the NPR API and process the results.
     * NOT YET IMPLEMENTED
     **/
    public function transcript()
    {
        $tagdata = $this->send_api_request(self::API_TRANSCRIPT);
        return $tagdata;
    }

    //--------------------------------------------------------------------------
    // Template Utilities
    //--------------------------------------------------------------------------

    /**
     * Parse all tag pairs from the body of the parent tag and process them
     * against the relevant data in an NPR API 'story' data element.
     *
     * A regular expression will be used to parse the elements of each tag pair
     * processed. The resulting match array will be in PREG_SET_ORDER so that
     * each element represents one complete match. Each match element will
     * include four sub elements:
     * Array
     * (
     *     [0] => The entire tag pair from the left delimeter of the opening
     *            tag to the right delimeter of the closing tag.
     *     [1] => The full contents of the opening tag (between the delimeters).
     *     [2] => Everyting between the opening and closing tags.
     *     [3] => The full contents of the closing tag (between the delimeters).
     * )
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $opts The EE template options for the $var variable.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     **/
    private function process_story_var_pairs(/*array*/ $story, /*string*/ $var,
        /*string*/ $opts, /*string*/ $tagdata) /*string*/
    {
        /*
            Var: /audio opts="some options" stuff="more stuff"/
            Array ( [opts] => some options [stuff] => more stuff )
        */

        // separate tag name from tag options
        preg_match('/^\S+/', $var, $matches);
        $tag = $matches[0];

        // extract the full tag data for this tag
        $pattern = '/('.LD.$var.RD.')(.*?)('.LD.SLASH.$tag.RD.')/s';
        $count = preg_match_all($pattern, $tagdata, $matches, PREG_SET_ORDER);

        // process all matches
        for ($i = 0; $i < $count; $i++)
        {
            if ($tag == 'audio')
            {
                $tagdata = $this->swap_story_audio_pair($story, $var, $opts, $matches[$i], $tagdata);
            }
            if ($tag == 'image')
            {
                $tagdata = $this->swap_story_image_pair($story, $var, $opts, $matches[$i], $tagdata);
            }
            else
            {
                // remove the tag pair from the tag data
                $tagdata = str_replace($matches[$i][0], '', $tagdata);
            }
        }

        return $tagdata;
    }

    /**
     * Parse all singular tags from the body of the parent tag and process them
     * against the relevant data in an NPR API 'story' data element.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $opts The EE template options for the $var variable.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     **/
    private function process_story_var_singles(/*array*/ $story, /*string*/ $var,
        /*string*/ $opts, /*string*/ $tagdata) /*string*/
    {
        if ($var == 'dump')
        {
            $tagdata = $this->_TMPL->swap_var_single($var, print_r($story, true), $tagdata); 
        }
        else if (preg_match('/^\w+Date/', $var, $matches) > 0)
        {
            // process date variables
            $tagdata = $this->swap_story_date_var($story, $var, $opts, $matches[0], $tagdata);
        }
        else if (preg_match('/^link/', $var, $matches) > 0)
        {
            // process link variables
            $tagdata = $this->swap_story_link_var($story, $var, $matches[0], $tagdata);
        }
        else if (preg_match('/^text/', $var, $matches) > 0)
        {
            // process body text
            $tagdata = $this->swap_story_paragraph_text($story, $var, $matches[0], $tagdata);
        }
        else if (preg_match('/^t(oe|humb)nail/', $var, $matches) > 0)
        {
            // process body text
            $tagdata = $this->swap_story_thumbnail_var($story, $var, $matches[0], $tagdata);
        }
        else if ($var == 'id')
        {
            $tagdata = $this->swap_story_single_var($story, $var, $tagdata);
        }
        else if ($var == 'transcript')
        {
            $tagdata = $this->swap_story_transcript_var($story, $tagdata);
        }
        else
        {
            // process other variables
            $tagdata = $this->swap_story_text_var($story, $var, $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process an 'image' tag pair from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $opts The EE template options for the $var variable.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_image_pair(/*array*/ $story, /*string*/ $var,
        /*array*/ $opts, /*array*/ $match, /*string*/ $tagdata) /*string*/
    {
        // make sure there is image available
        if (! array_key_exists('image', $story) || count($story['image']) == 0)
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array('image' => FALSE));
            return str_replace($match[0], '', $tagdata);
        }

        // process 'if' statements
        $tagdata = $this->_FNS->prep_conditionals($tagdata, array('image' => TRUE));

        // set the loop limit, check for the limit tag attribute
        $count = count($story['image']);
        if (is_array($opts) && array_key_exists('limit', $opts)) {
            $limit = intval($opts['limit']);
            if ($limit != 0 && $limit < $count) $count = $limit;
        }

        // loop through all images attached to story
        $imagedata = '';
        for ($i = 0; $i < $count; $i++)
        {
            $image = $story['image'][$i];

            // grab the body of the tag pair
            $tdata = $match[2];
            
            // process single vars
            $tdata = $this->swap_story_single_var($image, 'id', $tdata);
            $tdata = $this->swap_story_single_var($image, 'type', $tdata);
            $tdata = $this->swap_story_single_var($image, 'width', $tdata);
            $tdata = $this->swap_story_single_var($image, 'src', $tdata);
            $tdata = $this->swap_story_single_var($image, 'hasBorder', $tdata);
            
            // process vars with a single $text element
            $tdata = $this->swap_story_text_var($image, 'title', $tdata);
            $tdata = $this->swap_story_text_var($image, 'caption', $tdata);
            $tdata = $this->swap_story_text_var($image, 'producer', $tdata);
            $tdata = $this->swap_story_text_var($image, 'copyright', $tdata);
            $tdata = $this->swap_story_text_var($image, 'link', $tdata, 'url');
            $tdata = $this->swap_story_text_var($image, 'provider', $tdata, 'url');

            // process enlargement tags
            $tdata = $this->swap_story_text_var($image, 'enlargement', $tdata, 'src');

            // append the tag data
            $imagedata .= $tdata;
        }
        $tagdata = str_replace($match[0], $imagedata, $tagdata);

        return $tagdata;
    }

    /**
     * Process an 'audio' tag pair from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $opts The EE template options for the $var variable.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_audio_pair(/*array*/ $story, /*string*/ $var,
        /*array*/ $opts, /*array*/ $match, /*string*/ $tagdata) /*string*/
    {
        // make sure there is audio available
        if (! array_key_exists('audio', $story) || count($story['audio']) == 0)
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array('audio' => FALSE));
            return str_replace($match[0], '', $tagdata);
        }

        // process 'if' statements
        $tagdata = $this->_FNS->prep_conditionals($tagdata, array('audio' => TRUE));

        // loop through all audio attached to story
        $audiodata = '';
        foreach ($story['audio'] as $audio)
        {
            // grab the body of the tag pair
            $tdata = $match[2];
            
            // process single vars
            $tdata = $this->swap_story_single_var($audio, 'id', $tdata);
            $tdata = $this->swap_story_single_var($audio, 'type', $tdata);
            
            // process vars with a single $text element
            $tdata = $this->swap_story_text_var($audio, 'title', $tdata);
            $tdata = $this->swap_story_text_var($audio, 'duration', $tdata);
            $tdata = $this->swap_story_text_var($audio, 'description', $tdata);
            $tdata = $this->swap_story_text_var($audio, 'rightsHolder', $tdata);

            // process link tags
            $tdata = $this->swap_audio_link_vars($audio, $match, $tdata);

            // append the tag data
            $audiodata .= $tdata;
        }
        $tagdata = str_replace($match[0], $audiodata, $tagdata);

        return $tagdata;
    }

    /**
     * Process a the 'link' elements of an 'audio' tag pair of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $audio One 'audio' element from an NPR API 'story' response.
     * @param $tagmatch An array containing the result of the regex match
     *        of the 'audio' tag pair.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see swap_story_audio_pair 
     * @see process_story_var_pairs 
     **/
    private function swap_audio_link_vars(/*array*/ $audio, /*array*/ $tagmatch, /*string*/ $tagdata) /*string*/
    {
        // process 'if' tags
        $ok = count($audio) > 0;
        $tagdata = $this->_FNS->prep_conditionals($tagdata, array('format' => $ok));

        // grab all link matches from the body
        $pattern = '/'.LD.'(format(\s+(\w+))?)'.RD.'/';
        $count = preg_match_all($pattern, $tagmatch[2], $matches, PREG_SET_ORDER);

        // loop through all matches
        foreach ($matches as $match)
        {
            // get the full variable tag
            $var = $match[1];

            // determine the link format
            $format = (isset($match[3]) ? $match[3] : 'mp3');

            // get the correct audio link from the data
            $link = (isset($audio['format'][$format]['$text']) ? $audio['format'][$format]['$text'] : '');

            // perform the replacement 
            $tagdata = $this->_TMPL->swap_var_single($var, $link, $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process the 'paragraph' data from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_paragraph_text(/*array*/ $story, /*string*/ $var,
        /*string*/ $match, /*string*/ $tagdata) /*string*/
    {
        if (array_key_exists($var, $story))
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => TRUE));

            $data = '';
            foreach($story[$var]['paragraph'] as $paragraph)
            {
                if ($var == 'textWithHtml')
                {
                    $data .= "<p>{$paragraph['$text']}</p>";
                }
                else if ($var == 'text')
                {
                    $data .= "{$paragraph['$text']}\n\n";
                }
            }

            $tagdata = $this->_TMPL->swap_var_single($var, $data, $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => FALSE));
            $tagdata = $this->_TMPL->swap_var_single($var, '', $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process a dat tag from within the body of a story with support for
     * standard ExpressionEngine date formatting.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $opts The EE template options for the $var variable.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_date_var(/*array*/ $story, /*string*/ $var,
        /*string*/ $opts, /*string*/ $match, /*string*/ $tagdata) /*string*/
    {
        if (isset($story[$match]['$text']))
        {
            $data = $story[$match]['$text'];
            if ($var != $opts) $data = $this->_LOC->decode_date($opts, strtotime($data));
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($match => TRUE));
            $tagdata = $this->_TMPL->swap_var_single($var, $data, $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => FALSE));
            $tagdata = $this->_TMPL->swap_var_single($var, '', $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process the 'thumbnail' and 'toenail' data from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_thumbnail_var(/*array*/ $story, /*string*/ $var,
        /*string*/ $match, /*string*/ $tagdata) /*string*/
    {
        // retrieve the link type
        $type = (preg_match('/size="([^"]+)"/', $var, $matches) > 0 ? $matches[1] : 'large');

        // retrieve the data
        $data = (isset($story[$match][$type]['$text']) ? $story[$match][$type]['$text'] : false);

        // process the tag
        if ($data)
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($match => TRUE));
            $tagdata = $this->_TMPL->swap_var_single($var, $data, $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($match => FALSE));
        }

        return $tagdata;
    }

    /**
     * Process the 'link' data from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $match An array containing the result of the regex match operation
     *        performed in the 'process_story_var_pairs' function.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_link_var(/*array*/ $story, /*string*/ $var,
        /*string*/ $match, /*string*/ $tagdata) /*string*/
    {
        // retrieve the link type
        $type = (preg_match('/type="([^"]+)"/', $var, $matches) > 0 ? $matches[1] : 'html');

        // retrieve the data
        $data = false;
        foreach ($story['link'] as $link)
        {
            if ($link['type'] == $type)
            {
                $data = $link['$text'];
                break;
            }
        }

        // process the tag
        if ($data)
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => TRUE));
            $tagdata = $this->_TMPL->swap_var_single($var, $data, $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => FALSE));
            $tagdata = $this->_TMPL->swap_var_single($var, '', $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process the 'transcript' data from within the body of a story.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_transcript_var(/*array*/ $story, /*string*/ $tagdata) /*string*/
    {
        if (isset($story['transcript']['link']['$text']) && $story['transcript']['link']['$text'] != '')
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array('transcript' => TRUE));
            $tagdata = $this->_TMPL->swap_var_single('transcript', $story['transcript']['link']['$text'], $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array('transcript' => FALSE));
            $tagdata = $this->_TMPL->swap_var_single('transcript', '', $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process data from within the body of a story when there is only one
     * data element with one child. This is an idiom the NPR API uses for
     * single-value text data. The named data element is an array with one
     * child with '$text' as the key. Other keys are occasionally used for
     * the child.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $tagdata The EE tag data being processed.
     * @param $key The key of the child element, '$text' by default. 
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_text_var(/*array*/ $story, /*string*/ $var,
        /*string*/ $tagdata, /*string*/ $key = '$text') /*string*/
    {
        if (is_array($story[$var]) && array_key_exists($key, $story[$var]) && $story[$var][$key] != '')
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => TRUE));
            $tagdata = $this->_TMPL->swap_var_single($var, $story[$var][$key], $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => FALSE));
            $tagdata = $this->_TMPL->swap_var_single($var, '', $tagdata);
        }

        return $tagdata;
    }

    /**
     * Process data from within the body of a story when there is a single
     * keyed data element directly below the story root.
     *
     * @pre The JSON result of an NPR API request has been converted to a PHP
     *      array and normal ExpressionEngine template processing has begun.
     *
     * @param $story One 'story' element from an NPR API request.
     * @param $var The EE template variable being processed.
     * @param $tagdata The EE tag data being processed.
     *
     * @return $tagdata updated with the processed data.
     * 
     * @see process_story_var_pairs 
     **/
    private function swap_story_single_var(/*array*/ $story, /*string*/ $var, /*string*/ $tagdata)
    {
        if (isset($story[$var]))
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => TRUE));
            $tagdata = $this->_TMPL->swap_var_single($var, $story[$var], $tagdata);
        }
        else
        {
            $tagdata = $this->_FNS->prep_conditionals($tagdata, array($var => FALSE));
            $tagdata = $this->_TMPL->swap_var_single($var, '', $tagdata);
        }

        return $tagdata;
    }

    //--------------------------------------------------------------------------
    // Messaging Utilities
    //--------------------------------------------------------------------------

    /**
     * Build a query string for an NPR API request. Loops through all tag
     * parameters and build a correctly formatted query string. Sets the output
     * format to JSON regardless of what may be set by tag parameters and
     * ignores tag parameters specific to ExpressionEngine. Makes no attempt
     * to validate the syntactic correctness of the tag parameters. 'Garbage in,
     * garbage out' applies.
     *
     * @return The complete query string including the staring '?' character.
     **/
    private function build_query_string() /*string*/
    {
        // query string buffer
        $qs = '?output=JSON';

        // array of options to skip
        $skip = array('output', 'cache', 'refresh');

        // loop through all params and add to query string
        foreach ($this->_TMPL->tagparams as $key => $value)
        {
            if (! in_array($key, $skip))
            {
                $qs .= '&' . $key . '=' . preg_replace('/%2C/i', ',', urlencode(preg_replace('/\s*,\s*/', ',', $value)));
            }
        }

        return $qs;
    }

    /**
     * Send a network request to the NPR API using the cURL library and store
     * the results for processing.
     *
     * @return The data sent from the NPR API on success or the cURL error
     *         message on failure.
     **/
    private function send_api_request(/*stirng*/ $action) /*string*/
    {
        // string to return to template processor
        $tagdata = '';

        // send the request
        $url = self::API_URL . $action . $this->build_query_string();
        $this->setopt(CURLOPT_HTTPGET, TRUE);
        $this->setopt(CURLOPT_URL, $url);
        $ok = $this->exec();

        // process the response
        if ($ok)
        {
            $tagdata = $this->_response;
        }
        else
        {
            $tagdata = $this->_error;
        }

        return $tagdata;
    }
     
    //---------------------------------------------------------------------------
    // cURL Operations
    //---------------------------------------------------------------------------
    
    /**
     * Set the internal data members to valued representing a bad handle error.
     **/
    private function set_bad_handle() /*void*/ {
        $this->_response = '';
        $this->_errno = CURLM_BAD_HANDLE;
        $this->_error = 'The passed-in handle is not a valid cURL handle.';
        $this->_status = 0;
    }

    /**
     * Has the cURL library been correctly initialized?
     *
     * @return True if initialized else false.
     **/
    public function is_init() /*bool*/ {
        return $this->_curl !== FALSE;
    }

    /**
     * Close the current cURL handle and initialize the object anew.
     *
     * @see close
     * @see init
     *
     * @return True if initialization is successful else false.
     **/
    private function reset() /*bool*/ {
        $this->close();
        return $this->init();
    }
    
    /**
     * Initilize the internal cURL handle. Will not do anything if the object
     * has already been initialized.
     *
     * @return True if initialization is successful else false.
     **/
    private function init() /*bool*/ {
    
        if (! $this->is_init()) {
    
            // initialize a new session
            $this->_curl = curl_init();
    
            // set common options on success
            if ($this->_curl !== FALSE) {
                curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($this->_curl, CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);
            }
            else
            {
                $this->_response = '';
                $this->_errno = CURLE_FAILED_INIT;
                $this->_error = 'Failed to initialize the cURL session.';
                $this->_status = 0;
            }
        }
    
        // return a boolean
        return $this->is_init();
    }
    
    /**
     * Close the internal cURL handle and "zero" all internal data values.
     **/
    private function close() /*void*/ {
    
        // close an open session if it exists
        if ($this->is_init()) {
            curl_close($this->_curl);
            $this->_curl = FALSE;
        }
    
        // reset the error variables
        $this->_error = '';
        $this->_errno = CURLE_OK;
        $this->_response = '';
        $this->_status = 0;
    }
    
    /**
     * Set a cURL option.
     *
     * @link http://www.php.net/manual/en/function.curl-setopt.php 
     *
     * @param $option The defined constant for the option being set.
     * @param $value The value the option is to be set to.
     *
     * @return TRUE if successful else FALSE.
     **/
    private function setopt(/*int*/ $option, /*mixed*/ $value) /*BOOL*/ {
        if ($this->_curl) {
            return curl_setopt($this->_curl, (int)$option, $value);
        } else {
            return FALSE;
        }
    }
    
    /**
     * Convenience override of the underlying curl_getinfo method.
     *
     * @link http://www.php.net/manual/en/function.curl-getinfo.php 
     *
     * @param $opt The specific option to be retrieved or null for all options.
     *
     * @return A string when $opt is not null else an associative array.
     **/
    private function getinfo(/*int*/ $opt = null) /*mixes*/ {
        if (! $this->is_init()) {
            return null;
        } else if ($opt == null) {
            return curl_getinfo($this->_curl);
        } else {
            return curl_getinfo($this->_curl, $opt);
        }
    }
    
    /**
     * Execute the cURL command with the given parameters.
     *
     * @return true on success else false.
     **/
    private function exec() /*bool*/ {
        if ($this->_curl) {
            $this->_response = curl_exec($this->_curl);
            $this->_errno = curl_errno($this->_curl);
            $this->_error = curl_error($this->_curl);
            $this->_status = curl_getinfo($this->_curl, CURLINFO_HTTP_CODE);
        } else {
            $this->set_bad_handle();
        }
        return $this->_errno == CURLE_OK;
    }
   
    //--------------------------------------------------------------------------
    // Plugin Usage
    //--------------------------------------------------------------------------
    
    function usage()
    {
        ob_start(); 
?>
This is a standard plugin for ExpressionEngine 1.6.x content management system.
It has not been tested under ExpressionEngine 2.x at all. Use with
ExpressionEngine 2.x is NOT RECOMMENDED. Installation is according to the
plugin installation instructions in the ExpressionEngine User Guide:
[Using Plugins] (http://expressionengine.com/legacy_docs/templates/plugins.html).

Once installed, this plugin provides three additional template tags:

* {exp:npr:query}
* {exp:npr:station}
* {exp:npr:transcript}

These tags correspond to the three functions of the same names provided by the
NPR API.
<?php
        $buffer = ob_get_contents();        
        ob_end_clean(); 
        return $buffer;
    }
    // END
}
?>
<?php
//******************************************************************************
// @author Jerry D'Antonio
// @see http://www.ideastream.org
// @copyright Copyright (c) ideastream
// @license http://www.opensource.org/licenses/mit-license.php
//******************************************************************************
// Copyright (c) ideastream
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
//******************************************************************************
?>
