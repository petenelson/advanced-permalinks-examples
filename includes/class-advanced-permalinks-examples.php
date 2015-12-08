<?php

if ( ! class_exists( 'Advanced_Permalinks_Examples' ) ) {

	class Advanced_Permalinks_Examples {

		public function init() {

			$this->register_custom_post_types();

			$this->rewrite_rules();

			add_filter( 'post_type_link', array( $this, 'create_permalink' ), 10, 2 );

			add_action( 'pre_get_posts', array( $this, 'query_handler' ) );



		}

		/**
		 * Filter for creating custom permalinks
		 * @param  string $url  original URL
		 * @param  object $post the WP_Post object
		 * @return string       updated URL
		 */
		function create_permalink( $url, $post ) {

			// https://codex.wordpress.org/Plugin_API/Filter_Reference/post_type_link

			return $url;
		}


		function rewrite_rules() {



		}


		public function query_handler( $query ) {

		}


		function register_custom_post_types() {

			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Shows',
					'singular_name' => 'Show'
					),
				);

			register_post_type( 'show', $args );


			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Seasons',
					'singular_name' => 'Season'
					),
				);

			register_post_type( 'season', $args );

			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Episodes',
					'singular_name' => 'Episode'
					),
				);

			register_post_type( 'episode', $args );


			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'People',
					'singular_name' => 'Person'
					),
				);

			register_post_type( 'person', $args );

		}


	}

}