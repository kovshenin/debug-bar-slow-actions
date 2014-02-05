<?php
/**
 * Plugin Name: Debug Bar Slow Actions
 * Description: Easily find the slowest actions and filters during a page request.
 * Version: 0.7
 * Author: Konstantin Kovshenin
 * Author URI: http://kovshenin.com
 * License: GPLv2 or later
 */

class Debug_Bar_Slow_Actions {
	public $start;
	public $flow;

	public $warning_threshold = 100;

	function __construct() {
		$this->start = microtime( true );
		$this->flow = array();

		ob_start( array( $this, 'ob_callback' ) );
		// add_action( 'shutdown', array( $this, 'shutdown' ) );

		add_action( 'all', array( $this, 'time_start' ) );
		add_filter( 'debug_bar_panels', array( $this, 'debug_bar_panels' ) );
	}

	function time_start() {
		if ( ! isset( $this->flow[ current_filter() ] ) ) {
			$this->flow[ current_filter() ] = array(
				'count' => 0,
				'stack' => array(),
				'time' => array(),
			);

			// @todo: add support for nesting filters.
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
		require_once( 'class-debug-bar-slow-actions-panel.php' );
		$panel = new Debug_Bar_Slow_Actions_Panel();
		$panel->set_tab( 'Slow Actions', array( $this, 'panel_callback' ) );
		$panels[] = $panel;
		return $panels;
	}

	function panel_callback() {
		echo '<div id="db-slow-actions-container">%%debug-bar-slow-actions-placeholder%%</div>';
	}

	function sort_actions_by_time( $a, $b ) {
		if ( $a['total'] == $b['total'] )
        	return 0;

    	return ( $a['total'] > $b['total'] ) ? -1 : 1;
	}

	function output() {
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
		}

		uasort( $this->flow, array( $this, 'sort_actions_by_time' ) );
		$slowest_action = reset( $this->flow );

		$table = '<table class="debug-bar-actions-list" style="width: 100%;">';
		$table .= '<tr>';
		$table .= '<th style="text-align: left;">Action or Filter</th>';
		$table .= '<th style="text-align: right;">Calls</th>';
		$table .= '<th style="text-align: right;">Per Call</th>';
		$table .= '<th style="text-align: right;">Total</th>';
		$table .= '</tr>';

		foreach ( array_slice( $this->flow, 0, 100 ) as $action => $data ) {
			$table .= '<tr>';
			$table .= sprintf( '<td>%s</td>', $action );
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
		// $output .= '<p>This table shows the top 100 slowest actions during this page load, the number of times each action was called, and the total execution time of each action. Note that nested action calls timing is currently not supported.</p>';
		$output .= $table;

		$output .= '
		<style>
			.debug-bar-actions-list {
				border-spacing: 0;
			}
			.debug-bar-actions-list td,
			.debug-bar-actions-list th {
				padding: 6px;
				border-bottom: solid 1px #ddd;
			}
			.debug-bar-actions-list tr:hover {
				background: #e8e8e8;
			}

			#db-slow-actions-container h3 {
				float: none;
				clear: both;
				font-family: georgia, times, serif;
				font-size: 22px;
				margin: 15px 10px 15px 0 !important;
			}

		</style>';

		return $output;
	}

	function shutdown() {
		print_r( $this->flow );
	}

	function ob_callback( $buffer ) {
		$buffer = str_replace( '%%debug-bar-slow-actions-placeholder%%', $this->output(), $buffer );
		return $buffer;
	}
}
new Debug_Bar_Slow_Actions;