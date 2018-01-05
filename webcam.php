<?php

/*
@link              http://tim.gremalm.se/
@since             1.0.0
@package           Webcamswidget

@wordpress-plugin
Plugin Name:       WebcamsWidget
Plugin URI:        https://github.com/ChalmersRobotics/WebcamsWidget
Description:       A widget for Wordpress where you can display images from webcams. Add new webcams in the main menu Webcams.
Version:           1.0.0
Author:            Tim Gremalm
Author URI:        http://tim.gremalm.se/
License:           GPL-3.0+
License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
Text Domain:       webcamswidget
Domain Path:       /languages
*/

//Widget
class Webcams_Widget extends WP_Widget {
	function __construct() {
		parent::__construct(
			'webcams_widget', // Base ID
			esc_html__( 'Webcams Widget', 'text_domain' ), // Name
			array( 'description' => esc_html__( 'Adds webcams in a widget', 'text_domain' ), )
		);
	}

	//Front-end display of widget
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		//Get webcam's
		$webcams = get_posts(array('post_type' => 'webcam',
									'posts_per_page'=>-1,
									'meta_key' => 'webcamsortorder',
									'orderby' => 'meta_value_num',
									'order' => 'ASC'
								));
		//var_dump($webcams);
		$this->drawZoomPlaceholder();
		foreach($webcams as $webcam) {
			$webcamurl = get_post_meta( $webcam->ID, 'webcamurl', true );
			$webcammovementurl = get_post_meta( $webcam->ID, 'webcammovementurl', true );
			//var_dump($webcamurl);
			//var_dump($webcammovementurl);
			$this->drawWebcamImg( $webcam->post_title, $webcamurl, $webcammovementurl);
		}
		/*
		*/
		$this->insertZoomScript();
		echo $args['after_widget'];
	}
	public function drawWebcamImg($title, $webcamurl, $webcammovementurl) {
		?>
		<div class="webcamFrame">
			<img class="webcam" style="cursor: pointer; width: 100%;" onclick="zoom(this)" alt="A picture of the webcam <?php echo $title; ?> " src="<?php echo $webcamurl; ?>" />
			<img class="webcammovement" style="display: none;" alt="A picture of latest movement of webcam <?php echo $title; ?> " src="<?php echo $webcammovementurl; ?>" />
			<p class="movementfullscreenlink" style="cursor: pointer; color: blue; text-decoration: underline;" >Latest movement <?php echo $title; ?></p>
		</div>
		<?php
	}
	public function drawZoomPlaceholder() {
		?>
		<div id="largepicplaceholder" style="position: fixed; display: none; width: 90%; margin: 0 auto; left: 5%; top: 5%;"></div>
		<?php
	}
	public function insertZoomScript() {
		?>
		<script type="text/javascript" language="javascript">// <![CDATA[
			function zoom(obj) {
				jQuery("#largepicplaceholder").html('<img id="largepic" class="webcam" style="cursor:pointer; width: 100%;" onclick="zoomOut()" src="'+jQuery(obj).attr("src")+'" alt="Click to zoom out" />');
				jQuery("#largepicplaceholder").fadeIn("slow");
			}
			function zoomOut() {
				jQuery("#largepicplaceholder").fadeOut("slow");
			}
			jQuery(function ($) {
				$('.movementfullscreenlink').click( function() {
					var obj = $(this).parent().children('.webcammovement');
					zoom(obj);
				} );
			});
			// ]]>
		</script>
		<?php
	}

	//Back-end widget form
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'text_domain' );
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'text_domain' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	//Sanitize widget form values as they are saved
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}
}
add_action('widgets_init', create_function('', 'return register_widget("Webcams_Widget");'));

