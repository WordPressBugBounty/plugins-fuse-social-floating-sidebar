<?php
/**
 * Builds share-intent URLs for the social share buttons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Share_Links {

	/**
	 * @param string $network One of Fuse_Networks::share_networks() keys.
	 * @param string $url     Absolute URL being shared.
	 * @param string $title   Title being shared.
	 */
	public static function build( $network, $url, $title ) {
		$e_url   = rawurlencode( $url );
		$e_title = rawurlencode( $title );

		switch ( $network ) {
			case 'facebook':
				return "https://www.facebook.com/sharer/sharer.php?u={$e_url}";
			case 'x':
				return "https://twitter.com/intent/tweet?url={$e_url}&text={$e_title}";
			case 'linkedin':
				return "https://www.linkedin.com/sharing/share-offsite/?url={$e_url}";
			case 'pinterest':
				return "https://pinterest.com/pin/create/button/?url={$e_url}&description={$e_title}";
			case 'reddit':
				return "https://www.reddit.com/submit?url={$e_url}&title={$e_title}";
			case 'whatsapp':
				return "https://wa.me/?text={$e_title}%20{$e_url}";
			case 'telegram':
				return "https://t.me/share/url?url={$e_url}&text={$e_title}";
			case 'tumblr':
				return "https://www.tumblr.com/widgets/share/tool?canonicalUrl={$e_url}&title={$e_title}";
			case 'vk':
				return "https://vk.com/share.php?url={$e_url}&title={$e_title}";
			case 'line':
				return "https://social-plugins.line.me/lineit/share?url={$e_url}";
			case 'viber':
				return "viber://forward?text={$e_title}%20{$e_url}";
			case 'skype':
				return "https://web.skype.com/share?url={$e_url}&text={$e_title}";
			case 'bluesky':
				return "https://bsky.app/intent/compose?text={$e_title}%20{$e_url}";
			case 'threads':
				return "https://www.threads.net/intent/post?text={$e_title}%20{$e_url}";
			case 'pocket':
				return "https://getpocket.com/save?url={$e_url}&title={$e_title}";
			case 'xing':
				return "https://www.xing.com/spi/shares/new?url={$e_url}";
			case 'flipboard':
				return "https://share.flipboard.com/bookmarklet/popout?v=2&url={$e_url}&title={$e_title}";
			case 'envelope':
				return "mailto:?subject={$e_title}&body={$e_url}";
			case 'link':
			case 'print':
			case 'native':
				return '#'; // handled client-side (clipboard / window.print / navigator.share).
			default:
				return '';
		}
	}
}
