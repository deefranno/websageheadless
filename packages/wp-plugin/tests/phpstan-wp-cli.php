<?php
/**
 * Minimal WP-CLI declarations for static analysis only (the published stub
 * package does not yet support WordPress 7 stubs). Never loaded at runtime.
 *
 * @package WebSage\HeadlessCore
 */

// phpcs:disable

class WP_CLI {
	/**
	 * @param string       $name     Command name.
	 * @param class-string $callable Command class.
	 */
	public static function add_command( string $name, string $callable ): bool {}

	/**
	 * @param string               $question   Question.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public static function confirm( string $question, array $assoc_args = array() ): void {}

	public static function success( string $message ): void {}
}
