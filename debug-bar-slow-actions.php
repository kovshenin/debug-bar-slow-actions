<?php
/**
 * Plugin Name: Debug Bar Slow Actions
 * Description: Easily find the slowest actions and filters during a page request.
 * Version: 0.8.2
 * Author: Konstantin Kovshenin
 * Author URI: http://kovshenin.com
 * License: GPLv2 or later
 */

class Debug_Bar_Slow_Actions {
	public $start;
	public $flow;

	function __construct() {
		$this->start = microtime( true );
		$this->flow = array();

		add_action( 'all', array( $this, 'time_start' ) );
		add_filter( 'debug_bar_panels', array( $this, 'debug_bar_panels' ), 9000 );
		// add_action( 'wp_footer', function() { print_r( $this->flow ); }, 9000 );
	}

	function time_start() {
		if ( ! isset( $this->flow[ current_filter() ] ) ) {
			$this->flow[ current_filter() ] = array(
				'count' => 0,
				'stack' => array(),
				'time' => array(),
				'callbacks' => array(),
			);

			// @todo: add support for nesting filters, see #17817
			add_action( current_filter(), array( $this, 'time_stop' ), 9000 );
		}

		$count = ++$this->flow[ current_filter() ]['count'];
		array_push( $this->flow[ current_filter() ]['stack'], array( 'start' => microtime( true ) ) );
	}

	function time_stop( $value ) {
		$time = array_pop( $this->flow[ current_filter() ]['stack'] );
		$time['stop'] = microtime( true );
		array_push( $this->flow[ current_filter() ]['time'], $time );

		// In case this was a filter.
		return $value;
	}

	function debug_bar_panels( $panels ) {
		require_once( dirname( __FILE__ ) . '/class-debug-bar-slow-actions-panel.php' );
		$panel = new Debug_Bar_Slow_Actions_Panel( 'Slow Actions' );
		$panel->set_callback( array( $this, 'panel_callback' ) );
		$panels[] = $panel;
		return $panels;
	}

	function panel_callback() {

		// Hack wp_footer: this callback is executed late into wp_footer, but not after, so
		// let's assume it is the last call in wp_footer and manually stop the timer, otherwise
		// we won't get a wp_footer entry in the output.
		if ( ! empty( $this->flow['wp_footer']['stack'] ) ) {
			$time = array_pop( $this->flow['wp_footer']['stack'] );
			if ( $time && empty( $time['stop'] ) ) {
				$time['stop'] = microtime( true );
				array_push( $this->flow['wp_footer']['time'], $time );
			}
		}

		printf( '<div id="dbsa-container">%s</div>', $this->output() );
	}

	function sort_actions_by_time( $a, $b ) {
		if ( $a['total'] == $b['total'] )
        	return 0;

    	return ( $a['total'] > $b['total'] ) ? -1 : 1;
	}

