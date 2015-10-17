<?php

if ( ! class_exists( 'Advanced_Permalinks_Examples' ) ) {

	class Advanced_Permalinks_Examples {

		public function plugins_loaded() {

			$this->register_custom_post_types();

			$this->rewrite_rules();



		}

		function rewrite_rules() {

		}

		function register_custom_post_types() {

		}

	}

}