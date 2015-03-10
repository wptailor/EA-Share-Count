<?php
/**
 * Plugin Name: EA Share Count
 * Plugin URI:  https://github.com/jaredatch/EA-Share-Count
 * Description: 
 * Author:      Bill Erickson & Jared Atchison
 * Version:     1.0.0
 *
 * EA Share Count is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * EA Share Count is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EA Share Count. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    EA_ShareCount
 * @author     Bill Erickson & Jared Atchison
 * @since      1.0.0
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2015
 */
 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main class
 *
 * @since 1.0.0
 * @package EA_Share_Count
 */
final class EA_Share_Count {

	/**
	 * Instance of the class.
	 *
	 * @since 1.0.0
	 * @var object
	 */
	private static $instance;

	/**
	 * Domain for accessing SharedCount API.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_domain;
	
	/**
	 * API Key for SharedCount.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_key;
	
	/** 
	 * Share Count Instance.
	 *
	 * @since 1.0.0
	 * @return EA_Share_Count
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EA_Share_Count ) ) {
			self::$instance = new EA_Share_Count;
			$this->init();
		}
		return self::$instance;
	}

	/**
	 * Start the engines.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->api_key    = apply_filters( 'ea_share_count_key', '' );
		$this->api_domain = apply_filters( 'ea_share_count_domain', 'http://free.sharedcount.com' );
	}
	
	/**
	 * Retreive share counts for site or post.
	 * 
	 * @since 1.0.0
	 * @param int/string $id, pass 'site' for full site stats
	 * @param boolean $array, return json o
	 * @return object $share_count
	 */
	public function counts( $id = false, $array = false ) {

		if ( 'site' == $site ) {
			$post_date    = true;
			$post_url     = home_url();
			$share_count  = get_option( 'ea_share_count' );
			$last_updated = get_option( 'ea_share_count_datetime' );
			
		} else {
			$post_id      = $id ? $id : get_the_ID();
			$post_date    = false;
			$post_url     = get_permalink( $post_id );
			$share_count  = get_post_meta( $post_id, 'ea_share_count', true );
			$last_updated = get_post_meta( $post_id, 'ea_share_count_datetime', true );
		}

		// Rebuild and update meta if necessary
		if ( ! $share_count || ! $last_updated || $this->needs_updating( $last_updated, $post_date ) ) {
			
			$share_count = $this->query_api( $post_url ) );

			if ( $share_count && 'site' == $id ) {
				update_option( 'ea_share_count', $share_count );
				update_option( 'ea_share_count_datetime', time() );
			} elseif ( $share_count ) {
				update_post_meta( $post_id, 'ea_share_count', $share_count );
				update_post_meta( $post_id, 'ea_share_count_datetime', time() );
			}
		}

		if ( ! $share_count && $array == true ) {
			$share_count = json_decode( $share_count, true );
		}

