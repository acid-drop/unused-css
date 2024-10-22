<?php

namespace UCSS\Speed;

use UCSS\App\Config;

use Wa72\Url\Url;
use MatthiasMullie\Minify;

/**
 * This `CSS` class is responsible for optimizing and managing CSS files for faster 
 * page load times. It caches processed CSS files, rewrites URLs, and integrates 
 * with the WordPress enqueue system to serve optimized CSS assets.
 * 
 * @package UCSS
 */
class CSS {

    //The directory where unused CSS will be stored
    //will be a subdirectory of wp-content/cache
    public static $cache_directory = "acd-unused-css";

    //The hostname of the current site
    public static $hostname; 

    //Enabled, previewm stats only or disabled
    public static $mode;

    //The default include patterns for the unused CSS script
    public static $default_include_patterns = array('^\.hover','\.dropdown');


	public static function init() {

        //Set the hostname
        self::$hostname = parse_url(site_url(), PHP_URL_HOST);

        //Set the mode
        self::$mode = Config::get('speed_css','css_mode');

        //Enqueue the script for our CSS processing 
        if((self::$mode == "enabled" || self::$mode == "stats" || self::$mode == "preview") && !is_admin()) {
            add_action( 'wp_enqueue_scripts', array(__CLASS__,'public_enqueue_scripts') );
        }


	}

    /**
     * Processes an array of CSS files and saves them to a cache directory.
     * 
     * This function takes an array of CSS files and their corresponding URLs, 
     * rewrites absolute URLs, saves the processed CSS files to a cache directory. 
     * It also creates a lookup file to map original filenames to new filenames 
     * 
     * @param array $array An array of CSS files and their corresponding URLs.
     * @param string $source_url The URL of the source CSS file.
     * @param int $post_id The id of the post if available
     * @param array $post_types An array of post types
     * @return void
     */
    public static function process_css( $array, $source_url, $post_id = null, $post_types = null ) {

        //A lookup of old to new filesname
        $lookup = array();

        //Get cache directory for the URL
        $cache_dir =  self::get_cache_path_from_url($source_url);

        // Create the cache directory if it does not exist
        !is_dir($cache_dir) && mkdir($cache_dir, 0755, true);        

        //Get array of the CSS urls that we're processing
        $urls = array_keys((array)$array);

        //Run through the array
        foreach($array AS $url=>$csstxt) {

            //Minify it
            $minifier = new Minify\CSS($csstxt);
            $csstxt = $minifier->minify();

            //Rewrite absolutes
            $csstxt = self::rewrite_absolute_urls($csstxt, $url);
            
            //Save to cache            
            $original_filename = ($url);
            $new_filename = md5($csstxt) . ".css";            

            //Get path
            $cache_file_path = self::get_root_cache_path() . "/". $new_filename;

            //Save to root path
            if(!file_exists($cache_file_path)) {
                file_put_contents($cache_file_path, $csstxt);                             
            }

            //Create lookup
            $lookup[$original_filename] = $new_filename;
            

        }

        //Create the data array to save
        $data = array(
            'lookup' => $lookup,
            'source_url' => $source_url,
            'post_id' => $post_id,
            'post_types' => $post_types
        );

        //Save the lookup
        file_put_contents($cache_dir . "lookup.json", (string)json_encode($data));  

        //Refresh cache
        if($post_id) {
            $post_object = get_post( $post_id );

            self::remove_non_cache_purge_clean_hooks_from_post_updated();
            do_action( 'post_updated', (int) $post_id, $post_object, $post_object );
        }

    }    


