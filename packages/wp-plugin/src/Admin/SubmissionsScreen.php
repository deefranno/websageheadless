<?php
/**
 * Read-only admin view of form messages.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Admin;

use WebSage\HeadlessCore\Contact\Submissions;
use WebSage\HeadlessCore\Content\PostTypes;
use WP_Post;

/**
 * List columns and a details box; everything is escaped on output.
 */
final class SubmissionsScreen {

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_filter( 'manage_' . PostTypes::SUBMISSION . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . PostTypes::SUBMISSION . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . PostTypes::SUBMISSION, array( self::class, 'meta_box' ) );
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'        => $columns['cb'] ?? '',
			'title'     => __( 'Name', 'websage-headless' ),
			'wsh_email' => __( 'Email', 'websage-headless' ),
			'wsh_mail'  => __( 'Notification', 'websage-headless' ),
			'date'      => __( 'Received', 'websage-headless' ),
		);
	}

	/**
	 * Column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Submission ID.
	 */
	public static function column( string $column, int $post_id ): void {
		if ( 'wsh_email' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, Submissions::EMAIL, true ) );
		} elseif ( 'wsh_mail' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, Submissions::MAIL_STATUS, true ) );
		}
	}

	/**
	 * Adds the details box.
	 */
	public static function meta_box(): void {
		add_meta_box( 'wsh_submission', __( 'Message', 'websage-headless' ), array( self::class, 'render' ), PostTypes::SUBMISSION, 'normal', 'high' );
	}

	/**
	 * Renders the message.
	 *
	 * @param WP_Post $post Submission.
	 */
	public static function render( WP_Post $post ): void {
		printf(
			'<p><strong>%s</strong> %s</p><p><strong>%s</strong> <a href="mailto:%s">%s</a></p><div style="white-space:pre-wrap">%s</div>',
			esc_html__( 'Name:', 'websage-headless' ),
			esc_html( $post->post_title ),
			esc_html__( 'Email:', 'websage-headless' ),
			esc_attr( (string) get_post_meta( $post->ID, Submissions::EMAIL, true ) ),
			esc_html( (string) get_post_meta( $post->ID, Submissions::EMAIL, true ) ),
			esc_html( $post->post_content )
		);
	}
}
