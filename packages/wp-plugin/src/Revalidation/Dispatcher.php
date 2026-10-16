<?php
/**
 * Signed webhook delivery with retries.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Revalidation;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Support\Integration;
use WebSage\HeadlessCore\Support\Secrets;

/**
 * Events queued during a request are merged and sent once at shutdown.
 * Failed deliveries retry after 10, 60 and 300 seconds via WP-Cron with a
 * fresh signature; pending jobs and the last outcome are stored privately.
 */
final class Dispatcher {

	public const JOBS_OPTION  = 'wsh_revalidation_jobs';
	public const LAST_OPTION  = 'wsh_revalidation_last';
	public const RETRY_HOOK   = 'wsh_revalidation_retry';
	public const RETRY_DELAYS = array( 10, 60, 300 );
	public const TIMEOUT      = 5;

	/**
	 * Events waiting for shutdown, keyed by event+resource.
	 *
	 * @var array<string, array{event: string, resource: string, paths: list<string>, tags: list<string>}>
	 */
	private static array $pending = array();

	/**
	 * Whether the shutdown flush is registered for this request.
	 *
	 * @var bool
	 */
	private static bool $shutdown_hooked = false;

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_action( self::RETRY_HOOK, array( self::class, 'retry_due' ) );
	}

	/**
	 * Queues an event for delivery at the end of the request.
	 *
	 * @param string   $event Event name.
	 * @param string   $kind  Resource type.
	 * @param string[] $paths Affected paths.
	 * @param string[] $tags  Cache tags.
	 */
	public static function queue( string $event, string $kind, array $paths, array $tags ): void {
		$key = $event . '|' . $kind;
		if ( ! isset( self::$pending[ $key ] ) ) {
			self::$pending[ $key ] = array(
				'event'    => $event,
				'resource' => $kind,
				'paths'    => array(),
				'tags'     => array(),
			);
		}
		self::$pending[ $key ]['paths'] = array_values( array_unique( array_merge( self::$pending[ $key ]['paths'], $paths ) ) );
		self::$pending[ $key ]['tags']  = array_values( array_unique( array_merge( self::$pending[ $key ]['tags'], array_filter( $tags, array( self::class, 'valid_tag' ) ) ) ) );

		if ( ! self::$shutdown_hooked ) {
			add_action( 'shutdown', array( self::class, 'flush' ) );
			self::$shutdown_hooked = true;
		}
	}

	/**
	 * Whether a tag matches the shared grammar (packages/contracts/src/events.ts).
	 *
	 * @param string $tag Cache tag.
	 */
	public static function valid_tag( string $tag ): bool {
		return 1 === preg_match( '#^(?:site|posts|routes|page:/(?:[\p{Ll}\p{Lo}\p{N}_-]+(?:/[\p{Ll}\p{Lo}\p{N}_-]+)*)?|post:[\p{Ll}\p{Lo}\p{N}_-]+)$#u', $tag );
	}

	/**
	 * Events queued but not yet sent (for tests).
	 *
	 * @return list<array{event: string, resource: string, paths: list<string>, tags: list<string>}>
	 */
	public static function pending(): array {
		return array_values( self::$pending );
	}

	/**
	 * Drops queued events without sending them (tests, aborted requests).
	 */
	public static function discard(): void {
		self::$pending = array();
	}

	/**
	 * Sends every pending event.
	 */
	public static function flush(): void {
		$pending       = self::$pending;
		self::$pending = array();
		foreach ( $pending as $event ) {
			if ( array() === $event['tags'] ) {
				continue;
			}
			$body = array(
				'version'  => 1,
				'event'    => $event['event'],
				'resource' => $event['resource'],
				'paths'    => array_slice( $event['paths'], 0, 50 ),
				'tags'     => array_slice( $event['tags'], 0, 100 ),
			);
			$job  = array(
				'id'      => wp_generate_uuid4(),
				'body'    => (string) wp_json_encode( $body ),
				'attempt' => 0,
				'due'     => time(),
			);
			if ( ! self::deliver( $job ) ) {
				self::schedule_retry( $job );
			}
		}
	}

	/**
	 * Sends retries whose time has come.
	 */
	public static function retry_due(): void {
		$jobs = self::jobs();
		$keep = array();
		$now  = time();
		foreach ( $jobs as $job ) {
			if ( $job['due'] > $now ) {
				$keep[] = $job;
				continue;
			}
			if ( ! self::deliver( $job ) ) {
				$next = self::next_job( $job );
				if ( null !== $next ) {
					$keep[] = $next;
				}
			}
		}
		update_option( self::JOBS_OPTION, $keep, false );
		self::schedule_next( $keep );
	}

	/**
	 * Sends a test event (diagnostics); returns the outcome without retrying.
	 *
	 * @return array{ok: bool, status: int, message: string}
	 */
	public static function test(): array {
		$job = array(
			'id'      => wp_generate_uuid4(),
			'body'    => (string) wp_json_encode(
				array(
					'version'  => 1,
					'event'    => 'settings.updated',
					'resource' => 'settings',
					'paths'    => array(),
					'tags'     => array( 'site' ),
				)
			),
			'attempt' => 0,
			'due'     => time(),
		);
		self::deliver( $job );
		$last = get_option( self::LAST_OPTION );
		return is_array( $last ) ? array(
			'ok'      => (bool) $last['ok'],
			'status'  => (int) $last['status'],
			'message' => (string) $last['message'],
		) : array(
			'ok'      => false,
			'status'  => 0,
			'message' => 'not attempted',
		);
	}

	/**
	 * Revalidation endpoint: WSH_REVALIDATE_URL, or {frontend}/api/revalidate.
	 *
	 * @return string|null Null when the frontend origin is not configured.
	 */
	public static function endpoint(): ?string {
		$override = defined( 'WSH_REVALIDATE_URL' ) ? constant( 'WSH_REVALIDATE_URL' ) : getenv( 'WSH_REVALIDATE_URL' );
		if ( is_string( $override ) && str_starts_with( $override, 'https://' ) ) {
			return $override;
		}
		try {
			return Integration::frontend_origin() . '/api/revalidate';
		} catch ( ContentException ) {
			return null;
		}
	}

	/**
	 * One delivery attempt; records the outcome.
	 *
	 * @param array{id: string, body: string, attempt: int, due: int} $job Job.
	 */
	private static function deliver( array $job ): bool {
		$secret   = Secrets::get( Secrets::REVALIDATION );
		$endpoint = self::endpoint();
		if ( null === $secret || null === $endpoint ) {
			self::record( false, 0, 'not configured: revalidation secret or frontend origin missing', $job );
			return true; // Nothing to retry until configured.
		}

		$timestamp = time();
		$response  = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'    => 'application/json',
					'X-WSH-Timestamp' => (string) $timestamp,
					'X-WSH-Event-Id'  => $job['id'],
					'X-WSH-Signature' => Signer::sign( $secret, $timestamp, $job['id'], $job['body'] ),
				),
				'body'        => $job['body'],
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record( false, 0, $response->get_error_message(), $job );
			return false;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$ok     = $status >= 200 && $status < 300;
		self::record( $ok, $status, $ok ? 'delivered' : 'frontend answered ' . $status, $job );
		// Client errors (bad signature, malformed event) will not improve on retry.
		return $ok || ( $status >= 400 && $status < 500 && 429 !== $status );
	}

	/**
	 * Stores the next attempt of a failed job.
	 *
	 * @param array{id: string, body: string, attempt: int, due: int} $job Job.
	 */
	private static function schedule_retry( array $job ): void {
		$next = self::next_job( $job );
		if ( null === $next ) {
			return;
		}
		$jobs   = self::jobs();
		$jobs[] = $next;
		update_option( self::JOBS_OPTION, $jobs, false );
		self::schedule_next( $jobs );
	}

	/**
	 * The job's next attempt, or null after the last retry.
	 *
	 * @param array{id: string, body: string, attempt: int, due: int} $job Job.
	 * @return array{id: string, body: string, attempt: int, due: int}|null
	 */
	private static function next_job( array $job ): ?array {
		$delay = self::RETRY_DELAYS[ $job['attempt'] ] ?? null;
		if ( null === $delay ) {
			return null;
		}
		$job['attempt'] += 1;
		$job['due']      = time() + $delay;
		return $job;
	}

	/**
	 * Schedules the cron event for the earliest pending job.
	 *
	 * @param list<array{id: string, body: string, attempt: int, due: int}> $jobs Jobs.
	 */
	private static function schedule_next( array $jobs ): void {
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		if ( array() !== $jobs ) {
			wp_schedule_single_event( min( array_column( $jobs, 'due' ) ), self::RETRY_HOOK );
		}
	}

	/**
	 * Pending retry jobs.
	 *
	 * @return list<array{id: string, body: string, attempt: int, due: int}>
	 */
	public static function jobs(): array {
		$jobs = get_option( self::JOBS_OPTION, array() );
		return is_array( $jobs ) ? array_values( $jobs ) : array();
	}

	/**
	 * Records the last delivery outcome (no secrets, no body).
	 *
	 * @param bool                                                    $ok      Success.
	 * @param int                                                     $status  HTTP status.
	 * @param string                                                  $message Summary.
	 * @param array{id: string, body: string, attempt: int, due: int} $job     Job.
	 */
	private static function record( bool $ok, int $status, string $message, array $job ): void {
		update_option(
			self::LAST_OPTION,
			array(
				'ok'      => $ok,
				'status'  => $status,
				'message' => mb_substr( $message, 0, 300 ),
				'eventId' => $job['id'],
				'attempt' => $job['attempt'],
				'time'    => time(),
			),
			false
		);
	}
}