    /**
     * Removes non-cache/purge/clean hooks from the 'post_updated' action.
     *
     * This function is used internally by the CSS class to remove hooks that
     * are not related to cache, purge, or clean operations when the
     * 'post_updated' action is triggered.
     *
     */
    private static function remove_non_cache_purge_clean_hooks_from_post_updated() {
        global $wp_filter;
    
        // Define the regex pattern to match 'cache', 'purge', or 'clean'
        $pattern = '/cache|purge|clean/i'; // Case-insensitive pattern
    
        // Check if 'post_updated' action exists
        if (isset($wp_filter['post_updated'])) {
            $hooks = $wp_filter['post_updated'];
    
            // Loop through each priority level
            foreach ($hooks->callbacks as $priority => $functions) {
                foreach ($functions as $hook_key => $function) {
                    // Determine the function name
                    $function_name = '';
    
                    if (is_string($function['function'])) {
                        // Simple function
                        $function_name = $function['function'];
                    } elseif (is_array($function['function'])) {
                        // Class method
                        $class_name = is_object($function['function'][0]) ? get_class($function['function'][0]) : $function['function'][0];
                        $method_name = $function['function'][1];
                        $function_name = $class_name . '::' . $method_name;
                    }
    
                    // Use regex to check if the function name matches the pattern 'cache', 'purge', or 'clean'
                    if (!preg_match($pattern, $function_name)) {
                        // If it doesn't match the pattern, remove the action
                        remove_action('post_updated', $function['function'], $priority);
                    }
                }
            }
        }
    }


