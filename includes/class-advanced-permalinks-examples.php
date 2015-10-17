<?php

if ( ! class_exists( 'Advanced_Permalinks_Examples' ) ) {

	class Advanced_Permalinks_Examples {

		public function init() {

			$this->register_custom_post_types();

			$this->rewrite_rules();

			add_filter( 'post_type_link', array( $this, 'create_permalink' ), 10, 2 );

			add_action( 'pre_get_posts', array( $this, 'query_handler' ) );



		}

		function create_permalink( $url, $post ) {



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
					'name' => 'Meats',
					'singular_name' => 'Meat'
					),
				);

			register_post_type( 'meat', $args );


			$args = array(
				'public' => true,
				'labels' => array(
					'name' => 'Vegetables',
					'singular_name' => 'Vegetable'
					),
				);

			register_post_type( 'vegetable', $args );

		}


	}

}