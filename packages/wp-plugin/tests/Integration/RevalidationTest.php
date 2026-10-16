<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Revalidation\Dispatcher;
use WebSage\HeadlessCore\Revalidation\Signer;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

final class RevalidationTest extends ApiTestCase {

	/**
	 * Captured outgoing requests.
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	private array $sent = array();

	private int $status = 200;

	public function set_up(): void {
		parent::set_up();
		$this->sent   = array();
		$this->status = 200;
		delete_option( Dispatcher::JOBS_OPTION );
		add_filter( 'pre_http_request', array( $this, 'capture' ), 10, 3 );
	}

	/**
	 * @param mixed                $preempt Short-circuit value.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     URL.
	 * @return array<string, mixed>
	 */
	public function capture( mixed $preempt, array $args, string $url ): array {
		$this->sent[] = array(
			'url'  => $url,
			'args' => $args,
		);
		return array(
			'headers'  => array(),
			'body'     => '{}',
			'response' => array(
				'code'    => $this->status,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Tags of every queued event, merged.
	 *
	 * @return list<string>
	 */
	private function queued_tags(): array {
		$tags = array();
		foreach ( Dispatcher::pending() as $event ) {
			$tags = array_merge( $tags, $event['tags'] );
		}
		return $tags;
	}

	public function test_publishing_and_renaming_a_page_targets_old_and_new_paths(): void {
		$id = $this->page( array( 'post_name' => 'about' ) );
		$this->assertEqualsCanonicalizing( array( 'page:/about', 'routes' ), $this->queued_tags() );

		Dispatcher::discard();
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => 'about-us',
			)
		);
		$events = Dispatcher::pending();
		$this->assertSame( 'content.updated', $events[0]['event'] );
		$this->assertEqualsCanonicalizing( array( '/about', '/about-us' ), $events[0]['paths'] );
		$this->assertEqualsCanonicalizing( array( 'page:/about', 'page:/about-us', 'routes' ), $events[0]['tags'] );

		Dispatcher::discard();
		wp_trash_post( $id );
		$events = Dispatcher::pending();
		$this->assertSame( 'content.deleted', $events[0]['event'] );
		$this->assertContains( 'page:/about-us', $events[0]['tags'] );
	}

	public function test_posts_invalidate_the_post_list(): void {
		$this->post(
			array(
				'post_type' => 'post',
				'post_name' => 'hello',
			)
		);
		$this->assertEqualsCanonicalizing( array( 'post:hello', 'posts', 'routes' ), $this->queued_tags() );
	}

	public function test_drafts_queue_nothing(): void {
		$this->page(
			array(
				'post_name'   => 'wip',
				'post_status' => 'draft',
			)
		);
		$this->assertSame( array(), Dispatcher::pending() );
	}

	public function test_library_changes_reach_the_pages_that_use_them(): void {
		$cta = $this->post(
			array(
				'post_type'    => PostTypes::CTA,
				'post_content' => self::block( 'websage/cta' ),
			)
		);
		$this->page(
			array(
				'post_name'    => 'pricing',
				'post_content' => self::block( 'websage/cta-reference', array( 'ctaId' => $cta ) ),
			)
		);
		$this->page( array( 'post_name' => 'unrelated' ) );
		Dispatcher::discard();

		wp_update_post(
			array(
				'ID'         => $cta,
				'post_title' => 'Updated',
			)
		);

		$events = Dispatcher::pending();
		$this->assertSame( 'cta', $events[0]['resource'] );
		$this->assertSame( array( 'page:/pricing' ), $events[0]['tags'] );
	}

	public function test_menus_and_settings_invalidate_site_data(): void {
		wp_create_nav_menu( 'Main' );
		$this->assertContains( 'site', $this->queued_tags() );

		Dispatcher::discard();
		update_option(
			Settings::OPTION,
			array(
				'schema_version' => 1,
				'announcement'   => 'New',
			)
		);
		$this->assertSame( 'settings.updated', Dispatcher::pending()[0]['event'] );
		$this->assertContains( 'site', $this->queued_tags() );
	}

	public function test_delivery_is_signed_over_timestamp_event_and_body(): void {
		$this->page( array( 'post_name' => 'signed' ) );
		Dispatcher::flush();

		$this->assertCount( 1, $this->sent );
		$request = $this->sent[0];
		$this->assertSame( 'https://www.example.com/api/revalidate', $request['url'] );
		$headers = $request['args']['headers'];
		$this->assertSame(
			Signer::sign( WSH_REVALIDATION_SECRET, (int) $headers['X-WSH-Timestamp'], $headers['X-WSH-Event-Id'], $request['args']['body'] ),
			$headers['X-WSH-Signature']
		);
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $headers['X-WSH-Event-Id'] );
		$this->assertSame( 0, $request['args']['redirection'] );
		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( self::shape( self::fixture( 'revalidation-event' ) ), self::shape( $body ) );
		$this->assertStringNotContainsString( WSH_REVALIDATION_SECRET, (string) wp_json_encode( get_option( Dispatcher::LAST_OPTION ) ) );
	}

	public function test_server_errors_retry_with_backoff_and_client_errors_do_not(): void {
		$this->status = 503;
		$this->page( array( 'post_name' => 'retry' ) );
		Dispatcher::flush();
		$jobs = Dispatcher::jobs();
		$this->assertCount( 1, $jobs );
		$this->assertSame( 1, $jobs[0]['attempt'] );
		$this->assertNotFalse( wp_next_scheduled( Dispatcher::RETRY_HOOK ) );

		// Due now: retried with a fresh signature, then gives up after the last delay.
		foreach ( array( 2, 3 ) as $attempt ) {
			update_option( Dispatcher::JOBS_OPTION, array( array_merge( Dispatcher::jobs()[0], array( 'due' => time() - 1 ) ) ), false );
			Dispatcher::retry_due();
			$this->assertSame( $attempt, Dispatcher::jobs()[0]['attempt'] );
		}
		update_option( Dispatcher::JOBS_OPTION, array( array_merge( Dispatcher::jobs()[0], array( 'due' => time() - 1 ) ) ), false );
		Dispatcher::retry_due();
		$this->assertSame( array(), Dispatcher::jobs() );
		$this->assertCount( 4, $this->sent );

		delete_option( Dispatcher::JOBS_OPTION );
		$this->status = 401;
		$this->page( array( 'post_name' => 'rejected' ) );
		Dispatcher::flush();
		$this->assertSame( array(), Dispatcher::jobs() );
	}
}
