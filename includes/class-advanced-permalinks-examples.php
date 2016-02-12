<?php

if ( ! class_exists( 'Advanced_Permalinks_Examples' ) ) {

	class Advanced_Permalinks_Examples {

		const VERSION = '1.0.0';

		/**
		 * Initialize our example code
		 *
		 * @return void
		 */
		public function init() {

			// custom post types for the example code
			$this->register_custom_post_types();

			// custom rewrite rules
			$this->add_rewrite_rules();

			// filter for creating permalinks
			add_filter( 'pre_post_link',         array( $this, 'pre_post_permalink' ), 10, 2 ); // posts
			add_filter( 'page_link',             array( $this, 'page_permalink' ), 10, 2 ); // pages
			add_filter( 'post_type_link',        array( $this, 'custom_post_type_permalink' ), 10, 2 ); // custom post types

			// filter for our custom URL tags, called after the pre_post_link runs
			add_filter( 'post_link',             array( $this, 'replace_custom_url_tags' ), 10, 2 );

			// filter for custom queries based on rewrite rules
			// note that this is an action, not a filter
			add_action( 'pre_get_posts',         array( $this, 'query_handler' ) );

			// filter for additional query vars
			add_filter( 'query_vars',            array( $this, 'add_custom_query_vars' ) );

			// lets us assign the same post slugs to season
			add_filter( 'wp_unique_post_slug',   array( $this, 'allow_duplicate_season_slugs' ), 20, 6 );

			// an admin-ajax endpoint is handy for testing permalink generation
			add_action( 'wp_ajax_generate-permalink',        array( $this, 'ajax_generate_permalink' ) );
			add_action( 'wp_ajax_nopriv_generate-permalink', array( $this, 'ajax_generate_permalink' ) );

		}


		/**
		 * Filter for creating custom permalinks for 'post' post types
		 * https://codex.wordpress.org/Plugin_API/Filter_Reference/post_link
		 *
		 * @param  string   $url   original URL
		 * @param  object   $post  the WP_Post object
		 * @return string          updated URL
		 */
		function pre_post_permalink( $url, $post ) {

			// create a new URL this post is associated with a show
			if ( ! empty( $post->post_parent ) ) {

				$parent = get_post( $post->post_parent );
				if ( ! empty( $parent ) && 'show' === $parent->post_type ) {
					// WordPress will automatically add the site URL after this filter is called,
					// so we only return the path. It will also run the post_link filter after this
					// which we've hooked into for our custom tags
					$url = "/shows/{$parent->post_name}/blog/%year%/%monthnamefull%/%postname%";
				}

			}


			return $url;
		}


		/**
		 * Filter for creating custom permalinks for 'page' post types
		 * https://codex.wordpress.org/Plugin_API/Filter_Reference/page_link
		 */
		function page_permalink( $url, $page ) {

			// creates a URL if this page is assigned as the About page for a show
			// url: /shows/game-of-thrones/about
			// rewrite rule: '^shows/([^/]+)/about/?$', 'index.php?show=$matches[1]&_subpage=about'

			// run a post meta query to see if this is asigned to a show
			$show_page = $this->get_show_by_about_page( $page->ID );
			if ( ! empty( $show_page ) ) {
				$url = home_url( "/shows/{$show_page->post_name}/about" );
			}

			return $url;

		}


		/**
		 * Filter for creating custom permalinks for 'page' post types
		 * https://codex.wordpress.org/Plugin_API/Filter_Reference/post_type_link
		 */
		function custom_post_type_permalink( $url, $post ) {

			if ( 'show' === $post->post_type ) {
				$url = home_url( "/shows/{$post->post_name}" );
			}

			if ( 'season' === $post->post_type && ! empty( $post->post_parent ) ) {

				$parent = get_post( $post->post_parent );
				if ( ! empty( $parent ) && 'show' === $parent->post_type ) {
					$url = home_url( "/shows/{$parent->post_name}/{$post->post_name}" );
				}

			}

			return $url;

		}



		function add_rewrite_rules() {

			// show, or maybe a genre
			// ex: /shows/game-of-thrones
			// ex: /shows/fantasy
			add_rewrite_rule( '^shows/([^/]+)/?$', 'index.php?show=$matches[1]&_subpage=unknown', 'top' );

			// a show's about page
			// ex: /shows/game-of-thrones/about
			// WordPress 4.4 has the ability to take an array of params instead of a querystring
			add_rewrite_rule( '^shows/([^/]+)/about/?$', array(
				'show'       => '$matches[1]',
				'_subpage'   => 'about'
				),
				'top'
			);

			// a season for a show
			// ex: /shows/game-of-thrones/season-1
			// ex: /shows/game-of-thrones/season-two

			add_rewrite_tag( '%show%', '([^/]+)', 'show=' );
			add_rewrite_tag( '%season%', '([^/]+)', 'season=' );
			add_permastruct( 'show/season', '/shows/%show%/%season%' );

			// add_permastruct is another way of doing add_rewrite_rule
			// add_rewrite_rule( '^shows/([^/]+)/([^/]+)/?$', array(
			// 	'show'       => '$matches[1]',
			// 	'season'     => '$matches[2]',
			// 	),
			// 	'top'
			// );



			// only flush the rewrite rules if the version number has changed
			if ( Advanced_Permalinks_Examples::VERSION !== get_option( 'Advanced_Permalinks_Examples_Version' ) ) {
				flush_rewrite_rules();
				update_option( 'Advanced_Permalinks_Examples_Version', Advanced_Permalinks_Examples::VERSION );
			}

		}


		function add_custom_query_vars( $vars ) {
			// you can also use add_rewrite_tag, but this is a bit easier
			$vars[] = 'genre';
			$vars[] = '_subpage';
			return $vars;
		}


		public function query_handler( $query ) {

			// important, don't make any changes unless this is the main, frontend query
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			if ( '1' === filter_input( INPUT_GET, 'debug', FILTER_SANITIZE_STRING ) ) {
				// lets us debug the query before we modify it
				wp_send_json( $query );
			}

			$show_slug     = $query->get( 'show' );
			$season_slug   = $query->get( 'season' );
			$subpage       = $query->get( '_subpage' );


			// update the query for show's subpage
			if ( ! empty( $show_slug ) ) {

				$show_page = $this->get_show_by_slug( $show_slug );

				if ( ! empty( $season_slug ) ) {
					// if this is a season page
					// find the show page

					if ( ! empty( $show_page ) ) {
						// no need to reset the query, just add the show as the post parent
						$query->set( 'post_parent', $show_page->ID );
					}

				}


				// if it's a subpage
				switch( $subpage ) {
					case 'about':
						// find the show page

						if ( ! empty( $show_page ) ) {

							// if the show's About page is set, reset the query to that page
							$about_page_id = get_post_meta( $show_page->ID, '_about_page_id', true );
							if ( ! empty( $about_page_id  ) ) {

								$query->parse_query(
									array(
										'post_type' => 'page',
										'p' => $about_page_id
										)
									);

							}

						}
						break;

					case 'unknown':
						// this could be either a show, or a genre

						// see if this is a genre term
						$term = get_term_by( 'slug', $show_slug, 'genre' );
						if ( ! empty( $term ) ) {

							// reset the query to a taxonomy query
							$query->parse_query( array(
								'tax_query' => array(
									array(
										'taxonomy' => 'genre',
										'field' => 'slug',
										'terms' => $term->slug,
										),
									),
								)
							);

						}

						// if it's not a term, the query will default to searching the show custom post type

						break;

				}


			}


			if ( '2' === filter_input( INPUT_GET, 'debug', FILTER_SANITIZE_STRING ) ) {
				// lets us debug the query after we modify it
				wp_send_json( $query );
			}

		}


		function replace_custom_url_tags( $url, $post ) {

			// this is based on the code in core's link-template.php

			// a list of tags we'll search for in the URL
			$tags = array(
				'%monthnameshort%',
				'%monthnamefull%',
				);

			// add the custom date fields
			$fields = explode( " ", date( 'M F', strtotime( $post->post_date ) ) );

			// convert everything to lowerecase
			$fields = array_map( 'strtolower', $fields );

			// return the URL with tags replaced by data from the post
			return str_replace( $tags, $fields, $url );

		}


		/**
		 * Allows duplicate post slugs for the season custom post type between different post parents
		 *
		 * @param string $slug          The post slug.
		 * @param int    $post_ID       Post ID.
		 * @param string $post_status   The post status.
		 * @param string $post_type     Post type.
		 * @param int    $post_parent   Post parent ID
		 * @param string $original_slug The original post slug.
		 */
		function allow_duplicate_season_slugs( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {

			global $wpdb;

			if ( 'season' !== $post_type || 'publish' !== $post_status ) {
				return $slug;
			}

			$check_sql       = "
				SELECT post_name
				FROM $wpdb->posts
				WHERE post_name = %s
				AND post_type = %s
				AND ID != %d
				AND post_parent = %d
				LIMIT 1
			";

			// if we're adding a new season
			if ( empty( $post_parent ) && ! empty( $_POST['_post_for_show_id'] ) ) {
				$post_parent = absint( $_POST['_post_for_show_id'] );
			}

			$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_id, $post_parent ) );
			if ( ! empty( $post_name_check ) ) {

				$suffix = 2;
				do {
					$alt_post_name   = substr( $original_slug, 0, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
					$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_id, $post_parent ) );
					$suffix ++;
				} while ( ! empty( $post_name_check ) );
				$slug = $alt_post_name;

			} else {
				$slug = $original_slug;
			}

			return $slug;

		}


		function get_show_by_slug( $show_slug ) {

			$query = new WP_Query( array(
				'post_type'   => 'show',
				'name'        => $show_slug,
				)
			);

			if ( $query->have_posts() ) {
				return $query->posts[0];
			} else {
				return null;
			}
		}


		function get_show_by_about_page( $page_id ) {

			$query = new WP_Query( array(
				'post_type'    => 'show',
				'meta_key'     => '_about_page_id',
				'meta_value'   => $page_id,
				)
			);

			if ( $query->have_posts() ) {
				return $query->posts[0];
			} else {
				return null;
			}
		}


		/**
		 * Registers custom post types for our example (show, season, episode, person)
		 *
		 * @return void
		 */
		function register_custom_post_types() {

			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Shows',
					'singular_name' => 'Show'
					),
				'rewrite' => array(
					'slug' => 'shows'
					),
				'rewrite' => false, // we will create custom rewrites for this
				);

			register_post_type( 'btv-show', $args );

			register_taxonomy( 'genre', 'show', array(
				'name' => 'Genres',
				'singular_name' => 'Genre',
				'hierarchical' => true,
				'rewrite' => array(
					'slug' => 'shows'
					),
				)
			);


			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Seasons',
					'singular_name' => 'Season'
					),
				'rewrite' => false, // we will create custom rewrites for this
				);

			register_post_type( 'btv-season', $args );


			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Episodes',
					'singular_name' => 'Episode'
					),
				'rewrite' => false, // we will create custom rewrites for this
				);

			register_post_type( 'btv-episode', $args );

		}


		function ajax_generate_permalink() {
			wp_send_json( get_permalink( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) ) );
		}


	}

}
