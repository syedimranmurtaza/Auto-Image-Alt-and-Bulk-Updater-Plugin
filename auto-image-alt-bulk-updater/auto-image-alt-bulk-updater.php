<?php
/**
 * Plugin Name: Auto Image Alt & Bulk Updater
 * Description: Automatically generates image alt attributes based on post or page title and provides a bulk updater with customizable suffix text.
 * Version: 1.1.0
 * Author: CtrlAltImran
 * Author URI: https://ctrlaltimran.com
 * Text Domain: auto-image-alt-bulk-updater
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Auto_Image_Alt_Bulk_Updater' ) ) {

    class Auto_Image_Alt_Bulk_Updater {

        private static $instance = null;
        private $option_key      = 'aiabu_suffix';

        /**
         * Singleton instance.
         *
         * @return Auto_Image_Alt_Bulk_Updater
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
            // Admin page + assets.
            add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            // AJAX bulk processor.
            add_action( 'wp_ajax_aiabu_bulk_update', array( $this, 'ajax_bulk_update' ) );

            // Auto set alt on upload.
            add_action( 'add_attachment', array( $this, 'auto_set_alt_on_upload' ) );
        }

        /**
         * Register Tools page.
         */
        public function register_admin_page() {
            add_management_page(
                __( 'Image Alt Bulk Updater', 'auto-image-alt-bulk-updater' ),
                __( 'Image Alt Updater', 'auto-image-alt-bulk-updater' ),
                'manage_options',
                'aiabu-image-alt-updater',
                array( $this, 'render_admin_page' )
            );
        }

        /**
         * Enqueue admin assets.
         *
         * @param string $hook
         */
        public function enqueue_assets( $hook ) {
            if ( 'tools_page_aiabu-image-alt-updater' !== $hook ) {
                return;
            }

            wp_enqueue_script(
                'aiabu-admin',
                plugin_dir_url( __FILE__ ) . 'assets/admin.js',
                array( 'jquery' ),
                '1.1.0',
                true
            );

            $total = $this->count_images();

            wp_localize_script(
                'aiabu-admin',
                'aiabuSettings',
                array(
                    'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( 'aiabu_bulk_update' ),
                    'total'     => intval( $total ),
                    'batchSize' => 20,
                )
            );
        }

        /**
         * Count all image attachments in the media library.
         *
         * @return int
         */
        private function count_images() {
            $q = new WP_Query(
                array(
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post_mime_type' => 'image',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                )
            );

            return (int) $q->found_posts;
        }

        /**
         * Render admin page content.
         */
        public function render_admin_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'auto-image-alt-bulk-updater' ) );
            }

            // Handle suffix save.
            if ( isset( $_POST['aiabu_suffix_nonce'] ) && wp_verify_nonce( $_POST['aiabu_suffix_nonce'], 'aiabu_save_suffix' ) ) {
                if ( isset( $_POST['aiabu_suffix'] ) ) {
                    $suffix = wp_kses_post( wp_unslash( $_POST['aiabu_suffix'] ) );
                    $suffix = trim( $suffix );
                    update_option( $this->option_key, $suffix );
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'auto-image-alt-bulk-updater' ) . '</p></div>';
                }
            }

            $total  = $this->count_images();
            $suffix = get_option( $this->option_key, '' );
            $suffix = is_string( $suffix ) ? $suffix : '';
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Image Alt Bulk Updater', 'auto-image-alt-bulk-updater' ); ?></h1>

                <p>
                    <?php esc_html_e( 'This tool automatically generates or updates the alt text for your images.', 'auto-image-alt-bulk-updater' ); ?>
                    <?php esc_html_e( 'If an image is used as a featured image, its alt text will be based on the related post or page title.', 'auto-image-alt-bulk-updater' ); ?>
                    <?php esc_html_e( 'If it is attached to a post, it will use that post title, otherwise the image title will be used.', 'auto-image-alt-bulk-updater' ); ?>
                </p>

                <h2><?php esc_html_e( 'Suffix Settings', 'auto-image-alt-bulk-updater' ); ?></h2>

                <form method="post" style="max-width: 600px; margin-bottom: 20px;">
                    <?php wp_nonce_field( 'aiabu_save_suffix', 'aiabu_suffix_nonce' ); ?>
                    <p>
                        <label for="aiabu_suffix">
                            <?php esc_html_e( 'Custom text to append at the end of all image alt attributes:', 'auto-image-alt-bulk-updater' ); ?>
                        </label>
                    </p>
                    <p>
                        <input
                            type="text"
                            id="aiabu_suffix"
                            name="aiabu_suffix"
                            class="regular-text"
                            value="<?php echo esc_attr( $suffix ); ?>"
                            placeholder="<?php esc_attr_e( '- Northvilla Beauty Spa', 'auto-image-alt-bulk-updater' ); ?>"
                        />
                    </p>
                    <p class="description">
                        <?php esc_html_e( 'Example: "- Northvilla Beauty Spa". Leave empty if you do not want any suffix.', 'auto-image-alt-bulk-updater' ); ?>
                    </p>
                    <p>
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Save settings', 'auto-image-alt-bulk-updater' ); ?>
                        </button>
                    </p>
                </form>

                <h2><?php esc_html_e( 'Bulk Update', 'auto-image-alt-bulk-updater' ); ?></h2>

                <p>
                    <strong><?php esc_html_e( 'Total images detected:', 'auto-image-alt-bulk-updater' ); ?></strong>
                    <?php echo esc_html( (string) intval( $total ) ); ?>
                </p>

                <p>
                    <button id="aiabu-start" class="button button-primary">
                        <?php esc_html_e( 'Run bulk update', 'auto-image-alt-bulk-updater' ); ?>
                    </button>
                </p>

                <p id="aiabu-status"></p>

                <div id="aiabu-progress-bar" style="margin-top:15px;width:100%;max-width:400px;border:1px solid #ccc;border-radius:4px;height:20px;overflow:hidden;background:#f1f1f1;">
                    <div id="aiabu-progress-fill" style="width:0%;height:100%;background:#0073aa;"></div>
                </div>

                <p id="aiabu-progress-text" style="margin-top:8px;"></p>

                <p style="max-width:650px;margin-top:20px;">
                    <?php esc_html_e( 'Tip: You can safely run this tool multiple times. It will avoid adding the suffix twice and will refresh the alt text rules for your media library.', 'auto-image-alt-bulk-updater' ); ?>
                </p>
            </div>
            <?php
        }

        /**
         * AJAX handler for bulk processing.
         */
        public function ajax_bulk_update() {
            check_ajax_referer( 'aiabu_bulk_update', 'nonce' );

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Permission denied.', 'auto-image-alt-bulk-updater' ),
                    )
                );
            }

            $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
            $batch  = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 20;

            if ( $batch < 1 ) {
                $batch = 20;
            }

            $query = new WP_Query(
                array(
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post_mime_type' => 'image',
                    'posts_per_page' => $batch,
                    'offset'         => $offset,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'fields'         => 'ids',
                )
            );

            $processed = 0;

            if ( $query->have_posts() ) {
                foreach ( $query->posts as $attachment_id ) {
                    $this->update_alt_for_attachment( $attachment_id );
                    $processed++;
                }
            }

            $total       = $this->count_images();
            $next_offset = $offset + $processed;
            $done        = ( 0 === $processed || $next_offset >= $total );

            wp_send_json_success(
                array(
                    'processed'  => $processed,
                    'nextOffset' => $next_offset,
                    'total'      => $total,
                    'done'       => $done,
                )
            );
        }

        /**
         * Automatically set alt text when an attachment is added.
         *
         * @param int $attachment_id
         */
        public function auto_set_alt_on_upload( $attachment_id ) {
            $mime = get_post_mime_type( $attachment_id );
            if ( ! $mime || false === strpos( $mime, 'image/' ) ) {
                return;
            }

            $this->update_alt_for_attachment( $attachment_id );
        }

        /**
         * Get the configured suffix.
         *
         * @return string
         */
        private function get_suffix() {
            $suffix = get_option( $this->option_key, '' );
            if ( ! is_string( $suffix ) ) {
                return '';
            }
            $suffix = trim( $suffix );
            return $suffix;
        }

        /**
         * Core logic to update alt text for a single attachment.
         *
         * @param int $attachment_id
         */
        private function update_alt_for_attachment( $attachment_id ) {
            $attachment_id = intval( $attachment_id );
            if ( $attachment_id <= 0 ) {
                return;
            }

            $mime = get_post_mime_type( $attachment_id );
            if ( ! $mime || false === strpos( $mime, 'image/' ) ) {
                return;
            }

            $suffix = $this->get_suffix();

            $current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            $current_alt = is_string( $current_alt ) ? $current_alt : '';

            $base = '';

            // 1) If used as featured image of a post or page, prefer that title.
            $thumb_posts = get_posts(
                array(
                    'post_type'   => array( 'post', 'page' ),
                    'meta_key'    => '_thumbnail_id',
                    'meta_value'  => $attachment_id,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );

            if ( ! empty( $thumb_posts ) ) {
                $base = get_the_title( $thumb_posts[0] );
            }

            // 2) Otherwise, if the attachment has a parent, use its title.
            if ( empty( $base ) ) {
                $parent_id = (int) get_post_field( 'post_parent', $attachment_id );
                if ( $parent_id > 0 ) {
                    $base = get_the_title( $parent_id );
                }
            }

            // 3) Otherwise, fall back to the attachment title.
            if ( empty( $base ) ) {
                $attachment = get_post( $attachment_id );
                if ( $attachment && ! empty( $attachment->post_title ) ) {
                    $base = $attachment->post_title;
                }
            }

            // Final fallback.
            if ( empty( $base ) ) {
                $base = __( 'Image', 'auto-image-alt-bulk-updater' );
            }

            $base = trim( wp_strip_all_tags( $base ) );

            if ( ! empty( $current_alt ) ) {
                $new_alt = $current_alt;
            } else {
                $new_alt = $base;
            }

            // Append suffix if configured and not already present (case-insensitive).
            $suffix = $this->get_suffix();
            if ( '' !== $suffix ) {
                if ( false === stripos( $new_alt, $suffix ) ) {
                    $separator = ( substr( $new_alt, -1 ) === ' ' ) ? '' : ' ';
                    $new_alt  .= $separator . $suffix;
                }
            }

            $new_alt = trim( $new_alt );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $new_alt );
        }
    }

    // Initialize plugin.
    Auto_Image_Alt_Bulk_Updater::instance();
}