//Meta box for post type webcam
function wpt_add_webcam_metaboxes( $post ) {
	//add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
	add_meta_box(
		'wpt_webcam',
		'Webcam parameters',
		'wpt_webcam',
		'webcam',
		'normal',
		'default'
	);
}
function wpt_webcam() {
	global $post;
	//Nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'webcam_fields' );
	//Parameter webcam url
	$webcamurl = get_post_meta( $post->ID, 'webcamurl', true );
	echo '<label>' . esc_attr_e( 'URL to webcam image:', 'text_domain' ) . '</label>';
	echo '<input type="text" name="webcamurl" value="' . esc_textarea( $webcamurl )  . '" class="widefat">';
	//Parameter for movement url
	$webcammovementurl = get_post_meta( $post->ID, 'webcammovementurl', true );
	echo '<label>' . esc_attr_e( 'URL to webcam image containing movement:', 'text_domain' ) . '</label>';
	echo '<input type="text" name="webcammovementurl" value="' . esc_textarea( $webcammovementurl )  . '" class="widefat">';
	//Parameter sort order
	$webcamsortorder = get_post_meta( $post->ID, 'webcamsortorder', true );
	echo '<label>' . esc_attr_e( 'Sorting order in the widget:', 'text_domain' ) . '</label>';
	echo '<input type="text" name="webcamsortorder" value="' . esc_textarea( $webcamsortorder )  . '" class="widefat">';
}
function save_meta_box_webcam( $post_id, $post ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	//Verify this came from the our screen and with proper authorization, because save_post can be triggered at other times.
	if ( ! wp_verify_nonce( $_POST['webcam_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	if ( ! isset( $_POST['webcamurl'] ) || ! isset( $_POST['webcammovementurl'] ) ) {
		return $post_id;
	}

	//This sanitizes the data from the field and saves it into an array $webcam_meta
	$webcam_meta['webcamurl'] = esc_textarea( $_POST['webcamurl'] );
	$webcam_meta['webcammovementurl'] = esc_textarea( $_POST['webcammovementurl'] );
	$webcam_meta['webcamsortorder'] = esc_textarea( $_POST['webcamsortorder'] );

	foreach ( $webcam_meta as $key => $value ) :
		//Don't store custom data twice
		if ( 'revision' === $post->post_type ) {
			return;
		}
		if ( get_post_meta( $post_id, $key, false ) ) {
			update_post_meta( $post_id, $key, $value );
		} else {
			add_post_meta( $post_id, $key, $value);
		}
		if ( ! $value ) {
			delete_post_meta( $post_id, $key );
		}
	endforeach;
}
add_action('save_post', 'save_meta_box_webcam', 1, 2);

//Post type webcam
function register_cpt_webcam() {
	$labels = array(
		'name' => __( 'Webcams', 'webcam' ),
		'singular_name' => __( 'Webcam', 'webcam' ),
		'add_new' => __( 'Add New', 'webcam' ),
		'add_new_item' => __( 'Add New Webcam', 'webcam' ),
		'edit_item' => __( 'Edit Webcam', 'webcam' ),
		'new_item' => __( 'New Webcam', 'webcam' ),
		'view_item' => __( 'View Webcam', 'webcam' ),
		'search_items' => __( 'Search Webcams', 'webcam' ),
		'not_found' => __( 'No webcams found', 'webcam' ),
		'not_found_in_trash' => __( 'No webcams found in Trash', 'webcam' ),
		'parent_item_colon' => __( 'Parent Webcam:', 'webcam' ),
		'menu_name' => __( 'Webcams', 'webcam' ),
	);

	$args = array(
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'List of webcams',
		'supports' => array( 'title' ),
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 70,
		'show_in_nav_menus' => true,
		'publicly_queryable' => true,
		'exclude_from_search' => true,
		'has_archive' => false,
		'query_var' => true,
		'can_export' => true,
		'rewrite' => true,
		'capability_type' => 'post',
		'register_meta_box_cb' => 'wpt_add_webcam_metaboxes',
	);

	register_post_type( 'webcam', $args );
}
add_action( 'init', 'register_cpt_webcam' );

