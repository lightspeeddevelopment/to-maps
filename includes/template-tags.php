<?php
/**
 * Template Tags
 *
 * @package   to-maps
 * @author    LightSpeed
 * @license   GPL-3.0+
 * @link
 * @copyright 2016 LightSpeedDevelopment
 */

/**
 * Outputs the current post type map
 *
 * @param		$before	| string
 * @param		$after	| string
 * @param		$echo	| boolean
 * @return		string
 *
 * @package 	to-maps
 * @subpackage	template-tags
 */
function lsx_to_map( $before = '', $after = '', $echo = true ) {
	global $lsx_to_maps_frontend, $wp_query, $post;

	if ( false !== ( $location = get_transient( get_the_ID() . '_location' ) ) ) {
		$zoom = 15;

		if ( is_array( $location ) && isset( $location['zoom'] ) ) {
			$zoom = $location['zoom'];
		}

		$zoom = apply_filters( 'lsx_to_map_zoom', $zoom );
		$map_type = 'default';
		$parent_id = false;
		$connections = false;

		if ( is_singular( 'tour' ) ) {
			$map_type = 'itinerary';
		} elseif ( is_post_type_archive( 'destination' ) ) {
			$map_type = 'region_archive';
			$parent_id = '0';
		} elseif ( is_singular( 'destination' ) ) {
			global $post;

			if ( 0 === $post->post_parent ) {
				$map_type = 'region_archive';
				$parent_id = $post->ID;
			}
		} elseif ( is_tax( 'continent' ) ) {
			$map_type = 'continent_archive';
		}

		switch ( $map_type ) {
			case 'itinerary':
				$args = array(
					'zoom' => $zoom,
					'width' => '100%',
					'height' => '500px',
					'type' => 'route',
				);

				if ( isset( $location['kml'] ) ) {
					$args['kml'] = $location['kml'];
				} elseif ( isset( $location['connections'] ) ) {
					$args['connections'] = $location['connections'];
				}

				break;

			case 'region_archive':
				$args = array();

				$region_args = array(
					'post_type'	=> 'destination',
					'post_status' => 'publish',
					'nopagin' => true,
					'posts_per_page' => '-1',
					'fields' => 'ids',
				);

				$region_args['post_parent'] = $parent_id;

				if ( true === lsx_to_display_fustion_tables() ) {
					$region_args['post_parent'] = 0;
					$args['fusion_tables'] = true;
					$args['fusion_tables_colour_border'] = lsx_to_fustion_tables_attr( 'colour_border', '#000000' );
					$args['fusion_tables_width_border'] = lsx_to_fustion_tables_attr( 'width_border', '2' );
					$args['fusion_tables_colour_background'] = lsx_to_fustion_tables_attr( 'colour_background', '#000000' );
				}

				$regions = new WP_Query( $region_args );

				if ( isset( $regions->posts ) && ! empty( $regions->posts ) ) {
					$connections = $regions->posts;
				} else {
					$args['long'] = $location['long'];
					$args['lat'] = $location['lat'];
				}

				if ( lsx_to_has_destination_banner_map() ) {
					$args['selector'] = '#lsx-banner .page-banner';
				}

				$args['content'] = 'excerpt';

				if ( false !== $connections && '' !== $connections ) {
					$args['connections'] = $connections;
					$args['type'] = 'cluster';

					if ( '0' === $parent_id  && ! lsx_to_has_destination_banner_cluster() ) {
						$args['disable_cluster_js'] = true;
					}
				}

				//Check to see if the zoom is disabled
				$manual_zoom = get_post_meta( $parent_id, 'disable_auto_zoom', true );
				if ( false !== $manual_zoom && '' !== $manual_zoom ) {
					$args['disable_auto_zoom'] = true;
					$args['zoom'] = $zoom;
					$args['long'] = $location['long'];
					$args['lat'] = $location['lat'];
				}

				break;

			case 'continent_archive':
				$args = array();

				$country_args = array(
					'post_type'	=> 'destination',
					'post_status' => 'publish',
					'nopagin' => true,
					'posts_per_page' => '-1',
					'fields' => 'ids',
					'post_parent' => 0,
					'tax_query' => array(
						array(
							'taxonomy' => 'continent',
							'field'    => 'term_id',
							'terms'    => array( get_queried_object()->term_id ),
						),
					),
				);

				if ( true === lsx_to_display_fustion_tables() ) {
					$args['fusion_tables'] = true;
					$args['fusion_tables_colour_border'] = lsx_to_fustion_tables_attr( 'colour_border', '#000000' );
					$args['fusion_tables_width_border'] = lsx_to_fustion_tables_attr( 'width_border', '2' );
					$args['fusion_tables_colour_background'] = lsx_to_fustion_tables_attr( 'colour_background', '#000000' );
				}

				$countries = new WP_Query( $country_args );

				if ( isset( $countries->posts ) && ! empty( $countries->posts ) ) {
					$connections = $countries->posts;
				}

				if ( lsx_to_has_destination_banner_map() ) {
					$args['selector'] = '#lsx-banner .page-banner';
				}

				$args['content'] = 'excerpt';

				if ( false !== $connections && '' !== $connections ) {
					$args['connections'] = $connections;
					$args['type'] = 'cluster';

					if ( '0' === $parent_id  && ! lsx_to_has_destination_banner_cluster() ) {
						$args['disable_cluster_js'] = true;
					}
				}

				break;

			default:
				$args = array(
					'long' => $location['long'],
					'lat' => $location['lat'],
					'zoom' => $zoom,

					'width' => '100%',
					'height' => '500px',
				);

				break;
		}

		echo wp_kses_post( $lsx_to_maps_frontend->map_output( get_the_ID(), $args ) );
	}
}

