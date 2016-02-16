<?php

if ( ! class_exists( 'Advanced_Permalinks_Examples_Post_Meta' ) ) {

	class Advanced_Permalinks_Examples_Admin {

		/**
		 * Initialize the admin portion of the plugin
		 * @return void
		 */
		public function admin_init() {

			$this->add_meta_boxes();

			// custom columns in season
			add_filter('manage_edit-btv-season_columns',         array( $this, 'add_custom_season_columns' ) );
			add_action('manage_btv-season_posts_custom_column',  array( $this, 'render_custom_season_column'), 10, 2 );

		}

		private function add_meta_boxes() {

			// add a metabox to the show CPT to select the About page
			add_meta_box(
				'show-about',
				'About Page',
				array( $this, 'about_page_metabox' ),
				'btv-show',
				'side'
				);

			// save the show's About page
			add_action( 'save_post_btv-show', array( $this, 'save_about_page_metabox' ), 10, 2 );

			// add a metabox to allow associating items to a show
			add_meta_box(
				'post-for-show',
				'Show',
				array( $this, 'post_for_show_metabox' ),
				array( 'post', 'btv-season' ),
				'side'
				);

			// save the post's Show parent
			add_action( 'save_post', array( $this, 'save_post_for_show_metabox' ), 10, 2 );

		}


		public function about_page_metabox( $post ) {

			wp_nonce_field( 'show-about-page', 'show-about-page-nonce' );

			$about_page_id = get_post_meta( $post->ID, '_about_page_id', true );
			$page_query = new WP_Query( array(
				'post_type'       => 'page',
				'posts_per_page'  => 100,
				'orderby'         => 'name',
				'order'           => 'ASC',
				)
			);

			?>
				<select name="_about_page_id">
					<option value="">-Select-</option>
					<?php foreach( $page_query->posts as $page ) : ?>
						<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $page->ID, $about_page_id ); ?>><?php echo esc_html( $page->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php
		}


		public function save_about_page_metabox( $post_id, $post ) {

			if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'show-about-page-nonce', FILTER_SANITIZE_STRING ), 'show-about-page' ) ) {
				return;
			}


			$about_page_id = filter_input( INPUT_POST, '_about_page_id', FILTER_SANITIZE_NUMBER_INT );
			if ( ! empty( $about_page_id ) ) {
				update_post_meta( $post_id, '_about_page_id', $about_page_id );
			} else {
				delete_post_meta( $post_id, '_about_page_id' );
			}
		}

		public function post_for_show_metabox( $post ) {

			wp_nonce_field( 'post-for-show', 'post-for-show-nonce' );

			$show_query = new WP_Query( array(
				'post_type'       => 'btv-show',
				'posts_per_page'  => 500,
				'orderby'         => 'name',
				'order'           => 'ASC',
				)
			);

			?>
				<select name="_post_for_show_id">
					<option value="">-Select-</option>
					<?php foreach( $show_query->posts as $show ) : ?>
						<option value="<?php echo esc_attr( $show->ID ); ?>" <?php selected( $show->ID, $post->post_parent ); ?>><?php echo esc_html( $show->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php
		}


		/**
		 * Associates a post with a show
		 */
		public function save_post_for_show_metabox( $post_id, $post ) {
			if ( ! in_array( $post->post_type, array( 'post', 'season' ) )  || ! wp_verify_nonce( filter_input( INPUT_POST, 'post-for-show-nonce', FILTER_SANITIZE_STRING ), 'post-for-show' ) ) {
				return;
			}

			$post_parent = filter_input( INPUT_POST, '_post_for_show_id', FILTER_SANITIZE_NUMBER_INT );
			if ( ! empty( $post_parent ) ) {
				$post->post_parent = $post_parent;

				// unhook this function temporarily so it isn't called again by the wp_update_post call
				remove_action( 'save_post', array( $this, 'save_post_for_show_metabox' ), 10, 2 );

				// save the updated post
				wp_update_post( $post );

				// reenable hook
				add_action( 'save_post', array( $this, 'save_post_for_show_metabox' ), 10, 2 );

			}

		}


		function add_custom_season_columns( $columns ) {

			$new_columns = array(
				'show' => 'Show',
				'slug' => 'Slug',
			);

			$offset = 2;
			$columns = array_slice( $columns, 0, $offset, true ) +
            	$new_columns +
            	array_slice( $columns, $offset, NULL, true );

			return $columns;

		}


		function render_custom_season_column( $name, $id ) {

			$value = '';

			switch ( $name ) {
				case 'slug':
					$value = get_post_field( 'post_name', $id );
					break;
				case 'show':
					$parent = get_post_field( 'post_parent', $id );
					if ( ! empty( $parent ) ) {
						$value = get_post_field( 'post_title', $parent );
					}
					break;

			}

			if ( ! empty( $value ) ) {
				echo esc_html( $value );
			}

		}


	}

}