		return $share_count;
	}

	/**
	 * Retreive a single share count for a site or post.
	 *
	 * @since 1.0.0
	 * @param int/string $id, pass 'site' for full site stats
	 * @param string $type
	 * @param boolean $echo
	 * @return int
	 */
	public function count( $id = false, $type = 'facebook', $echo = false ) {

		$counts = $this->counts( $id, true );

		if ( $counts == false ) {
			$share_count == '0';
		} else {
			switch ( $type ) {
				case 'facebook':
					$share_count = $counts['Facebook']['total_count'];
					break;
				case 'facebook_likes':
					$share_count = $counts['Facebook']['like_count'];
					break;
				case 'facebook_shares':
					$share_count = $counts['Facebook']['share_count'];
					break;
				case 'facebook_comments':
					$share_count = $counts['Facebook']['comment_count'];
					break;
				case 'twitter':
					$share_count = $counts['Twitter'];
					break;
				case 'pinterest':
					$share_count = $counts['Pinterest'];
					break;
				case 'linkedin':
					$share_count = $counts['LinkedIn'];
					break;
				case 'google':
					$share_count = $counts['GooglePlusOne'];
					break;
				case 'stumbleupon':
					$share_count = $counts['StumbleUpon'];
					break;
				default:
					$share_count = apply_filters( 'ea_share_count_single', '0', $counts );
					break;
			}
		}

		if ( $echo ) {
			echo $share_count;
		} else {
			return $share_count;
		}
	}

	/**
	 * Check if share count needs updating.
	 *
	 * @since 1.0.0
	 * @param int $last_updated, unix timestamp
	 * @param int $post_date, unix timestamp
	 * @return bool $needs_updating
	 */
	function needs_updating( $last_updated = false, $post_date = false ) {
	
		if ( ! $last_updated ) {
			return true;
		}
			
		if ( ! $post_date ) {
			$post_date = get_the_date( 'U', $post_id );
		}
	
		$update_increments = array(
			array(
				'post_date' => strtotime( '-1 day' ),
				'increment' => strtotime( '-30 minutes'),
			),
			array(
				'post_date' => strtotime( '-5 days' ),
				'increment' => strtotime( '-6 hours' )
			),
			array(
				'post_date' => 0,
				'increment' => strtotime( '-2 days' ),
			)
		);
		
		$increment = false;
		foreach ( $update_increments as $i ) {
			if ( $post_date > $i['post_date'] ) {
				$increment = $i['increment'];
				break;
			}
		}
		
		return $last_updated < $increment;
	}

	/**
	 * Query the SharedCount API
	 *
	 * @since 1.0.0
	 * @param string $url
	 * @return object $share_count
	 */
	function query_api( $url = false ) {
	
		// Check that URL and API key are set
		if ( ! $url || empty( $this->api_key ) ) {
			return;
		}

		$query_args = apply_filters( 'ea_share_count_api_params', array( 'url' => $url, 'apikey' => $this->api_key ) );
		$query      = add_query_arg( $query_args, $this->api_domain . '/url' );
		$results    = wp_remote_get( $query );

		if ( 200 == $results['response']['code'] ) {
			return $results['body'];
		} else {
			return false;
		}
	}

	/**
	 * Generate sharing links
	 *
	 * For styling: https://gist.github.com/billerickson/a67bf451675296b144ea
	 *
	 * @since 1.0.0
	 * @param string $type, button type
	 * @param int/string $id, pass 'site' for full site stats
	 * @return string $button_output
	 */
	function link( $types = 'facebook', $id = false, $echo = true ) {

		if ( !$id ) {
			$id = get_the_ID();
		}

		$types  = (array) $types;
		$output = '';

		foreach ( $types as $type ) {
			$link         = array();
			$link['type'] = $type;

			if ( 'site' == $id ) {
				$link['url']   = home_url();
				$link['title'] = get_bloginfo( 'name' );
				$link['img']   = apply_filters( 'ea_share_count_default_image', '' );
			} else {
				$link['url']   = get_permalink( $id );
				$link['title'] = get_the_title( $id );
				$img           = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'full' );
				if ( isset( $img[0] ) ) {
					$link['img'] = $img[0];
				} else {
					$link['img'] = apply_filters( 'ea_share_count_default_image', '' );
				}
			}
			$link['count'] = $this->count( $id, $type );

			switch ( $type ) {
				case 'facebook':
					$link['link']  = 'http://www.facebook.com/plugins/like.php?href=' . $link['url'];
					$link['label'] = 'Facebook';
					$link['icon']  = 'fa fa-facebook';
					break;
				case 'facebook_likes':
					$link['link']  = 'http://www.facebook.com/plugins/like.php?href=' . $link['url'];
					$link['label'] = 'Like';
					$link['icon']  = 'fa fa-facebook';
					break;
				case 'facebook_shares':
					$link['link']  = 'http://www.facebook.com/plugins/share_button.php?href=' . $link['url'];
					$link['label'] = 'Share';
					$link['icon']  = 'fa fa-facebook';
					break;
				case 'twitter':
					$link['link']  = 'https://twitter.com/share?url=' . $link['url'] . '&text=' . $link['title'];
					$link['label'] = 'Tweet';
					$link['icon']  = 'fa fa-twitter';
					break;
				case 'pinterest':
					$link['link']  = 'http://pinterest.com/pin/create/button/?url=' . $link['url'] . '&media=' . $img . ' &description=' . $link['title'];
					$link['label'] = 'Pin';
					$link['icon']  = 'fa fa-pinterest-p';
					break;
				case 'linkedin':
					$link['link']  = 'http://www.linkedin.com/shareArticle?mini=true&url=' . $link['url'];
					$link['label'] = 'LinkedIn';
					$link['icon']  = 'fa fa-linkedin';
					break;
				case 'google':
					$link['link']  = 'http://plus.google.com/share?url=' . $link['url'];
					$link['label'] = 'Google+';
					$link['icon']  = 'fa fa-google-plus';
					break;
				case 'stumbleupon':
					$link['link']  = 'http://www.stumbleupon.com/submit?url=' . $link['url'] . '&title=' . $link['title'];
					$link['label'] = 'StumbleUpon';
					$link['icon']  = 'fa fa-stumbleupon';
					break;
			}

			$link = apply_filters( 'ea_share_count_link', $data );

			$output .= '<a href="' . $link['link'] . '" target="_blank" class="ea-share-count ' . sanitize_html_class( $link['type'] ) . '">';

			$output .= '</a>';
		}

		if ( $echo == true ) {
			echo $output;
		} else {
			return $output;
		}
		
		/*
		if( 'fb-like' == $type && isset( $count->Facebook->like_count ) )
			return '<a class="social-count facebook-like-button" href=" . '"><span class="blue"><i class="icon-social-facebook"></i>Like</span> <span class="count">' . $count->Facebook->like_count . '</span></a>';
			
		if( 'fb-share' == $type && isset( $count->Facebook->share_count ) )
			return '<a class="social-count facebook-share-button" href="http://www.facebook.com/plugins/share_button.php?href=' . urlencode( $url ) . '"><span class="blue"><i class="icon-social-facebook"></i>Share</span> <span class="count">' . $count->Facebook->share_count . '</span></a>';
			
		if( 'twitter' == $type && isset( $count->Twitter ) )
			return '<a class="social-count twitter-button" href=""><span class="tweet"><i class="icon-twitter"></i><span class="label">Tweet</span></span><span class="count">' . $count->Twitter . '</span></a>';
		*/
	}
}

/**
 * The function which returns the one WP_Forms instance.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @since 1.0.0
 * @return object
 */
function ea_share() {
	return EA_Share_Count::instance();
}
ea_share();