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
    public static $menu_icon = 'data:image/svg+xml;base64,PHN2ZyBjbGFzcz0iZmxleC1zaHJpbmstMCB3LTUgaC01IGlubGluZS1ibG9jayBtdC1bLTNweF0iIAogICAgICAgICAgICAgICAgICB2aWV3Qm94PSItMzEuODcyIC0zLjg0MiA2NC4yMDMgMjYuMzA0IiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj4KICAgICAgICAgICAgICAgICAgPGcgZmlsbD0iI2E3YWFhZCIgc3Ryb2tlPSIjYTdhYWFkIiBzdHJva2UtbWl0ZXJsaW1pdD0iMTAiIHN0cm9rZS13aWR0aD0iMiI+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0ibS0yOS43MDQgOS4xNzJjMC0yLjY4OCAwLjgzOS00LjkzMiAyLjUxNS02LjczOSAxLjY3OC0xLjgwNSAzLjUyLTMuMDExIDUuNTMzLTMuNjEyIDAuNDYyLTAuMTYxIDAuOTI2LTAuMjc3IDEuMzg5LTAuMzQ4IDAuNDYtMC4wNzEgMC45MTItMC4xMDQgMS4zNTItMC4xMDRsMjQuNTYxLTAuMDM0djIuNzA0aC0yMy44MzRjLTIuNzk3IDAuMjc3LTQuOTI1IDEuMjUxLTYuMzgyIDIuOTE0LTEuNDU3IDEuNjY0LTIuMTk5IDMuNDY5LTIuMjIyIDUuNDEydjAuMTAyYzAgMC40NDEgMC4wNiAwLjk3MiAwLjE3NiAxLjU5OHMwLjMyMSAxLjI1OSAwLjYyNSAxLjkwOGMwLjUwNyAxLjE1OCAxLjM2OCAyLjIyIDIuNTgyIDMuMTc5IDEuMjE4IDAuOTYgMi45NTcgMS40MzMgNS4yMjMgMS40MzNoMi40NjJ2Mi42MzloLTMuOTE4Yy0zLjIxNS0wLjA5My01LjY5Ni0xLjE4LTcuNDQxLTMuMjY1LTEuNzQ4LTIuMDgyLTIuNjItNC42NjgtMi42Mi03Ljc1di0wLjAzN3oiLz4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJtLTYuNDYgMTcuNTUxYzAuOTcgMC4wMjMgMS43NS0wLjE1OSAyLjMzOS0wLjU1MyAwLjU5LTAuMzk2IDAuOTQzLTEuMDE4IDEuMDU4LTEuODc1IDAtMC43NC0wLjI0My0xLjQyMi0wLjcyOC0yLjA0NS0wLjQ4Ni0wLjYyNi0xLjIxNC0xLjE4NC0yLjE4Ni0xLjY3LTAuMTg2LTAuMDkxLTAuMzc4LTAuMTgxLTAuNTczLTAuMjcxLTAuMTk4LTAuMDk2LTAuMzk3LTAuMTkzLTAuNjA3LTAuMjgzLTAuMDIyLTAuMDIyLTAuMDU0LTAuMDM5LTAuMDg4LTAuMDUtMC4wMzMtMC4wMTItMC4wNjUtMC4wMjgtMC4wODctMC4wNTEtMS43MTEtMC44MzUtMy4yNzctMS43MjgtNC43LTIuNjg4LTEuNDItMC45NTgtMi4xMzItMi4yNjktMi4xMzItMy45MzcgMC4wMjMtMC4wNyAwLjAzNS0wLjEyNyAwLjAzNS0wLjE3NSAwLTAuMDQ1IDVlLTMgLTAuMDc5IDAuMDE2LTAuMTA0IDAuMDEyLTAuMDIzIDAuMDE4LTAuMDQ2IDAuMDE4LTAuMDY5IDAtMS42MDUgMC40NTgtMi44MTYgMS4zNzMtMy42MyAwLjkxMS0wLjgxNiAxLjkxMS0xLjM0OSAyLjk5Ny0xLjYwNiAwLjE2NS0wLjA0NyAwLjMzMS0wLjA4MiAwLjUwNS0wLjEwNCAwLjE3My0wLjAyNiAwLjMzOS0wLjA0OCAwLjUwMy0wLjA3MWw3LjYzLTAuMDM0djIuNzA0aC03LjE0NWMtMC44MTEgMC4wMjMtMS40NTcgMC4yNzUtMS45NDEgMC43NDYtMC40ODYgMC40NzQtMC43ODggMS4wMDItMC45MDQgMS41NzggMCAwLjA5My02ZS0zIDAuMTgxLTAuMDE3IDAuMjYyLTAuMDEyIDAuMDgtMC4wMTcgMC4xNTUtMC4wMTcgMC4yMjQgMCAwLjMyNSAwLjA4MiAwLjY0MiAwLjI0MiAwLjk1NCAwLjE2MSAwLjMxMyAwLjM4MSAwLjU2NCAwLjY1OCAwLjc0NSAwLjk0OSAwLjY3MiAxLjg0IDEuMjE5IDIuNjcyIDEuNjMyIDAuODMzIDAuNDE3IDEuNjA3IDAuNzk1IDIuMzI0IDEuMTQ1IDAuMjA5IDAuMTEyIDAuNDA5IDAuMjI2IDAuNjA3IDAuMzI4IDAuMTk0IDAuMTAyIDAuMzg2IDAuMjEzIDAuNTczIDAuMzI3IDAuMTYxIDAuMDk2IDAuMzI4IDAuMTkzIDAuNTAzIDAuMzAxIDAuMTcyIDAuMTAyIDAuMzQxIDAuMjA5IDAuNTAxIDAuMzI3IDAuNzE4IDAuNTM2IDEuMzgxIDEuMjAzIDEuOTk3IDIuMDE2IDAuNjEyIDAuODA3IDAuOTE3IDEuODEyIDAuOTE3IDMuMDE1IDAgMC4zMDUtMC4wMTcgMC42MzItMC4wNSAxLjAwNC0wLjAzNSAwLjM3My0wLjExMSAwLjc0NS0wLjIyNiAxLjExMy0wLjI1MyAwLjg5Ni0wLjc4MSAxLjcxNC0xLjU3NyAyLjQ0NC0wLjc5OSAwLjcyNi0yLjA1MyAwLjgxMS0zLjc2NCAwLjgxMWgtMC4xMDQtMC4xMDQtMC4wMzRjLTAuMDQ4IDAtMC4wOTMtMC4wMTEtMC4xNDEtMC4wMjItMC4wNDUtMC4wMTItMC4wNzktMC4wMTItMC4xMDMtMC4wMTJsLTguMTE4LTAuMDM4di0yLjM4OGg3Ljg3OHoiLz4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJtOS42MzQgMTcuNTUxYzAuOTcxIDAuMDIzIDEuNzUxLTAuMTU5IDIuMzM3LTAuNTUzIDAuNTk0LTAuMzk2IDAuOTQ4LTEuMDE4IDEuMDYyLTEuODc1IDAtMC43NC0wLjI0My0xLjQyMi0wLjcyOS0yLjA0NS0wLjQ4NC0wLjYyNi0xLjIxMy0xLjE4NC0yLjE4NS0xLjY3LTAuMTg0LTAuMDkxLTAuMzc3LTAuMTgxLTAuNTcxLTAuMjcxLTAuMTk5LTAuMDk2LTAuMzk4LTAuMTkzLTAuNjA3LTAuMjgzLTAuMDIzLTAuMDIyLTAuMDU1LTAuMDM5LTAuMDg3LTAuMDUtMC4wMzUtMC4wMTItMC4wNjYtMC4wMjgtMC4wODgtMC4wNTEtMS43MTEtMC44MzUtMy4yNzctMS43MjgtNC43MDEtMi42ODgtMS40MTktMC45NTktMi4xMzEtMi4yNjktMi4xMzEtMy45MzcgMC4wMjItMC4wNyAwLjAzNC0wLjEyOCAwLjAzNC0wLjE3NSAwLTAuMDQ1IDZlLTMgLTAuMDc5IDAuMDE3LTAuMTA0IDAuMDEyLTAuMDIzIDAuMDE3LTAuMDQ2IDAuMDE3LTAuMDY5IDAtMS42MDUgMC40NTgtMi44MTYgMS4zNzEtMy42MyAwLjkxMi0wLjgxNiAxLjkxMS0xLjM0OSAyLjk5OS0xLjYwNiAwLjE2Mi0wLjA0OCAwLjMzLTAuMDgzIDAuNTA1LTAuMTA0IDAuMTcyLTAuMDI2IDAuMzM3LTAuMDQ4IDAuNS0wLjA3MWw3LjYzMS0wLjAzNHYyLjcwNGgtNy4xNDRjLTAuODEgMC4wMjMtMS40NTcgMC4yNzUtMS45NDIgMC43NDYtMC40ODQgMC40NzQtMC43ODYgMS4wMDItMC45MDMgMS41NzggMCAwLjA5My02ZS0zIDAuMTgxLTAuMDE2IDAuMjYyLTAuMDEyIDAuMDgtMC4wMTggMC4xNTUtMC4wMTggMC4yMjQgMCAwLjMyNSAwLjA4MyAwLjY0MiAwLjI0MyAwLjk1NCAwLjE2IDAuMzEzIDAuMzgxIDAuNTY0IDAuNjU4IDAuNzQ1IDAuOTQ5IDAuNjcyIDEuODQgMS4yMTkgMi42NzQgMS42MzIgMC44MzIgMC40MTcgMS42MDYgMC43OTUgMi4zMjIgMS4xNDUgMC4yMDkgMC4xMTIgMC40MTIgMC4yMjYgMC42MDUgMC4zMjggMC4xOTcgMC4xMDIgMC4zODkgMC4yMTMgMC41NzYgMC4zMjcgMC4xNTggMC4wOTYgMC4zMjcgMC4xOTMgMC41MDIgMC4zMDEgMC4xNzQgMC4xMDIgMC4zNDMgMC4yMDkgMC41MDIgMC4zMjcgMC43MTcgMC41MzYgMS4zNzcgMS4yMDMgMiAyLjAxNiAwLjYwNyAwLjgwNyAwLjkxNCAxLjgxMiAwLjkxNCAzLjAxNSAwIDAuMzA1LTAuMDE4IDAuNjMyLTAuMDUyIDEuMDA0LTAuMDMzIDAuMzczLTAuMTA3IDAuNzQ1LTAuMjI1IDEuMTEzLTAuMjU1IDAuODk3LTAuNzg1IDEuNzE1LTEuNTc2IDIuNDQ1LTAuODAzIDAuNzI2LTIuMDU0IDAuODExLTMuNzY2IDAuODExaC0wLjEwNy0wLjEwMi0wLjAzNWMtMC4wNDQgMC0wLjA4OC0wLjAxMS0wLjE0LTAuMDIyLTAuMDQ0LTAuMDEyLTAuMDc4LTAuMDEyLTAuMTAyLTAuMDEybC04LjExOS0wLjAzOHYtMi4zODhoNy44Nzd6Ii8+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0ibTMwLjE1OSA5LjIwOGMwIDMuMDgzLTAuODcgNS42NjktMi42MiA3Ljc0OS0xLjc0MyAyLjA4NC00LjIyNSAzLjE3LTcuNDQxIDMuMjY0aC0zMy44NHYtMi42MzloMzIuMzg0YzIuMjY0IDAgNC4wMDQtMC40NzMgNS4yMjItMS40MzIgMS4yMTQtMC45NTcgMi4wNzgtMi4wMiAyLjU4Ni0zLjE3NCAwLjI5OS0wLjY1MSAwLjUwNy0xLjI4NiAwLjYyMi0xLjkxMiAwLjExOS0wLjYyNiAwLjE3NC0xLjE1NiAwLjE3NC0xLjU5OHYtMC4xMDJjLTAuMDIzLTEuOTQzLTAuNzYyLTMuNzQ4LTIuMjE4LTUuNDEyLTEuNDU4LTEuNjYzLTMuNTkyLTIuNjM3LTYuMzg2LTIuOTE0aC0xMS4wMTJ2LTIuNzA0bDExLjMxMyAwLjAzNGMwLjQ0MSAwIDEuMzE5IDAuMDMzIDEuNzc3IDAuMTA0IDAuNDYyIDAuMDcxIDAuOTI2IDAuMTg3IDEuMzg5IDAuMzQ4IDIuMDEzIDAuNjAxIDMuODYxIDEuODA3IDUuNTM4IDMuNjEyIDEuNjc2IDEuODA3IDIuNTEzIDQuMDUxIDIuNTEzIDYuNzM5djAuMDM3eiIvPgogICAgICAgICAgICAgICAgICA8L2c+CiAgICAgICAgICAgICAgPC9zdmc+';

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
            'Unused CSS',          // Page title
            'Unused CSS',          // Menu title
            'manage_options',           // Capability required to view this menu
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