<?php
/*
Plugin Name: Advanced Permalinks Examples
Author: Pete Nelson (@GunGeekATX)
Version: 1.0.0
*/

require_once dirname( __FILE__ ) . '/includes/class-advanced-permalinks-examples.php';

$examples = new Advanced_Permalinks_Examples();
add_action( 'init', array( $examples, 'init' ) );
