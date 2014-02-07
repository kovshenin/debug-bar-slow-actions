<?php
if ( ! defined( 'ABSPATH' ) )
    die();

class Debug_Bar_Slow_Actions_Panel extends Debug_Bar_Panel {
    private $tab_name;
    private $tab;
    private $callback;

    public function set_callback( $callback ) {
        $this->callback = $callback;
    }

    public function prerender() {
        $this->set_visible( true );
    }

    public function render() {
        echo call_user_func( $this->callback );
    }
}