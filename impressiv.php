<?php
/*
Plugin Name: Impressiv
Plugin URI: http://www.github.com/johnciacia/impressiv
Description: Create presentations in WordPress with impress.js
Version: 1.0
Author: John Ciacia
Author URI: http://www.johnciacia.com
License: GPL2


Welcome to the light side of the source, young padawan.

                           ____                  
                        _.' :  `._               
                    .-.'`.  ;   .'`.-.           
           __      / : ___\ ;  /___ ; \      __  
         ,'_ ""--.:__;".-.";: :".-.":__;.--"" _`,
         :' `.t""--.. '<@.`;_  ',@:` ..--""j.' `;
              `:-.._J '-.-'L__ `-- ' L_..-;'     
                "-.__ ;  .-"  "-.  : __.-"       
                    L ' /.------.\ ' J           
                     "-.   "--"   .-"            
                    __.l"-:_JL_;-";.__           
                 .-j/'.;  ;""""  / .'\"-.        
               .' /:`. "-.:     .-" .';  `.      
            .-"  / ;  "-. "-..-" .-"  :    "-.   
         .+"-.  : :      "-.__.-"      ;-._   \  
         ; \  `.; ;                    : : "+. ; 
         :  ;   ; ;                    : ;  : \: 
         ;  :   ; :                    ;:   ;  : 
        : \  ;  :  ;                  : ;  /  :: 
        ;  ; :   ; :                  ;   :   ;: 
        :  :  ;  :  ;                : :  ;  : ; 
        ;\    :   ; :                ; ;     ; ; 
        : `."-;   :  ;              :  ;    /  ; 
         ;    -:   ; :              ;  : .-"   : 
         :\     \  :  ;            : \.-"      : 
          ;`.    \  ; :            ;.'_..--  / ; 
          :  "-.  "-:  ;          :/."      .'  :
           \         \ :          ;/  __        :
            \       .-`.\        /t-""  ":-+.   :
             `.  .-"    `l    __/ /`. :  ; ; \  ;
               \   .-" .-"-.-"  .' .'j \  /   ;/ 
                \ / .-"   /.     .'.' ;_:'    ;  
                 :-""-.`./-.'     /    `.___.'   
                       \ `t  ._  /               
                        "-.t-._:' 
*/