    /**
     * Replaces CSS links in the provided HTML output with optimized versions 
     * from the lookup file.
     *
     * @param string $output The HTML output to optimize.
     * @return string The optimized HTML output.
     */
    public static function rewrite_css($output) {

        //Don't run if we're disabled or stats only
        if(self::$mode == "disabled") {
            return $output;
        }

        if(self::$mode == "preview" && !current_user_can( 'manage_options' )) {
            return $output;
        }

        $current_url = $_SERVER['REQUEST_URI'];
        if(function_exists('weglot_get_current_full_url')) {
            $current_url = weglot_get_current_full_url();
        }

        //See if there is a lookup file
        $lookup_file = self::get_lookup_file( $current_url );
        if(!file_exists($lookup_file)) {
            return $output;
        }

        //Check if we have a lookup file
        $lookup = json_decode((string)file_get_contents($lookup_file));
        $lookup = $lookup->lookup;
        
        if(is_object($lookup)) {
                        
            //Get the sheets from the output
            $sheets = self::get_stylesheets($output);      
            
            //Replace with lookup file
            foreach($sheets[0] AS $key => $tag) {
                
                if(!isset($sheets[1][$key])) {
                    continue;
                }

                $sheet_url = $sheets[1][$key];

                //Make URL absolute
                $baseurl = Url::parse($sheet_url);
                $sheet_url_lookup = $baseurl->makeAbsolute($baseurl)->write();  
                $sheet_url_lookup = preg_replace("@^//@", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://', trim($sheet_url_lookup));

                if(isset($lookup->$sheet_url_lookup)) {   

                    if(self::$mode == "stats") {
                        
                        $new_tag = str_replace("<link ","<link data-ucss-processed='true' ",$tag);                        

                    } else {

                        if($lookup->$sheet_url_lookup == md5("").".css") {  //Blank files
                            $new_tag = "";
                        } else {
                            $new_tag = str_replace($sheet_url,self::get_root_cache_url() . "/" . $lookup->$sheet_url_lookup,$tag);//
                        }

                    }
                    $output = str_replace($tag,$new_tag,$output);

                }                 

            }


        }

        return $output;

    }


    /**
     * Enqueues the public scripts for the Speed CSS plugin.
     *
     * This function is hooked into the 'wp_enqueue_scripts' action and is 
     * responsible for enqueuing the plugin's JavaScript file and localizing 
     * the script with the cache directory.
     *
     * @return void
     */
    public static function public_enqueue_scripts() {

        //Get include patterns
        $default_patterns = self::$default_include_patterns;
        $include_patterns = Config::get('speed_css','include_patterns');       
        if($include_patterns) {
            $include_patterns_array = explode("\n",$include_patterns);
        } else {
            $include_patterns_array = $default_patterns;
        }
        $include_pattern = json_encode($include_patterns_array);

        // Enqueue our js script.
        wp_enqueue_script( 'speed-css', UCSS_PLUGIN_URL . 'assets/speed_css/speed_css.min.js', array( 'jquery' ), UCSS_VER, true );

		wp_localize_script(
			'speed-css',
			'speed_css_vars',
			array(
				'cache_directory' => self::$cache_directory,
                'include_patterns' => $include_pattern
			)
		);        


    }

    /**
     * Retrieves an array of stylesheets from the provided HTML.
     *
     * This function uses a regular expression to match all HTML link tags 
     * with a rel attribute set to 'stylesheet' and extracts their href attribute 
     * values.
     *
     * @param string $html The HTML string to parse for stylesheets.
     * @return array An array of stylesheets, where [0] is an array of tags and [1] is an array of URLs.
     */
    public static function get_stylesheets($html) {

        // run preg match all to grab all the tags
        $pattern = '/<link[^>]*\srel=[\'"]stylesheet[\'"][^>]*\shref=[\'"]([^\'"]+)[\'"][^>]*>/i';
        preg_match_all($pattern, $html, $stylesheets);

        if(isset($stylesheets[0])) {
            
            return $stylesheets;

        } else {

            return array();

        }



    }

    /**
     * Retrieves the path to the lookup file for the given URL.
     *
     * @param string $url The URL for which to retrieve the lookup file path.
     * @return string The path to the lookup file.
     */
    public static function get_lookup_file($url) {
        
        $file = self::get_cache_path_from_url($url) . "lookup.json";
        return $file;


    }

    /**
     * Retrieves the cache path for a given URL.
     *
     * @param string $url The URL for which to retrieve the cache path.
     * @return string The cache path for the given URL.
     */
    public static function get_cache_path_from_url($url) {

      $file_relative_path = parse_url($url, PHP_URL_PATH);        
      $file_path = self::get_root_cache_path()  . $file_relative_path;
      return $file_path;

    }    

    /**
     * Retrieves the root cache path.
     *
     * @return string The root cache path.
     */
    public static function get_root_cache_path() {

        return ABSPATH . "wp-content/cache/".self::$cache_directory."/" . self::$hostname;

    }

    /**
     * Retrieves the root cache URL.
     *
     * @return string The root cache URL.
     */
    public static function get_root_cache_url() {

        return site_url() . "/wp-content/cache/".self::$cache_directory."/" . self::$hostname;

    }

    /**
     * Replaces relative URLs in the provided content with absolute URLs.
     *
     * @param string $content The content to replace relative URLs in.
     * @param string $base_url The base URL to use for absolute URLs.
     * @return string The content with relative URLs replaced.
     */
    private static function rewrite_absolute_urls($content, $base_url)    {

      $regex = '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)|@import\s+[\'"]([^\'"]+\.[^\s]+)[\'"]/';
  
      $content = preg_replace_callback(
        $regex,
        function ($match) use ($base_url) {
          // Remove empty values
          $match = array_values(array_filter($match));
          $url_string = $match[0];
          $relative_url = $match[1];
          $absolute_url = Url::parse($relative_url);
          $absolute_url->makeAbsolute(Url::parse($base_url));
          return str_replace($relative_url, $absolute_url, $url_string);
        },
        $content
      );
  
      return $content;

    }    

    /**
     * Retrieves the relative path of a given path from the root cache directory.
     *
     * @param string $path The path to retrieve the relative path for.
     * @return string The relative path.
     */
    public static function get_relative_path($path) {

        $dir = self::get_root_cache_path();

        //Get the relative path of where we are
        $relativePath = str_replace($dir, '', $path);
        $relativePath = trim($relativePath, '/');
        if($relativePath === '') {
            $relativePath = '/';
        }

        return $relativePath;

    }

    /**
     * Retrieves an array of post types from an array of strings.
     *
     * Removes any post types that are prefixed with 'home-' or contain 'template', 'id', 'child', or 'parent'.
     *
     * @param string|array $types The post types to filter.
     * @return array An array of post types.
     */
    public static function get_posttypes($types) {

        $types = (array)$types;

        foreach($types AS $key=>$value) {

            if(preg_match("@home|(-(template|id|child|parent))@",$value)) {
                unset($types[$key]);
            }

        }

        return array_values($types);

    }

    /**
     * Retrieves an array of arrays containing data about each folder in the cache.
     *
     * The outer array is keyed by the relative path of the folder within the cache.
     * Each value in the outer array is itself an array with the following keys:
     *
     * - `post_types`: An array of post types.
     * - `post_id`: The ID of the post.
     * - `empty`: An array of plugin names as keys and the number of empty CSS files
     *            generated by that plugin as the value.
     * - `used`: An array of plugin names as keys and the number of non-empty CSS files
     *           generated by that plugin as the value.
     *
     * @return array An array of arrays containing data about each folder in the cache.
     */
    public static function get_master_data() {

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::get_root_cache_path()));
        $pluginStats = [];
        $urlGroups = [];
        $totalPages = 0;

