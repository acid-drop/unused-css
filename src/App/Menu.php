<?php

namespace UCSS\App;

use UCSS\Auth;

/**
 * This class handles adding the UCSS plugin's admin menu
 * and injecting JavaScript and CSS required for the admin page. 
 * It also renders the display content for the UCSS plugin. 
 *
 * @package UCSS
 */
class Menu {
    
    /**
     * @var string Base64-encoded SVG icon used for the admin menu icon.
     */
    public static $menu_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjxzdmcgYmFzZVByb2ZpbGU9InRpbnkiIGhlaWdodD0iMjRweCIgaWQ9IkxheWVyXzEiIHZlcnNpb249IjEuMiIgdmlld0JveD0iMCAwIDI0IDI0IiB3aWR0aD0iMjRweCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PHBhdGggZD0iTTE2LjQzMyw4LjU5NmMtMS4xNTMsMC0yLjIzNywwLjQ0OS0zLjAzNiwxLjI0NmwtMS4zOTYsMS4zNGwtMS4zNzUtMS4zMkM5LjgxMSw5LjA0NSw4LjcyNSw4LjU5Niw3LjU3MSw4LjU5NiAgYy0xLjE1NCwwLTIuMjM5LDAuNDUxLTMuMDUzLDEuMjY2Yy0wLjgxNywwLjgxNi0xLjI2NywxLjktMS4yNjcsMy4wNTVjMCwxLjE1MiwwLjQ0OSwyLjIzOCwxLjI2NiwzLjA1MyAgYzAuODE0LDAuODE2LDEuODk5LDEuMjY2LDMuMDU0LDEuMjY2YzEuMTUzLDAsMi4yMzktMC40NDksMy4wMzYtMS4yNDhsMS4zOTUtMS4zMzhsMS4zNzYsMS4zMmMwLjgxNSwwLjgxNiwxLjkwMSwxLjI2NiwzLjA1NSwxLjI2NiAgczIuMjM4LTAuNDQ5LDMuMDUzLTEuMjY2YzAuODE3LTAuODE0LDEuMjY3LTEuOSwxLjI2Ny0zLjA1NXMtMC40NDktMi4yMzgtMS4yNjYtMy4wNTVDMTguNjcsOS4wNDUsMTcuNTg2LDguNTk2LDE2LjQzMyw4LjU5NnogICBNOC44NTcsMTQuMjAxYy0wLjY4NywwLjY4OC0xLjg4NCwwLjY4OC0yLjU3MiwwYy0wLjM0NC0wLjM0NC0wLjUzMy0wLjgwMS0wLjUzMy0xLjI4NWMwLTAuNDg2LDAuMTg5LTAuOTQxLDAuNTM1LTEuMjg3ICBjMC4zNDItMC4zNDQsMC43OTktMC41MzMsMS4yODQtMC41MzNzMC45NDIsMC4xODksMS4zMDUsMC41NTFsMS4zMjEsMS4yN0w4Ljg1NywxNC4yMDF6IE0xNy43MTgsMTQuMjAxICBjLTAuNjg3LDAuNjg5LTEuODY2LDAuNzA1LTIuNTktMC4wMThsLTEuMzIxLTEuMjdsMS4zMzktMS4yODVjMC42ODgtMC42ODgsMS44ODYtMC42ODgsMi41NzMtMC4wMDIgIGMwLjM0NCwwLjM0NiwwLjUzMywwLjgwMSwwLjUzMywxLjI4N1MxOC4wNjIsMTMuODU3LDE3LjcxOCwxNC4yMDF6IiBmaWxsPSJ3aGl0ZSIgLz48L3N2Zz4=';

    /**
     * @var string Menu slug for the UCSS app
     */
    public static $menu_slug = 'unused-css';

    /**
     * @var array Data that will be passed to the JavaScript frontend for display.
     */
    public static $display = array();

    /**
     * Initialize the class by hooking the 'add_menu' method to the 'admin_menu' action.
     * This ensures that the admin menu is added when the admin dashboard is being loaded.
     *
     * @return void
     */
    public static function init() {        
        
        //Add the UCSS menu
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 999 );

        //Add any error notices
        add_action( 'admin_notices', array( \UCSS\App\Dashboard::class ,'add_notices' ));

    }

    /**
     * Add the custom menu page to the WordPress admin dashboard.
     * This function defines the title, capability, slug, and callback function for the page.
     * It also adds JavaScript and CSS specifically to this admin page.
     *
     * @return void
     */
    public static function add_menu() {
        
        // Check if the current user has the necessary permissions to view the page.
        if ( ! Auth::is_allowed() ) {
            return;
        }

        // Add the custom menu page to the WordPress admin.
        $menu = add_menu_page(
            'UCSS',          // Page title
            'UCSS',          // Menu title
            'edit_posts',           // Capability required to view this menu
            self::$menu_slug,      // Menu slug
            array( __CLASS__, 'display' ), // Callback function to display content
            self::$menu_icon,       // Icon for the menu
            '100'                   // Position in the admin menu
        );


        // Inject the JavaScript and CSS files only when this specific admin page is loaded.
        add_action( 'admin_print_scripts-' . $menu, array( __CLASS__, 'add_js' ) );
    }

    /**
     * Enqueue the JavaScript and CSS required for the custom admin page.
     * This ensures the required assets are loaded only on the specific page.
     *
     * @return void
     */
    public static function add_js() {
        // Enqueue the admin page JavaScript file.
        wp_enqueue_script(
            'ucss_namespace_admin',                      // Handle for the script
            UCSS_PLUGIN_URL . 'assets/index.js', // URL to the script
            array(),                                   // Dependencies (none)
            filemtime( UCSS_PLUGIN_DIR . 'assets/index.js' ), // Version based on file modification time
            true                                       // Load in the footer
        );

        // Enqueue the admin page CSS file.
        wp_enqueue_style(
            'ucss_namespace_admin_style',                // Handle for the style
            UCSS_PLUGIN_URL . 'assets/index.css' // URL to the stylesheet
        );
    }

    /**
     * Display the content for the custom admin page.
     * This function passes configuration and version data to the frontend by injecting it
     * into a global JavaScript object. It also sets up a container for rendering the Vue.js app.
     *
     * @return void
     */
    public static function display() {

        // Get the plugin version.
        $version = UCSS_VER;

        // Get the plugin configuration, removing any non-global values.
        $config  = json_encode( Config::remove_non_global(Config::$config ));

        //Set ajax url
        $ajax_url =  admin_url( 'admin-ajax.php' );

        // Output the necessary data for the frontend into a JavaScript object.
        echo "<script>window.ucss_namespace={config:$config,version:'$version',display:".json_encode(self::$display).",ajaxurl:'$ajax_url'}</script>";

        // Output a container div for the Vue.js app with Tailwind CSS classes.
        echo '<div id="app" class="tailwind"></div>';
    }

}