/**
 * Outputs the map meta
 *
 * @package 	to-maps
 * @subpackage	template-tags
 * @category 	meta
 */
function lsx_to_map_meta() {
	do_action( 'lsx_to_map_meta' );
}

/**
 * Return if the fusion tables are enabled or not
 *
 * @package 	to-maps
 * @subpackage	template-tags
 */
function lsx_to_display_fustion_tables() {
	$temp = get_option( '_lsx-to_settings', false );

	if ( false !== $temp && isset( $temp['display'] ) && ! empty( $temp['display'] ) ) {
		if ( isset( $temp['display']['fusion_tables_enabled'] ) ) {
			return true;
		} else {
			return false;
		}
	}
}

/**
 * Return fusion tables attribute
 *
 * @package 	to-maps
 * @subpackage	template-tags
 */
function lsx_to_fustion_tables_attr( $attribute, $default ) {
	$temp = get_option( '_lsx-to_settings', false );

	if ( false !== $temp && isset( $temp['display'] ) && ! empty( $temp['display'] ) ) {
		if ( isset( $temp['display'][ 'fusion_tables_' . $attribute ] ) && ! empty( $temp['display'][ 'fusion_tables_' . $attribute ] ) ) {
			return $temp['display'][ 'fusion_tables_' . $attribute ];
		} else {
			return $default;
		}
	}
}

/**
 * Checks to see if the destination banner map is enabled.
 *
 * @package 	to-maps
 * @subpackage	template-tags
 * @category 	destination
 *
 * @return boolean
 */
function lsx_to_has_destination_banner_map() {
	$temp = get_option( '_lsx-to_settings', false );
	$return = false;

	if ( false !== $temp && isset( $temp['destination'] ) && ! empty( $temp['destination'] ) ) {
		if ( isset( $temp['destination']['enable_banner_map'] ) ) {
			$return = true;
		}
	}

	return apply_filters( 'lsx_to_has_destination_banner_map', $return );
}

/**
 * Checks to see if the destination banner map cluster is disabled.
 *
 * @package 	to-maps
 * @subpackage	template-tags
 * @category 	destination
 *
 * @return boolean
 */
function lsx_to_has_destination_banner_cluster() {
	$temp = get_option( '_lsx-to_settings', false );

	if ( false !== $temp && isset( $temp['destination'] ) && ! empty( $temp['destination'] ) ) {
		if ( isset( $temp['destination']['disable_banner_map_cluster'] ) ) {
			return false;
		} else {
			return true;
		}
	}
	return true;
}

/**
 * Checks to see if the API key is actually set.
 *
 * @package 	to-maps
 * @subpackage	template-tags
 * @category 	meta
 */
function lsx_to_maps_has_api_key() {
	$options = get_option( '_lsx-to_settings', false );

	if ( false !== $options && isset( $options['api']['googlemaps_key'] ) ) {
		$return = true;
	} else {
		$return = $options;
	}

	return $return;
}

/**
 * Checks if the current item has a map
 *
 * @param		$before	| string
 * @param		$after	| string
 * @param		$echo	| boolean
 * @return		string
 *
 * @package 	tour-operator
 * @subpackage	template-tags
 * @category 	general
 */
function lsx_to_has_map() {
	//If theres no API key then dont even try output the Map
	if ( ! lsx_to_maps_has_api_key() ) { return false; }

	// Get any existing copy of our transient data
	if ( false === ( $location = get_transient( get_the_ID() . '_location' ) ) ) {
		// It wasn't there, so regenerate the data and save the transient

		$kml = false;

		if ( is_post_type_archive( 'destination' ) ) {
			$location = array(
				'lat' => true,
			);
		} elseif ( is_singular( 'tour' ) ) {
			$file_id = get_post_meta( get_the_ID(), 'itinerary_kml', true );

			if ( false !== $file_id ) {
				$kml = wp_get_attachment_url( $file_id );
			}

			$location = false;
			$accommodation_connected = get_post_meta( get_the_ID(), 'accommodation_to_tour' );

			if ( is_array( $accommodation_connected ) && ! empty( $accommodation_connected ) ) {
				$location = array(
					'lat' => true,
					'connections' => $accommodation_connected,
				);
			}
		} else {
			$location = get_post_meta( get_the_ID(), 'location', true );
		}

		if ( false !== $location && '' !== $location && is_array( $location ) && isset( $location['lat'] ) && '' !== $location['lat'] ) {
			set_transient( get_the_ID() . '_location', $location, 30 );
			return true;
		} elseif ( false !== $kml ) {
			set_transient( get_the_ID() . '_location', array(
				'kml' => $kml,
			), 30 );

			return true;
		} else {
			return false;
		}
	} elseif ( is_array( $location ) && ((isset( $location['lat'] ) && '' !== $location['lat']) || (isset( $location['kml'] ) && '' !== $location['kml'])) ) {
		return true;
	} else {
		return false;
	}
}
