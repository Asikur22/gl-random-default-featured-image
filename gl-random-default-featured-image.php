<?php
/**
 * Plugin Name: GL Random Default Featured Image
 * Description: Randomly sets a default featured image from selected media for posts.
 * Version: 1.0
 * Author: Asiqur Rahman <asiq.webdev@gmail.com>
 * Author URI: https://asique.net/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gl-rdfi
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'GL_RDFI' ) ) {
    class GL_RDFI {
        private $option_name = 'rdfi_media_ids';
        
        private $page_slug = 'gl-rdfi';
        
        public function __construct() {
            add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
            
            add_filter( 'get_post_metadata', array( $this, 'set_cache_meta_key' ), 10, 3 );
            add_filter( 'post_thumbnail_id', [ $this, 'filter_post_thumbnail_id' ], 20, 2 );
            
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ] );
        }
        
        public function add_admin_menu() {
            add_options_page(
                'Random Default Featured Image',
                'Random Featured Image',
                'manage_options',
                $this->page_slug,
                [ $this, 'render_settings_page' ]
            );
        }
        
        public function enqueue_admin_scripts( $hook ) {
            if ( $hook === 'settings_page_' . $this->page_slug ) {
                wp_enqueue_media();
                wp_enqueue_script( 'rdfi-media', plugin_dir_url( __FILE__ ) . '/assets/js/media-selector.js', null, '1.0', true );
            }
        }
        
        public function render_settings_page() {
            $saved_ids = get_option( $this->option_name, [] );
            $saved_post_types = get_option( 'rdfi_post_types', [] );
            ?>
			<div class="wrap">
				<h1>Random Default Featured Image By <a href="https://www.asique.net/?ref=rdfi" target="_blank">Asiqur Rahman</a></h1>
				<p>Select images to use as default featured image:</p>
				<form method="post">
                    <?php wp_nonce_field( $this->option_name . '_nonce' ); ?>
					<input type="hidden" id="rdfi_media_ids" name="rdfi_media_ids" value="<?php echo esc_attr( json_encode( $saved_ids ) ); ?>">
					<button class="button" id="rdfi_select_images">Select Images</button>
					<div id="rdfi_preview" style="margin-top: 15px;">
                        <?php
                        foreach ( $saved_ids as $id ) {
                            echo wp_get_attachment_image( $id, 'thumbnail', false, [ 'style' => 'margin-right:10px;border:1px solid #ccc' ] );
                        }
                        ?>
					</div>

					<h2>Select Post Types</h2>
					<p>Select the post types where the random default featured image should be applied:</p>
                    <?php
                    $post_types = get_post_types( [ 'public' => true ], 'objects' );
					unset( $post_types['attachment'] );
                    foreach ( $post_types as $post_type ) {
                        $checked = in_array( $post_type->name, $saved_post_types ) ? 'checked' : '';
                        echo "<label><input type='checkbox' name='rdfi_post_types[]' value='{$post_type->name}' {$checked}> {$post_type->label}</label><br>";
                    }
                    ?>

					<p><input type="submit" name="rdfi_submit" class="button button-primary" value="Save"></p>
				</form>
			</div>
            <?php
        }
        
        public function handle_form_submission() {
            if ( isset( $_POST['rdfi_submit'] ) && check_admin_referer( $this->option_name . '_nonce' ) ) {
                $media_ids = json_decode( stripslashes( $_POST['rdfi_media_ids'] ?? '[]' ), true );
                if ( is_array( $media_ids ) ) {
                    update_option( $this->option_name, array_map( 'intval', $media_ids ) );
                }

                $post_types = $_POST['rdfi_post_types'] ?? [];
                update_option( 'rdfi_post_types', array_map( 'sanitize_text_field', $post_types ) );
            }
        }
        
        public function set_cache_meta_key( $_null, $object_id, $meta_key ) {
            // Only affect thumbnails on the frontend, do allow ajax calls.
            if ( ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) ) {
                return $_null;
            }
            
            // Check only empty meta_key and '_thumbnail_id'.
            if ( ! empty( $meta_key ) && '_thumbnail_id' !== $meta_key ) {
                return $_null;
            }
            
            $post_type = get_post_type( $object_id );
            $saved_post_types = get_option( 'rdfi_post_types', [] );
            if ( ! in_array( $post_type, $saved_post_types ) ) {
                return $_null;
            }
            
            // Check if this post type supports featured images.
            if ( false !== $post_type && ! post_type_supports( $post_type, 'thumbnail' ) ) {
                return $_null; // post type does not support featured images.
            }
            
            // Get current Cache.
            $meta_cache = wp_cache_get( $object_id, 'post_meta' );
            
            /**
             * Empty objects probably need to be initiated.
             *
             * @see get_metadata() in /wp-includes/meta.php
             */
            if ( ! $meta_cache ) {
                $meta_cache = update_meta_cache( 'post', array( $object_id ) );
                if ( ! empty( $meta_cache[ $object_id ] ) ) {
                    $meta_cache = $meta_cache[ $object_id ];
                } else {
                    $meta_cache = array();
                }
            }
            
            // Is the _thumbnail_id present in cache?
            if ( ! empty( $meta_cache['_thumbnail_id'][0] ) ) {
                return $_null; // it is present, don't check anymore.
            }
            
            // Get the Default Featured Image ID.
            $ids = get_option( $this->option_name, [] );
            if ( empty( $ids ) ) {
                return $_null;
            }
            
            // Set the dfi in cache.
            $meta_cache['_thumbnail_id'][0] = $ids[ array_rand( $ids ) ];
            wp_cache_set( $object_id, $meta_cache, 'post_meta' );
            
            return $_null;
        }
        
        public function filter_post_thumbnail_id( $thumbnail_id, $post ) {
            if ( $thumbnail_id ) {
                return $thumbnail_id;
            }
			
			$post_type = get_post_type( $post->ID );
			$saved_post_types = get_option( 'rdfi_post_types', [] );
			if ( ! in_array( $post_type, $saved_post_types ) ) {
				return $thumbnail_id;
			}
            
            $ids = get_option( $this->option_name, [] );
            if ( empty( $ids ) ) {
                return $thumbnail_id;
            }
            
            return $ids[ array_rand( $ids ) ];
        }
        
        public function add_settings_link( $links ) {
            $settings_link = '<a href="' . admin_url( 'options-general.php?page=' . $this->page_slug ) . '">' . __( 'Settings', 'gl-rdfi' ) . '</a>';
            array_unshift( $links, $settings_link );
            
            return $links;
        }
    }
    
    new GL_RDFI();
}
