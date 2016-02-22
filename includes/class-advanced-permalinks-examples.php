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
			$this->add_custom_rewrite_rules();

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
		 *
		 * @param  string   $url   original URL
		 * @param  object   $post  the WP_Post object
		 * @return string          updated URL
		 */
		function pre_post_permalink( $url, $post ) {

			if ( ! empty( $post->post_parent ) ) {
				$parent = get_post( $post->post_parent );
				if ( ! empty( $parent ) && 'btv-show' === $parent->post_type ) {
					// we only need the path and the rewrite tags here, not the full site URL
					$url = "/shows/{$parent->post_name}/blog/%year%/%monthnum%/%postname%";
				}
			}

			return $url;
		}


		/**
		 * Filter for creating custom permalinks for 'page' post types (note: page_id, not page)
		 * https://codex.wordpress.org/Plugin_API/Filter_Reference/page_link
		 */
		function page_permalink( $url, $page_id ) {

			// creates a URL if this page is assigned as the About page for a show
			// url: /shows/game-of-thrones/about
			// rewrite rule: '^shows/([^/]+)/about/?$', 'index.php?btv-show=$matches[1]&_rule=about'

			// run a post meta query to see if this is asigned to a show
			$show_page = $this->get_show_by_about_page( $page_id );
			if ( ! empty( $show_page ) ) {
				$url = user_trailingslashit( trailingslashit( $this->get_show_permalink( $show_page ) ) . 'about' );
			}

			return $url;
		}


		/**
		 * Filter for creating custom permalinks for 'page' post types
		 * https://codex.wordpress.org/Plugin_API/Filter_Reference/post_type_link
		 */
		function custom_post_type_permalink( $url, $post ) {

			switch ( $post->post_type ) {

				case 'btv-show':
					$url = $this->get_show_permalink( $post );
					break;

				case 'btv-season':
					$url = $this->get_season_permalink( $post );
					break;

				case 'btv-episode':
					$url = $this->get_episode_permalink( $post );
					break;

			}

			return $url;

		}

		/**
		 * Gets the permalink for a show ( /shows/show-name )
		 */
		function get_show_permalink( $post ) {
			$post = get_post( $post );
			return user_trailingslashit( home_url( "/shows/{$post->post_name}" ) );
		}

		/**
		 * Gets the permalink for a season ( /shows/show-name/season-name )
		 */
		function get_season_permalink( $post ) {
			$post = get_post( $post );
			$show_url = untrailingslashit( $this->get_show_permalink( $post->post_parent ) );
			return user_trailingslashit( "{$show_url}/{$post->post_name}" );
		}

		/**
		 * Gets the permalink for an episode ( /shows/show-name/episode-name )
		 */
		function get_episode_permalink( $post ) {
			$post = get_post( $post );
			$season_url = untrailingslashit( $this->get_season_permalink( $post->post_parent ) );
			return user_trailingslashit( "{$season_url}/{$post->post_name}" );
		}

		/**
		 * Gets the permalink for a show's About page ( /shows/show-name/about )
		 */
		function get_show_about_permalink( $post ) {
			$post = get_post( $post );
			$about_page_id = get_post_meta( $post->ID, '_about_page_id', true );
			if ( ! empty( $about_page_id ) ) {
				return user_trailingslashit( trailingslashit( $this->get_show_permalink( $post ) ) . '/about' );
			} else {
				return '';
			}
		}

		function add_custom_rewrite_rules() {

			add_rewrite_tag( '%monthnamefull%', '([^/]+)', '_monthnamefull=' );
			add_rewrite_tag( '%monthnameshort%', '([^/]+)', '_monthnameshort=' );


			// show, or maybe a genre
			// ex: /shows/game-of-thrones/
			add_rewrite_rule( '^shows/([^/]+)/?$', 'index.php?btv-show=$matches[1]', 'top' );


			// show aired by year
			// ex: /shows/aired/2015/
			add_rewrite_rule( '^shows/aired/([0-9]{4})/?$', array(
				'post_type'  => 'btv-show',
				'year'       => '$matches[1]',
				),
				'top'
			);


			// show, or maybe a genre
			// ex: /shows/game-of-thrones/
			add_rewrite_rule( '^shows/([^/]+)/?$', array(
				'btv-show'   => '$matches[1]',
				'_rule'      => 'show-or-genre'
				),
				'top'
			);

			// a blog post for a show
			// ex: /shows/game-of-thrones/blog/2015/02/season-6-air-date
			add_rewrite_rule( '^shows/[^/]+/[0-9]{4}/[0-9]{1,2}/([^/]+)/?$', array(
				'post_type'  => 'post',
				'name'       => '$matches[1]',
				),
				'top'
			);



			// a show's about page
			// ex: /shows/game-of-thrones/about
			add_rewrite_rule( '^shows/([^/]+)/about/?$', array(
				'btv-show'   => '$matches[1]',
				'_rule'      => 'about'
				),
				'top'
			);

			// shows tagged
			// ex: /shows/tagged/popular_currently-airing
			add_rewrite_rule( '^shows/tagged/([^/]+)/?$', array(
				'_tags'   => '$matches[1]',
				'_rule'   => 'tagged'
				),
				'top'
			);

			// an episode for a show
			// ex: /shows/game-of-thrones/season-1/winter-is-coming
			// ex: /shows/game-of-thrones/season-two/the-north-remembers
			add_rewrite_rule( '^shows/([^/]+)/([^/]+)/([^/]+)/?$', array(
				'btv-show'     => '$matches[1]',
				'btv-season'   => '$matches[2]',
				'btv-episode'  => '$matches[3]',
				'_rule'        => 'episode',
				),
				'top'
			);

			// season for a show
			// ex: /shows/game-of-thrones/season-1/
			// ex: /shows/game-of-thrones/season-two
			add_rewrite_rule( '^shows/([^/]+)/([^/]+)/?$', array(
				'btv-show'     => '$matches[1]',
				'btv-season'   => '$matches[2]',
				'_rule'        => 'season'
				),
				'top'
			);



			// only flush the rewrite rules if the version number has changed
			if ( Advanced_Permalinks_Examples::VERSION !== get_option( 'Advanced_Permalinks_Examples_Version' ) ) {
				flush_rewrite_rules();
				update_option( 'Advanced_Permalinks_Examples_Version', Advanced_Permalinks_Examples::VERSION );
			}

		}


		// https://developer.wordpress.org/reference/hooks/query_vars/
		function add_custom_query_vars( $vars ) {
			// you can also use add_rewrite_tag, but this is a bit easier
			$vars[] = '_rule';
			$vars[] = '_tags';
			return $vars;
		}


		public function query_handler( $query ) {

			// important, don't make any changes unless this is the main, frontend query
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			$rule          = $query->get( '_rule' );

			$show_slug     = $query->get( 'btv-show' );
			$season_slug   = $query->get( 'btv-season' );
			$episode_slug  = $query->get( 'btv-episode' );

			// alter the main query based on the rule

			if ( 'episode' === $rule ) {
				$show = $this->get_post_by_slug( $show_slug, 'btv-show' );

				if ( ! empty( $show ) ) {
					$season = $this->get_post_by_slug( $season_slug, 'btv-season', $show->ID );

					if ( ! empty( $season ) ) {
						$query->set( 'post_parent', $season->ID );
						return;
					}
				}
			}


			if ( 'season' === $rule ) {
				$show = $this->get_post_by_slug( $show_slug, 'btv-show' );

				if ( ! empty( $show ) ) {
					$query->set( 'post_parent', $show->ID );
					return;
				}
			}


			// check if the show slug is a genre
			// /shows/sci-fi or /shows/game-of-thrones
			if ( 'show-or-genre' === $rule ) {

				$genre_term = get_term_by( 'slug', $show_slug, 'btv-genre' );

				if ( ! empty( $genre_term ) ) {
					// reset the query to displays shows in the genre
					$query->parse_query( array(
						'post_type'   => 'btv-show',
						'tax_query'   => array(
							array(
								'taxonomy'   => 'btv-genre',
								'terms'      => $genre_term->term_id,
 								)
							)
						)
					);
					return;
				}

			}


			if ( 'about' === $rule ) {

				$show = $this->get_post_by_slug( $show_slug, 'btv-show' );

				// if the show's About page is set, reset the query to that page
				if ( ! empty( $show ) ) {
					$about_page_id = get_post_meta( $show->ID, '_about_page_id', true );
					if ( ! empty( $about_page_id  ) ) {

						$query->parse_query(
							array(
								'post_type' => 'page',
								'p' => $about_page_id,
								)
							);
						return;

					}
				}
			}


			if ( 'tagged' === $rule ) {
				$tags = explode( '_', $query->get( '_tags' ) );
				$args = array(
					'post_type'   => 'btv-show',
					'tax_query'   => array( 'OR' ),
					);

				// build multiple tags into the OR query
				foreach( $tags as $tag ) {
					$args['tax_query'][] = array(
						'taxonomy'   => 'btv-tag',
						'field'      => 'slug',
						'terms'      => $tag,
						);
				}

				$query->parse_query( $args );

				return;
			}



		}


		/**
		 * Replaces custom URL tags with values from the post
		 */
		function replace_custom_url_tags( $url, $post ) {

			// this is based on the code in core's link-template.php

			// a list of tags we'll search for in the URL
			$tags = array( '%monthnameshort%', '%monthnamefull%' );

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

			if ( 'btv-season' !== $post_type || 'publish' !== $post_status ) {
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


		function get_post_by_slug( $slug, $post_type, $post_parent_id = null ) {

			$args = array(
				'post_type'   => $post_type,
				'name'        => $slug,
				);

			if ( ! empty( $post_parent_id ) ) {
				$args['post_parent'] = $post_parent_id;
			}

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				return $query->posts[0];
			} else {
				return null;
			}
		}


		function get_show_by_about_page( $page_id ) {

			$query = new WP_Query( array(
				'post_type'    => 'btv-show',
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
				'rewrite' => false, // we will create custom rewrites for this
				);

			register_post_type( 'btv-show', $args );

			register_taxonomy( 'btv-genre', 'btv-show', array(
				'labels' => array(
					'name' => 'Genres',
					'singular_name' => 'Genre',
					'add_new_item' => 'Add New Genre',
					'new_item_name' => 'New Genre Name',
					),
				'hierarchical' => true,
				'rewrite' => false,
				)
			);

			register_taxonomy( 'btv-tag', 'btv-show', array(
				'labels' => array(
					'name' => 'Tags',
					'singular_name' => 'Tag',
					'add_new_item' => 'Add New Tag',
					'new_item_name' => 'New Tag Name',
					),
				'hierarchical' => false,
				'rewrite' => false,
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
			$results = array(
				'url'   => get_permalink( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) ),
			);
			wp_send_json( $results );
		}


	}

}