	function output() {
		global $wp_filter;

		$output = '';
		$total_actions = 0;
		$total_actions_time = 0;

		foreach ( $this->flow as $action => $data ) {
			$total = 0;
			foreach ( $data['time'] as $time )
				$total += ( $time['stop'] - $time['start'] ) * 1000;

			$this->flow[ $action ]['total'] = $total;
			$total_actions_time += $total;
			$total_actions += $data['count'];

			$this->flow[ $action ]['callbacks_count'] = 0;

			// Add all filter callbacks.
			foreach ( $wp_filter[ $action ] as $priority => $callbacks ) {
				if ( ! isset( $this->flow[ $action ]['callbacks'][ $priority ] ) ) {
					$this->flow[ $action ]['callbacks'][ $priority ] = array();
				}

				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && count( $callback['function'] == 2 ) ) {
						list( $object_or_class, $method ) = $callback['function'];
						if ( is_object( $object_or_class ) ) {
							$object_or_class = get_class( $object_or_class );
						}

						$this->flow[ $action ]['callbacks'][ $priority ][] = sprintf( '%s::%s', $object_or_class, $method );
					} elseif ( is_object( $callback['function'] ) ) {
						// Probably an anonymous function.
						$this->flow[ $action ]['callbacks'][ $priority ][] = get_class( $callback['function'] );
					} else {
						$this->flow[ $action ]['callbacks'][ $priority ][] = $callback['function'];
					}

					$this->flow[ $action ]['callbacks_count']++;
				}
			}
		}

		uasort( $this->flow, array( $this, 'sort_actions_by_time' ) );
		$slowest_action = reset( $this->flow );

		$table = '<table>';
		$table .= '<tr>';
		$table .= '<th>Action or Filter</th>';
		$table .= '<th style="text-align: right;">Callbacks</th>';
		$table .= '<th style="text-align: right;">Calls</th>';
		$table .= '<th style="text-align: right;">Per Call</th>';
		$table .= '<th style="text-align: right;">Total</th>';
		$table .= '</tr>';

		foreach ( array_slice( $this->flow, 0, 100 ) as $action => $data ) {

			$callbacks_output = '<ol class="dbsa-callbacks">';
			foreach ( $data['callbacks'] as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$callbacks_output .= sprintf( '<li value="%d">%s</li>', $priority, $callback );
				}
			}
			$callbacks_output .= '</ol>';

			$table .= '<tr>';
			$table .= sprintf( '<td><span class="dbsa-action">%s</span> %s</td>', $action, $callbacks_output );
			$table .= sprintf( '<td style="text-align: right;">%d</td>', $data['callbacks_count'] );
			$table .= sprintf( '<td style="text-align: right;">%d</td>', $data['count'] );
			$table .= sprintf( '<td style="text-align: right;">%.2fms</td>', $data['total'] / $data['count'] );
			$table .= sprintf( '<td style="text-align: right;">%.2fms</td>', $data['total'] );
			$table .= '</tr>';
		}
		$table .= '</table>';

		$output .= sprintf( '<h2><span>Unique actions:</span> %d</h2>', count( $this->flow ) );
		$output .= sprintf( '<h2><span>Total actions:</span> %d</h2>', $total_actions );
		$output .= sprintf( '<h2><span>Actions Execution time:</span> %.2fms</h2>', $total_actions_time );
		$output .= sprintf( '<h2><span>Slowest Action:</span> %.2fms</h2>', $slowest_action['total'] );

		$output .= '<div class="clear"></div>';
		$output .= '<h3>Slow Actions</h3>';

		$output .= $table;

		$output .= <<<EOD
		<style>
			#dbsa-container table {
				border-spacing: 0;
				width: 100%;
			}
			#dbsa-container td,
			#dbsa-container th {
				padding: 6px;
				border-bottom: solid 1px #ddd;
			}
			#dbsa-container td {
				font: 12px Monaco, 'Courier New', Courier, Fixed !important;
				line-height: 180% !important;
				cursor: pointer;
				vertical-align: top;
			}
			#dbsa-container tr:hover {
				background: #e8e8e8;
			}
			#dbsa-container th {
				font-weight: 600;
			}
			#dbsa-container h3 {
				float: none;
				clear: both;
				font-family: georgia, times, serif;
				font-size: 22px;
				margin: 15px 10px 15px 0 !important;
			}
			ol.dbsa-callbacks {
				list-style: decimal;
				padding-left: 50px;
				color: #777;
				margin-top: 10px;
				display: none;
			}
			.dbsa-expanded ol.dbsa-callbacks {
				display: block;
			}
			.dbsa-action:before {
				content: '\\f140';
				display: inline-block;
				-webkit-font-smoothing: antialiased;
				font: normal 20px/1 'dashicons';
				vertical-align: top;
				color: #aaa;
				margin-right: 4px;
				margin-left: -6px;
			}
			.dbsa-expanded .dbsa-action:before {
				content: '\\f142';
			}
		</style>
EOD;
		$output .= <<<EOD
		<script>
			(function($){
				$('#dbsa-container td').on('click', function() {
					$(this).parents('tr').toggleClass('dbsa-expanded');
				});
			}(jQuery));
		</script>
EOD;

		return $output;
	}
}
new Debug_Bar_Slow_Actions;