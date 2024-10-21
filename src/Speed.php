<?php

namespace UCSS;

/**
 * The `Speed` class handles performance optimizations and output rewriting 
 * for the UCSS plugin. It manages CSS rewriting, HTML processing, 
 * and integrates with third-party plugins like Yoast SEO and Tribe Events 
 * for specific modifications, such as fixing hreflang tags and handling virtual
 * locations in events. The class also allows for dynamic insertion of custom code 
 * into the head and body of HTML output and offers a mechanism to perform 
 * find-and-replace operations on the HTML. <a href='namespace-UCSS.Speed.html'>Detail</a>
 * 
 * **How to test**
 * - Check that UCSS > Speed Optimisation > Archive Posts works as expected
 * - Check that UCSS > Speed Optimisation > Code Insertion works as expected
 * - Check that UCSS > Speed Optimisation > Find/Replace works as expected
 * - Check the page source has correctly optimised CSS
 * - Check FlyingPress is preloading the correct URLs
 * 
 * - **Assets**
 * - /assets/speed_css/speed_css.js
 * 
 * @package UCSS
 */
class Speed {

    /**
     * Initializes the Speed class by setting up output buffering, CSS optimizations,
     * HTML rewriting, and registering filters and actions for third-party plugins.
     */
    public static function init() {

        //Admin side functions
        if(is_admin()) {
            self::init_admin();
        }
        
        // Initialize CSS and Archive speed optimizations
        Speed\CSS::init();
        
        // Start output buffering and process output
        // Hook to init to ensure it runs before other plugins
        add_action(
            'init',function() {
                ob_start(array(__CLASS__, 'process_output'));
        });

 


    }

    public static function init_admin() {




    }

    /**
     * Processes the final output before it's sent to the browser.
     * This includes rewriting CSS and HTML.
     *
     * @param string $output The buffered output.
     * @return string The modified output.
     */
    public static function process_output($output) {

        $output = Speed\CSS::rewrite_css($output); // Rewrite CSS for performance improvements
        $output = self::rewrite_html($output);     // Rewrite HTML content for additional optimizations
        return $output;

    }

    /**
     * Rewrites the HTML output by inserting custom head and body code, and performs 
     * find-and-replace operations defined in the configuration.
     *
     * @param string $html The HTML output.
     * @return string The modified HTML output.
     */
    public static function rewrite_html($html) {

        // Check if this is an HTML document
        if (!strstr($html, '<html')) {
            return $html;
        }


        return $html;

    }





}