        //Run through the folders and gather data
        $master_data = array();
        foreach ($iterator as $file) {

            //Find the lookup files
            if ($file->isFile() && $file->getFilename() === 'lookup.json') {

                $allData = json_decode(file_get_contents($file->getPathname()), true);

                //Get the root path
                $post_types = (array)self::get_posttypes($allData['post_types']);
                $rootPath =  self::get_relative_path($file->getPath());

                if(in_array("single",$post_types)) {
                    $rootPath = str_replace(basename($rootPath),"{slug}",$rootPath);
                }

                $master_data[$rootPath] = array();
                $master_data[$rootPath]['post_types'] = $post_types;               
                $master_data[$rootPath]['post_id'] = $allData['post_id'];                

                //Get the lookup data
                $lookupData = $allData['lookup'] ?? [];

                foreach ($lookupData as $url => $cssFile) {

                    //Get the plugin name from the URL
                    $plugin = self::get_plugin_from_url($url);
                    if($plugin == "unknown") {
                        continue;
                    }

                    //This one is empty! The CSS didn't match any selectors
                    if (basename($cssFile) === md5("") . ".css") {

                        if(isset($master_data[$rootPath]['empty'][$plugin])) {
                            $master_data[$rootPath]['empty'][$plugin]++;
                        } else {
                            $master_data[$rootPath]['empty'][$plugin] = 1;
                        }

                    } else {
                        
                        if(isset($master_data[$rootPath]['used'][$plugin])) {
                            $master_data[$rootPath]['used'][$plugin]++;
                        } else {
                            $master_data[$rootPath]['used'][$plugin] = 1;
                        }                        

                    }

                }
                
            }

        }

        ksort($master_data);

