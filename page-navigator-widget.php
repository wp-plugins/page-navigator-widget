<?php
/*
Plugin Name: Page Navigator Widget
Description: The page widget that works as you want it to be
Version: 1.3
Author: J.N. Breetvelt a.k.a OpaJaap
Author URI: http://www.opajaap.nl/
Plugin URI: http://wordpress.org/extend/plugins/page-navigator-widget
*/

load_plugin_textdomain('pnw', 'wp-content/plugins/page-navigator-widget/langs/', 'page-navigator-widget/langs/');
/**
 * PageNavigatorWidget Class
 */
class PageNavigatorWidget extends WP_Widget {
    /** constructor */
    function PageNavigatorWidget() {
        parent::WP_Widget(false, $name = 'Page Navigator Widget');	
		$widget_ops = array('classname' => 'widget_pages_plus', 'description' => __( 'Your blog&#8217;s WordPress Pages Menu', 'pnw') );	//
		$this->WP_Widget('pages_plus', __('Page Navigator', 'pnw'), $widget_ops);															//
    }

	/** @see WP_Widget::widget */
    function widget($args, $instance) {		
		global $wpdb;
		global $widget_content;
		global $page_ancestors;
		global $exclude_array;

        extract( $args );
        
 		$title = apply_filters('widget_title', empty( $instance['title'] ) ? __( 'Pages Menu', 'pnw' ) : $instance['title']);
		$sortby = empty( $instance['sortby'] ) ? 'menu_order' : $instance['sortby'];
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		$addhome = empty( $instance['addhome'] ) ? 'never' : $instance['addhome'];

		if ( $sortby == 'menu_order' )
			$sortby = 'menu_order, post_title';
       
		// Find all ancestor pages
		$page_ancestors = array();
		if (!is_page()) {
			$page_ancestors['0'] = '0';	// None, we are not on a page
		}
		else {
			$index = '0';
			if (isset($_REQUEST['page_id'])) $page = $_REQUEST['page_id'];
	else $page = '0';	// Fix?
			$page_ancestors[$index] = $page;	// Yep, got one
			while ($page != '0') {			// Are there more?
				$index++;
				$query = "SELECT post_parent FROM " . $wpdb->posts . " WHERE post_type = 'page' AND post_status = 'publish' AND id = " . $page . " LIMIT 0,1";
				if ($page = $wpdb->get_var($query)) {			// Got another or zero
					$page_ancestors[$index] = $page;
				}
				else {
					$page = '0';
					$page_ancestors[$index] = $page;
				}
			}
		}
		// Explode exclusions
		$exclude_array = explode(',' , $exclude);
	
		$widget_content = '';
	
		$this->pnw_get_pages('0', $sortby, $addhome);

		echo $before_widget . $before_title . $title . $after_title . $widget_content . $after_widget;
    }
	
	function pnw_get_pages($root_id = 0, $sortby = 'post_title', $addhome = 'never') {
		global $wpdb;
		global $widget_content;
		global $page_ancestors;
		global $exclude_array;
	
		$query = "SELECT ID, post_title FROM " . $wpdb->posts . " WHERE post_type = 'page' AND post_status = 'publish' AND post_parent = " . $root_id . " ORDER BY " . $sortby . " ASC";
		$pages = $wpdb->get_results ($query, 'ARRAY_A');
		if ($pages) {
			$widget_content .= '<ul>';
			if ($root_id == 0) {
				if (($addhome == 'always') || ($addhome == 'pag' && is_page())) {	// Add Home link
					$widget_content .= '<li><a href="' . get_bloginfo('url') . '">Home</a></li>';
				}
			}
			foreach ($pages as $page) {
				if (!in_array( $page['ID'], $exclude_array)) {
					$widget_content .= '<li><a href="' . get_page_link($page['ID']) . '">' . __( $page['post_title'] ) . '</a></li>';
					$found = false;
					$i = 0;
					while ($i < count($page_ancestors)) {
						if ($page_ancestors[$i] == $page['ID']) $found = true;
						$i++;
					}
					if ($found) {	// Go a level deeper					
						$this->pnw_get_pages($page['ID'], $sortby, $addhome);
					}
				}
			}
			$widget_content .= '</ul>';
		}
	}

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		if ( in_array( $new_instance['sortby'], array( 'post_title', 'menu_order', 'ID' ) ) ) {
			$instance['sortby'] = $new_instance['sortby'];
		} else {
			$instance['sortby'] = 'menu_order';
		}
		$instance['exclude'] = strip_tags( $new_instance['exclude'] );
		if ( in_array( $new_instance['addhome'], array( 'always', 'pag', 'never' ))) {
			$instance['addhome'] = $new_instance['addhome'];
		} else {
			$instance['addhome'] = 'never';
		}

        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'sortby' => 'post_title', 'title' => '', 'exclude' => '', 'addhome' => 'never') );
		$title = esc_attr( $instance['title'] );
		$exclude = esc_attr( $instance['exclude'] );
	?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'pnw'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<label for="<?php echo $this->get_field_id('sortby'); ?>"><?php _e( 'Sort by:', 'pnw' ); ?></label>
			<select name="<?php echo $this->get_field_name('sortby'); ?>" id="<?php echo $this->get_field_id('sortby'); ?>" class="widefat">
				<option value="post_title"<?php selected( $instance['sortby'], 'post_title' ); ?>><?php _e('Page title', 'pnw'); ?></option>
				<option value="menu_order"<?php selected( $instance['sortby'], 'menu_order' ); ?>><?php _e('Page order', 'pnw'); ?></option>
				<option value="ID"<?php selected( $instance['sortby'], 'ID' ); ?>><?php _e( 'Page ID', 'pnw' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('exclude'); ?>"><?php _e( 'Exclude:', 'pnw' ); ?></label> <input type="text" value="<?php echo $exclude; ?>" name="<?php echo $this->get_field_name('exclude'); ?>" id="<?php echo $this->get_field_id('exclude'); ?>" class="widefat" />
			<br />
			<small><?php _e( 'Page IDs, separated by commas.', 'pnw' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('addhome'); ?>"><?php _e( 'Add a Home link:', 'pnw' ); ?></label>
			<select name="<?php echo $this->get_field_name('addhome'); ?>" id="<?php echo $this->get_field_id('addhome'); ?>" class="widefat">
				<option value="always"<?php selected( $instance['addhome'], 'always' ); ?>><?php _e('Always', 'pnw'); ?></option>
				<option value="pag"<?php selected( $instance['addhome'], 'pag' ); ?>><?php _e('When viewing a page only', 'pnw'); ?></option>
				<option value="never"<?php selected( $instance['addhome'], 'never' ); ?>><?php _e('Never', 'pnw'); ?></option>
			</select>
			<br />
			<small><?php _e( 'Select when you want a Home-link to be added.', 'pnw'); ?></small>
		</p>
<?php
    }

} // class PageNavigatorWidget

// register PageNavigatorWidget widget
add_action('widgets_init', create_function('', 'return register_widget("PageNavigatorWidget");'));
?>
