<?php

/**
 * Class Flexible_Shipping_UPS_Connect_Platform_Info
 */
class Flexible_Shipping_UPS_Connect_Platform_Info implements \WPDesk\PluginBuilder\Plugin\HookablePluginDependant {

	use \WPDesk\PluginBuilder\Plugin\PluginAccess;

	const NOTICE_NAME = 'fsups-cpi';

	/**
	 * End time for notice..
	 *
	 * @var int
	 */
	private $end_time = 0;

	/**
	 * Flexible_Shipping_UPS_Connect_Platform_Info constructor.
	 *
	 * @param int $end_time End time.
	 */
	public function __construct( $end_time ) {
		$this->end_time = $end_time;
	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'admin_init', [ $this, 'show_info_notice' ] );
	}

	/**
	 * Show info notice.
	 *
	 * @return null|\WPDesk\Notice\PermanentDismissibleNotice
	 */
	public function show_info_notice() {
		if ( $this->end_time > current_time( 'timestamp' ) ) {
			$notice_content = sprintf(
				// Translators: links.
				__( 'Do you want to print UPS labels? Check what we\'ve been working on and get free 50 labels per month for life! %1$sCheck out Flexible Shipping Connect now &rarr;%2$s' ),
				sprintf( '<a href="%1$s" target="_blank">', 'https://wpde.sk/ups-try-connect' ),
				'</a>'
			);
			return new \WPDesk\Notice\PermanentDismissibleNotice(
				$notice_content,
				\WPDesk\Notice\Notice::NOTICE_TYPE_INFO,
				self::NOTICE_NAME
			);
		}
		return null;
	}

}