if( ! class_exists( 'Impressiv' ) ) {
	class Impressiv {

		const ENDPOINT = 'full';

		/**
		 *
		 */
		public static function initialize() {
			add_action( 'init', array( __CLASS__, 'init' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'wp_enqueue_scripts' ) );
			add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
			add_filter( 'request', array( __CLASS__, 'request' ) );
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
			add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
			add_filter( 'page_row_actions', array( __CLASS__, 'post_row_actions' ), 10, 2 );
		}

		/**
		 *
		 */
		public static function post_row_actions( $actions, $post ) {
			$screen = get_current_screen();
			$post_type_object = get_post_type_object( $screen->post_type );
			$can_edit_post = current_user_can( $post_type_object->cap->edit_post, $post->ID );

			if ( $post_type_object->public ) {
				if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ) ) ) {
					if ( $can_edit_post )
						$actions['view_slide'] = '<a href="' . esc_url( add_query_arg( self::ENDPOINT, 'true', add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'Preview Slide' ) . '</a>';
				} elseif ( 'trash' != $post->post_status ) {
					$actions['view_slide'] = '<a href="' . esc_url( add_query_arg( self::ENDPOINT, 'true', get_permalink( $post->ID ) ) ) . '#/' . sanitize_title( $post->post_title ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'View Slide' ) . '</a>';
				}
			}

			return $actions;
		}

		/**
		 *
		 */
		public static function add_meta_boxes() {
			add_meta_box( 'slide_attributes', __( 'Slide Attributes', 'impressive' ), array( __CLASS__, 'slide_attributes' ), 'presentation' );
		}

		/**
		 *
		 */
		public static function slide_attributes( $post ) {

			foreach( self::get_attrs() as $key => $value ) {
				$$key = get_post_meta( get_the_ID(), $key, true );
				?>
				<p>
					<label for="<?php echo $key; ?>"><?php echo $key; ?>:</label>
					<input class="widefat" type="text" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo esc_attr( $$key ) ?>">
				</p>
				<?php
			}
			
			wp_nonce_field( plugin_basename( __FILE__ ), 'impressiv_nonce' );
		}

		/**
		 *
		 */
		public static function save_post( $post_id, $post ) {
			//
			if ( defined( 'DOING_AJAX' ) )
				return $post_id;

			if ( wp_is_post_autosave( $post_id ) )
				return $post_id;

			if ( wp_is_post_revision( $post_id ) )
				return $post_id;

			if ( isset( $_POST['impressiv_nonce'] ) && ! wp_verify_nonce( $_POST['impressiv_nonce'], plugin_basename( __FILE__ ) ) )
				return $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;


			//
			foreach( self::get_attrs() as $key => $value ) {
				if( ! empty( $_POST[$key] ) )
					update_post_meta( $post_id, $key, (int)$_POST[$key] );
				else
					update_post_meta( $post_id, $key, $value );
			}


			//
			if( 0 == $post->post_parent )
				delete_transient( 'presentation_' . $post_id );
			else {
				delete_transient( 'presentation_' . $post->post_parent );
			}

			return $post_id;
		}

		/**
		 *
		 */
		public static function request( $vars ) {
			if( isset( $vars[self::ENDPOINT] ) ) $vars[self::ENDPOINT] = true;
			return $vars;
		}

		/**
		 *
		 */
		public static function template_redirect() {
			global $post;
			if( $post->post_type != 'presentation' ) return;

			if( get_query_var( self::ENDPOINT ) ) {
				add_filter( 'show_admin_bar', '__return_false' );
				require_once( dirname( __FILE__ ) . '/template.php' );
				exit;			
			}
		}

		/**
		 *
		 */
		public static function wp_enqueue_scripts() {
			if( get_query_var( self::ENDPOINT ) ) {
				wp_enqueue_script( 'impress-js', plugins_url( '/js/impress.js', __FILE__ ), array( 'jquery' ), '0.5.2', true );

				if ( file_exists( get_stylesheet_directory() . '/presentation.css') )
					wp_enqueue_style( 'impress-css', get_stylesheet_directory_uri() . '/presentation.css' );
				else
					wp_enqueue_style( 'impress-css', plugins_url( '/css/style.css', __FILE__ ) );
			}
		}

		/**
		 *
		 */
		public static function init() {
			register_post_type(
				'presentation',
				array(
					'labels' => array(
						'name' => _x( 'Presentation', 'post type general name' ),
						'singular_name' => _x( 'Presentation', 'post type singular name' ),
						'add_new' => _x('Add New', 'presentation'),
						'add_new_item' => __('Add New Presentation'),
						'edit_item' => __('Edit Presentation'),
						'new_item' => __('New Presentation'),
						'all_items' => __('All Presentations'),
						'view_item' => __('View Presentation'),
						'search_items' => __('Search Presentations'),
						'not_found' =>  __('No presentations found'),
						'not_found_in_trash' => __('No presentations found in Trash'), 
						'menu_name' => 'Presentations'
					),
					'public' => true,
					'hierarchical' => true,
					'supports' => array( 'title', 'editor', 'comments', 'revisions', 'page-attributes' )
				)
			);
			add_rewrite_endpoint( self::ENDPOINT, EP_PERMALINK );
		}

		/**
		 * Get an array of valid html attributes and their default values
		 */
		public static function get_attrs() {
			return array( 
				'data-x' => 0, 
				'data-y' => 0, 
				'data-z' => 0, 
				'data-scale' => 1, 
				'data-rotate' => 0, 
				'data-rotate-x' => 0, 
				'data-rotate-y' => 0, 
				'data-rotate-z' => 0 
			);
		}
	}

	/**
	 *
	 */
	register_activation_hook( __FILE__, 'impressiv_activation' );
	function impressiv_activation() {
		flush_rewrite_rules();
	}

	/**
	 *
	 */
	register_deactivation_hook( __FILE__, 'impressiv_deactivation' );
	function impressiv_deactivation() {
		flush_rewrite_rules();
	}
}
Impressiv::initialize();