        return  $master_data;

    }

    /**
     * Retrieves a list of all plugin names (directories) in the WordPress plugins directory.
     * 
     * @return array A list of plugin names (directories) in the WordPress plugins directory.
     */
    public static function get_all_plugins() {
        $pluginDirectory = WP_PLUGIN_DIR;
        $plugins = [];
    
        if (is_dir($pluginDirectory)) {
            // Open the directory and iterate through its contents
            $pluginDir = new \DirectoryIterator($pluginDirectory);
            foreach ($pluginDir as $fileinfo) {
                if ($fileinfo->isDir() && !$fileinfo->isDot() && substr($fileinfo->getFilename(), 0, 1) !== '_') {
                    // Assume each directory is a plugin, add the directory name to the plugins array
                    $dirname = $fileinfo->getFilename();
                    if(substr($dirname,0,1)!=".") {
                        $plugins[] = $dirname;
                    }
                }
            }
        }
    
        return $plugins; // Return the list of all plugin names
    }
    

    /**
     * Reorganizes the master data by path and adds counts of empty and non-empty
     * CSS files per path.
     *
     * @param array $master_data The master data to reorganize.
     *
     * @return array The reorganized data.
     */
    private static function get_by_path($master_data) {

        $plugin_data = array();

        foreach($master_data AS $path=>$data) {

            ksort($data['used']);
            ksort($data['empty']);

            $plugin_data[$path] = array("sortkey"=>$path,
                                   "found_urls"=>$data['used'],
                                   "empty_urls"=>$data['empty'],
                                   'empty_css_count' => count($data['empty']),
                                   'found_css_count' => count($data['used']),
                                    );

        }

        return $plugin_data;

    }

    /**
     * Reorganizes the master data by plugin and adds counts of empty and non-empty
     * CSS files per plugin.
     *
     * @param array $master_data The master data to reorganize.
     *
     * @return array The reorganized data with the structure:
     *     [
     *         <plugin_name> => [
     *             'sortkey'      => <plugin_name>,
     *             'empty_css_count' => <int>,
     *             'found_css_count' => <int>,
     *             'empty_urls'     => [<path> => <int>, ...],
     *             'found_urls'     => [<path> => <int>, ...],
     *         ],
     *         ...
     *     ]
     */
    private static function get_by_plugin($master_data) {

   
        $plugin_data = array();

        foreach($master_data AS $path=>$data) {

            foreach($data['used'] AS $plugin=>$count) {

                if(!isset($plugin_data[$plugin])) {
                    $plugin_data[$plugin] = array('sortkey' => $plugin,
                                                 'empty_css_count' => 0,
                                                'found_css_count' => 0,
                                                'empty_urls' => [],
                                                'found_urls' => []
                                                );
                }
                
                $plugin_data[$plugin]['found_css_count']++;
                $plugin_data[$plugin]['found_urls'][$path] = $count;


            }

            foreach($data['empty'] AS $plugin=>$count) {

                if(!isset($plugin_data[$plugin])) {
                    $plugin_data[$plugin] = array('sortkey' => $plugin,
                                                'empty_css_count' => 0,
                                                'found_css_count' => 0,
                                                'empty_urls' => [],
                                                'found_urls' => []
                                                );
                }
                
                $plugin_data[$plugin]['empty_css_count']++;
                $plugin_data[$plugin]['empty_urls'][$path] = $count;


            }            


        }

        //Add in empty plugins
        $all_plugins = self::get_all_plugins();
        foreach($all_plugins AS $plugin) {
            if(!isset($plugin_data[$plugin])) {
                $plugin_data[$plugin] = array('sortkey' => $plugin,
                                              'empty_css_count' => 0,
                                              'found_css_count' => 0,
                                              'empty_urls' => [],
                                              'found_urls' => []
                                              );
            }
        }

        ksort($plugin_data);

        return $plugin_data;

    }    

    /**
     * Reorganizes the master data by post type and adds counts of empty and non-empty
     * CSS files per post type.
     *
     * @param array $master_data The master data to reorganize.
     *
     * @return array The reorganized data with the structure:
     *     [
     *         <post_type> => [
     *             'sortkey'      => <post_type>,
     *             'empty_css_count' => <int>,
     *             'found_css_count' => <int>,
     *             'empty_urls'     => [<path> => <int>, ...],
     *             'found_urls'     => [<path> => <int>, ...],
     *         ],
     *         ...
     *     ]
     */
    private static function get_by_posttype($master_data) {
        
        $plugin_data = array();

        foreach($master_data AS $path=>$data) {

            $post_types = $data['post_types'];
            foreach($post_types AS $type) {

                if(!isset($plugin_data[$type]['empty_urls'])) {
                    $plugin_data[$type] = array('sortkey' => $type,
                                                'empty_css_count' => 0,
                                                'found_css_count' => 0,
                                                'empty_urls' => [],
                                                'found_urls' => []
                                                );
                }


                $plugin_data[$type]['found_urls'] = ($plugin_data[$type]['found_urls'] + $data['used']);
                $plugin_data[$type]['empty_urls'] = ($plugin_data[$type]['empty_urls'] + $data['empty']);

                $plugin_data[$type]['empty_css_count'] = count($plugin_data[$type]['empty_urls']);
                $plugin_data[$type]['found_css_count'] = count($plugin_data[$type]['found_urls']);

                ksort($plugin_data[$type]['found_urls']);
                ksort($plugin_data[$type]['empty_urls']);

            }


        }

        return $plugin_data;

    }

    /**
     * Retrieves statistical information about the CSS cache.
     *
     * It looks through the files lookup.json files, finding entries which
     * have an empty CSS file. For the URL used as the key it works out
     * what the plugin is that has called an empty file. Thus we build 
     * a list of plugins that are outputting empty CSS files.
     * 
     *
     * @return array Information about the current state of the CSS cache.
     */
    public static function get_stats_data() {

        $master_data = self::get_master_data();
        
        $data['by_path'] = self::get_by_path($master_data);
        $data['by_plugin'] = self::get_by_plugin($master_data);
        $data['by_post_type'] = self::get_by_posttype($master_data);
    
        return [
            'plugin_stats' => $data,
        ];
    }
    


    /**
     * Extracts the root path from a given path.
     *
     * This function takes a path and returns the root directory of that path.
     * If the path contains more than one part, it appends a trailing slash.
     *
     * @param string $path The path to extract the root from.
     * @return string The root path.
     */
    private static function get_root_path( $path ) {

        $parts = explode('/', trim($path, '/'));
        $return = $parts[0] . (count($parts) > 1 ? '/' : '');
        if($return === '') {
            $return = '/';
        }
        return $return;
    }

    /**
     * Extracts the plugin name from a given URL.
     *
     * This function takes a URL and attempts to determine which plugin
     * the URL belongs to by analyzing the path. It assumes that the URL
     * structure contains a recognizable plugin directory.
     *
     * @param string $url The URL to analyze.
     * @return string The name of the plugin, or 'unknown' if it cannot be determined.
     */
    public static function get_plugin_from_url($url) {
        
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'];
        $pathParts = explode('/', $path);

        // Look for the 'wp-content/plugins' directory in the path
        $pluginIndex = array_search('plugins', $pathParts);
        if ($pluginIndex !== false && isset($pathParts[$pluginIndex + 1])) {
            return $pathParts[$pluginIndex + 1];
        }

        return 'unknown';
    }

    /**
     * Retrieves information about the current state of the CSS cache.
     *
     * The returned array will contain two keys: 'num_css_files' and 'num_lookup_files'.
     * The first will contain the number of CSS files in the cache folder, and the second
     * will contain the number of 'lookup.json' files in the cache folder and all subfolders.
     *
     * @return array Information about the current state of the CSS cache.
     */
    public static function get_cache_data() {

        //Get info on the current state of the cache
        $dir = self::get_root_cache_path();

        // Create the cache directory if it does not exist
        !is_dir($dir) && mkdir($dir, 0755, true);  

        //Get the number of CSS files in this dir (not subfolders)
        $cssFiles = glob($dir . '/*.css');

        // Count the number of CSS files
        $cssFileCount = count($cssFiles);        

        // Create a RecursiveDirectoryIterator to iterate through the directory and subdirectories
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        // Filter the files and count how many are named "lookup.json"
        $lookupFileCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'lookup.json') {
                $lookupFileCount++;
            }
        }        

        //Set the data var
        $data['num_css_files'] = $cssFileCount;
        $data['num_lookup_files'] = $lookupFileCount;

        return $data;


    }

    /**
     * Clears the CSS cache by deleting all files and subfolders in the cache directory.
     *
     * This function will delete all files and subfolders in the root cache directory.
     * Additionally, if the FLYING_PRESS_CACHE_DIR constant is defined, it will also
     * delete all files and subfolders in that directory.
     *
     * @return void
     */
    public static function clear_cache() {
        


        $dir = self::get_root_cache_path();
        self::deleteAllFilesAndSubfolders($dir);        

        //Integrations
        if(defined('FLYING_PRESS_CACHE_DIR')) {
            self::deleteAllFilesAndSubfolders(FLYING_PRESS_CACHE_DIR);
        }
        


    }

    /**
     * Deletes all files and subfolders in a given directory.
     *
     * @param string $dir The path to the directory to delete.
     *
     * @return void
     */
    public static function deleteAllFilesAndSubfolders($dir) {
        // Ensure the directory exists
        if (!is_dir($dir)) {
            //Directory does not exist
            return;
        }
    
        // Create a RecursiveDirectoryIterator to iterate through the directory and subdirectories
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
    
        // Loop through each item (file/folder) in the directory
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                // If it's a directory, use rmdir to remove it
                rmdir($file->getRealPath());
            } else {
                // If it's a file, use unlink to delete it
                unlink($file->getRealPath());
            }
        }                

    }    
    


}
