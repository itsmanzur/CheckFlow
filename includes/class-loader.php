<?php
/**
 * Central hook loader.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Loader {

	/** @var array<int, array<string, mixed>> */
	private $actions = array();

	/** @var array<int, array<string, mixed>> */
	private $filters = array();

	/**
	 * Register action callback.
	 *
	 * @param string $hook Hook name.
	 * @param object $component Object instance.
	 * @param string $callback Method name.
	 * @param int    $priority Priority.
	 * @param int    $accepted_args Args count.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register filter callback.
	 *
	 * @param string $hook Hook name.
	 * @param object $component Object instance.
	 * @param string $callback Method name.
	 * @param int    $priority Priority.
	 * @param int    $accepted_args Args count.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Execute all registered actions/filters.
	 */
	public function run() {
		foreach ( $this->filters as $f ) {
			add_filter( $f['hook'], array( $f['component'], $f['callback'] ), (int) $f['priority'], (int) $f['accepted_args'] );
		}
		foreach ( $this->actions as $a ) {
			add_action( $a['hook'], array( $a['component'], $a['callback'] ), (int) $a['priority'], (int) $a['accepted_args'] );
		}
	}
}
