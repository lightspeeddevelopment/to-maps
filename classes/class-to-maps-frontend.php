<?php
/**
 * LSX_TO_Maps_Frontend
 *
 * @package   LSX_TO_Maps_Frontend
 * @author    LightSpeed
 * @license   GPL-3.0+
 * @link
 * @copyright 2016 LightSpeedDevelopment
 */

/**
 * Main plugin class.
 *
 * @package LSX_TO_Maps_Frontend
 * @author  LightSpeed
 */
class LSX_TO_Maps_Frontend extends LSX_TO_Maps {

	/**
	 * Holds the value of the current marker
	 */
	var $current_marker = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->set_vars();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 1499 );
	}

	/**
	 * Enques the assets
	 */
	public function assets() {
		wp_enqueue_script( 'googlemaps_api', 'https://maps.googleapis.com/maps/api/js?key=' . $this->api_key . '&libraries=places', array( 'jquery' ), null, true );
		wp_enqueue_script( 'googlemaps_api_markercluster', LSX_TO_MAPS_URL . '/assets/js/google-markerCluster.js', array( 'googlemaps_api' ), null, true );
		wp_enqueue_script( 'lsx_to_maps', LSX_TO_MAPS_URL . '/assets/js/to-maps.min.js', array( 'jquery', 'googlemaps_api', 'googlemaps_api_markercluster' ), null, true );

		wp_localize_script( 'lsx_to_maps', 'lsx_to_maps_params', array(
			'apiKey' => $this->api_key,
			'start_marker' => $this->markers->start,
			'end_marker' => $this->markers->end,
		) );
	}

	/**
	 * Output the Map
	 *
	 * @since 1.0.0
	 *
	 * @return    null
	 */
	public function map_output( $post_id = false, $args = array() ) {
		$defaults = array(
			'lat' => '-33.914482',
			'long' => '18.3758789',
			'zoom' => 14,
			'width' => '100%',
			'height' => '400px',
			'type' => 'single',
			'search' => '',
			'connections' => false,
			'link' => true,
			'selector' => '.lsx-map-preview',
			'icon' => false,
			'content' => 'address',
			'kml' => false,
			'cluster_small'		=> $this->markers->cluster_small,
			'cluster_medium'	=> $this->markers->cluster_medium,
			'cluster_large'		=> $this->markers->cluster_large,
			'fusion_tables' => false,
			'fusion_tables_colour_border' => '#000000',
			'fusion_tables_width_border' => '2',
			'fusion_tables_colour_background' => '#000000',
			'disable_cluster_js' => false,
			'disable_auto_zoom' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );

		$map_classes = array( 'lsx-map' );
		if( true === $disable_auto_zoom ) {
			$map_classes[] = 'disable-auto-zoom';
		}

		$thumbnail = '';

		if ( false === $icon ) {
			$icon = $this->set_icon( $post_id );
		}

		if ( ( '-33.914482' !== $lat && '18.3758789' !== $long ) || false !== $search || 'cluster' === $type || 'route' === $type ) {
			$map = '<div class="' . implode( ' ', $map_classes ) . '" data-zoom="' . $zoom . '" data-icon="' . $icon . '" data-type="' . $type . '" data-class="' . $selector . '" data-fusion-tables-colour-border="' . $fusion_tables_colour_border . '" data-fusion-tables-width-border="' . $fusion_tables_width_border . '" data-fusion-tables-colour-background="' . $fusion_tables_colour_background . '"';

			if ( 'route' === $type && false !== $args['kml'] ) {
				$map .= ' data-kml="' . $kml . '"';
			}

			$map .= ' data-lat="' . $lat . '" data-long="' . $long . '"';

			if ( false === $disable_cluster_js ) {
				$map .= ' data-cluster-small="' . $cluster_small . '" data-cluster-medium="' . $cluster_medium . '" data-cluster-large="' . $cluster_large . '"';
			}

			$map .= '>';
			$map .= '<div class="lsx-map-preview" style="width:' . $width . ';height:' . $height . ';"></div>';

			$map .= '<div class="lsx-map-markers" style="display:none;">';

			if ( 'single' === $type ) {
				$thumbnail = get_the_post_thumbnail_url( $post_id, array( 100, 100 ) );
				$tooltip = $search;

				if ( 'excerpt' === $content ) {
					$tooltip = strip_tags( get_the_excerpt( $post_id ) );
				}

				$icon = $this->set_icon( $post_id );

				$map .= '<div class="map-data" data-icon="' . $icon . '" data-id="' . $post_id . '" data-long="' . $long . '" data-lat="' . $lat . '" data-thumbnail="' . $thumbnail . '" data-link="' . get_permalink( $post_id ) . '" data-title="' . get_the_title( $post_id ) . '" data-fusion-tables="' . ( true === $fusion_tables ? '1' : '0' ) . '">
							<p style="line-height: 20px;">' . $tooltip . '</p>
						</div>';
			} elseif ( ( 'cluster' === $type || 'route' === $type ) && false !== $connections ) {
				if ( ! is_array( $connections ) ) {
					$connections = array( $connections );
				}

				foreach ( $connections as $connection ) {
					$location = get_post_meta( $connection, 'location', true );

					if ( false !== $location && '' !== $location && is_array( $location ) ) {
						$thumbnail = '';

						if ( '' !== $location['long'] && '' !== $location['lat'] ) {
							$thumbnail = get_the_post_thumbnail_url( $connection, array( 100, 100 ) );

							$this->current_marker = $connection;

							$tooltip = $location['address'];

							if ( 'excerpt' === $content ) {
								$tooltip = strip_tags( get_the_excerpt( $connection ) );
							}

							$icon = $this->set_icon( $connection );

							$map .= '<div class="map-data" data-icon="' . $icon . '" data-id="' . $connection . '" data-long="' . $location['long'] . '" data-lat="' . $location['lat'] . '" data-link="' . get_permalink( $connection ) . '" data-thumbnail="' . $thumbnail . '" data-title="' . get_the_title( $connection ) . '" data-fusion-tables="' . ( true === $fusion_tables ? '1' : '0' ) . '">';

							global $post;
							$post = get_post( $connection );
							setup_postdata( $post );

							ob_start();
							lsx_to_content( 'content', 'map-marker' );
							wp_reset_postdata();
							$tooltip = ob_get_clean();

							$map .= $tooltip;
							$map .= '</div>';
						}
					}
				}
			}

			$map .= '</div>';
			$map .= '</div>';

			//print_r($map);
			//die();

			return $map;
		}
	}

	/**
	 * outputs the genral tabs settings
	 */
	public function set_icon( $post_id = false ) {
		$icon = $this->markers->default_marker;

		if ( false !== $post_id ) {
			$connection_type = get_post_type( $post_id );

			if ( in_array( $connection_type, $this->post_types ) ) {
				if ( isset( $this->markers->post_types[ $connection_type ] ) ) {
					$icon = $this->markers->post_types[ $connection_type ];
				}
			} else {
				$icon = apply_filters( 'lsx_to_default_map_marker', $this->markers->default_marker );
			}
		}

		return $icon;
	}

}

global $lsx_to_maps_frontend;
$lsx_to_maps_frontend = new LSX_TO_Maps_Frontend();
