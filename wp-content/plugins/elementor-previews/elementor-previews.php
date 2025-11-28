<?php

/**
 * Plugin Name: Elementor Template Previews
 * Plugin URI: https://yourwebsite.com
 * Description: Adds thumbnail preview functionality to Elementor's template library
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-previews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELEMENTOR_PREVIEWS_VERSION', '1.0.0');
define('ELEMENTOR_PREVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELEMENTOR_PREVIEWS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ELEMENTOR_PREVIEWS_PLUGIN_FILE', __FILE__);

// Initialize Plugin Update Checker for GitHub releases
require_once ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'plugin-update-checker-5.6/load-v5p6.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$elementor_previews_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/webgate-digital/elementor-previews/',
    __FILE__,
    'elementor-previews'
);

// Set the branch that contains the stable release (optional, defaults to master)
$elementor_previews_update_checker->setBranch('main');

// Enable release assets - this makes it download the zip from GitHub releases
$elementor_previews_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * Main Elementor Previews Plugin Class
 */
class Elementor_Previews_Plugin
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'admin_notice_missing_elementor'));
            return;
        }

        // Register the endpoint and handle requests
        add_action('init', array($this, 'register_template_preview_endpoint'));
        add_action('parse_request', array($this, 'handle_template_preview_request'));

        // Load other functionality
        $this->load_hooks();
    }

    /**
     * Register rewrite endpoint
     */
    public function register_template_preview_endpoint()
    {
        add_rewrite_rule('^template-preview/([0-9]+)/?', 'index.php?template_preview_id=$matches[1]', 'top');
        add_rewrite_tag('%template_preview_id%', '([0-9]+)');
    }

    /**
     * Handle template preview request
     */
    public function handle_template_preview_request($wp)
    {
        // Check if this is our preview request
        if (!array_key_exists('template_preview_id', $wp->query_vars)) {
            return;
        }

        $template_id = intval($wp->query_vars['template_preview_id']);

        if (!$template_id) {
            wp_die('Invalid template ID.');
        }

        // Get the template post
        $template_post = get_post($template_id);

        if (!$template_post || $template_post->post_type !== 'elementor_library') {
            wp_die('Template not found.');
        }

        // Render the template
        $this->render_public_template($template_post);
        exit;
    }

    /**
     * Render template publicly with all Elementor assets
     */
    private function render_public_template($template_post)
    {
        // Make sure Elementor is loaded
        if (!class_exists('\Elementor\Plugin')) {
            wp_die('Elementor not available.');
        }

        // Get Elementor instance
        $elementor = \Elementor\Plugin::instance();

        // Initialize frontend if not already done
        $elementor->frontend->init();

        // Get the document
        $document = $elementor->documents->get($template_post->ID);

        if (!$document) {
            wp_die('Could not load template document.');
        }

        // Set up global post data
        global $post;
        $post = $template_post;
        setup_postdata($post);

        // Start output buffering
        ob_start();

        // Output HTML structure
?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($template_post->post_title); ?> - Template Preview</title>

            <?php
            // Enqueue WordPress head
            wp_head();

            // Force enqueue Elementor styles and scripts
            $elementor->frontend->enqueue_styles();
            $elementor->frontend->enqueue_scripts();

            // Print styles that might have been enqueued
            wp_print_styles();
            ?>

            <style>
                body {
                    margin: 0;
                    padding: 0;
                }

                /* Hide admin bar if shown */
                #wpadminbar {
                    display: none !important;
                }

                html {
                    margin-top: 0 !important;
                }
            </style>
        </head>

        <body <?php body_class('elementor-template-preview'); ?>>

            <?php
            // Render the Elementor content
            echo $document->get_content();
            ?>

            <?php
            // WordPress footer
            wp_footer();
            ?>

            <script>
                // Ensure Elementor frontend is initialized
                if (typeof elementorFrontend !== 'undefined') {
                    elementorFrontend.init();
                }
            </script>
        </body>

        </html>
<?php

        // Get the output and clean up
        $output = ob_get_clean();
        wp_reset_postdata();

        // Output the final HTML
        echo $output;
    }

    /**
     * Load plugin hooks
     */
    private function load_hooks()
    {
        // Add the preview functionality to Elementor editor
        add_action('elementor/editor/footer', array($this, 'enqueue_preview_assets'));

        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));

        // Ensure our query var is recognized
        add_filter('query_vars', array($this, 'add_query_vars'));

    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'template_preview_id';
        return $vars;
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'elementor-previews',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Enqueue CSS and JS assets for preview functionality
     */
    public function enqueue_preview_assets()
    {
        $css_file = ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'assets/css/elementor-previews.css';
        $js_file = ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'assets/js/elementor-previews.js';

        // Enqueue CSS with file modification time as version
        wp_enqueue_style(
            'elementor-previews-style',
            ELEMENTOR_PREVIEWS_PLUGIN_URL . 'assets/css/elementor-previews.css',
            array(),
            file_exists($css_file) ? filemtime($css_file) : ELEMENTOR_PREVIEWS_VERSION
        );

        // Enqueue JavaScript with file modification time as version
        wp_enqueue_script(
            'elementor-previews-script',
            ELEMENTOR_PREVIEWS_PLUGIN_URL . 'assets/js/elementor-previews.js',
            array('jquery'),
            file_exists($js_file) ? filemtime($js_file) : ELEMENTOR_PREVIEWS_VERSION,
            true
        );

        // Localize script for translations
        wp_localize_script('elementor-previews-script', 'elementorPreviews', array(
            'adminUrl' => admin_url(),
            'strings' => array(
                'insert' => __('Insert', 'elementor-previews'),
                'edit' => __('Edit', 'elementor-previews'),
                'delete' => __('Delete', 'elementor-previews'),
                'export' => __('Export', 'elementor-previews'),
                'more_actions' => __('More actions', 'elementor-previews'),
            )
        ));
    }

    /**
     * Admin notice for missing Elementor
     */
    public function admin_notice_missing_elementor()
    {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'elementor-previews'),
            '<strong>' . esc_html__('Elementor Template Previews', 'elementor-previews') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'elementor-previews') . '</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
}

/**
 * Initialize the plugin
 */
function elementor_previews_plugin()
{
    return Elementor_Previews_Plugin::get_instance();
}

// Start the plugin
elementor_previews_plugin();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function () {
    // Create directory structure
    $plugin_dirs = array(
        ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'assets',
        ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'assets/css',
        ELEMENTOR_PREVIEWS_PLUGIN_PATH . 'assets/js',
    );

    foreach ($plugin_dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }

    // Flush rewrite rules to activate the new endpoint
    flush_rewrite_rules();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    // Clean up rewrite rules
    flush_rewrite_rules();
});
