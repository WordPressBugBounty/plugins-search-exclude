<?php

namespace QuadLayers\QLSE\Controllers;

use QuadLayers\QLSE\Models\Settings as Models_Settings;

/**
 * Frontend Class
 */
class Frontend {

	protected static $instance;

	private function __construct() {
		/**
		 * Search filter
		 */

		add_action(
			'init',
			function () {
			add_filter( 'pre_get_posts', array( $this, 'search_filter' ) );
			add_filter( 'bbp_has_replies_query', array( $this, 'bbpress_flag_replies' ) );
			}
		);
	}

	public function search_filter( $query ) {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $query;
		}

		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return $query;
		}

		$excluded = Models_Settings::instance()->get();

		if ( ! isset( $excluded ) ) {
			return $query;
		}

		$allow_exclude = $query->is_search && ! $this->is_bbpress( $query );

		$allow_exclude = apply_filters( 'searchexclude_filter_search', $allow_exclude, $query );

		if ( ! $allow_exclude ) {
			return $query;
		}

		/**
		 * Exclude posts by post type
		 */
		if ( isset( $excluded->entries ) ) {
			$post__not_in = $query->get( 'post__not_in', array() );
			foreach ( $excluded->entries as $post_type => $excluded_post_type ) {
				$post_type_ids = ! empty( $excluded_post_type['all'] ) ? $this->get_all_post_type_ids( $post_type ) : $excluded_post_type['ids'];
				$post__not_in  = array_merge( $post__not_in, $post_type_ids );
			}
			$query->set( 'post__not_in', $post__not_in );
		}

		/**
		 * Exclude posts by taxonomies
		 */
		if ( isset( $excluded->taxonomies ) ) {
			$tax_query          = $query->get( 'tax_query', array() );
			$tax_query_modified = false;

			foreach ( $excluded->taxonomies as $taxonomy => $setting ) {
				$ids = isset( $setting['ids'] ) ? $setting['ids'] : array();

				// Use more memory-efficient approach for taxonomies
				if ( $setting['all'] ) {
					// Using NOT EXISTS is more efficient than fetching all terms
					$tax_query[]        = array(
						'taxonomy' => $taxonomy,
						'operator' => 'NOT EXISTS',
					);
					$tax_query_modified = true;
				} elseif ( ! empty( $ids ) ) {
					$tax_query[]        = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $ids,
						'operator' => 'NOT IN',
					);
					$tax_query_modified = true;
				}
			}

			if ( $tax_query_modified ) {
				$existing_tax_query = $query->get( 'tax_query' );

				// Properly merge tax queries to prevent nesting issues
				if ( ! empty( $existing_tax_query ) ) {
					// If existing tax query doesn't have a relation, wrap it
					if ( is_array( $existing_tax_query ) && ! isset( $existing_tax_query['relation'] ) ) {
						$tax_query = array(
							'relation' => 'AND',
							$existing_tax_query,
						);
						// Add our new conditions
						foreach ( $tax_query as $key => $condition ) {
							if ( is_numeric( $key ) ) {
								$tax_query[] = $condition;
							}
						}
					} else {
						// Otherwise create a properly structured query
						$tax_query = array(
							'relation' => 'AND',
							$existing_tax_query,
						);

						// Add our new conditions
						foreach ( $tax_query as $condition ) {
							if ( is_array( $condition ) ) {
								$tax_query[] = $condition;
							}
						}
					}
				} else {
					$tax_query['relation'] = 'AND';
				}

				$query->set( 'tax_query', $tax_query );
			}
		}
		/**
		 * Exclude posts by author
		 */
		if ( isset( $excluded->author ) ) {
			$ids = isset( $excluded->author['ids'] ) ? $excluded->author['ids'] : array();
			// Exclude all posts by author
			if ( $excluded->author['all'] ) {
				$query->set( 'post__in', array( 0 ) ); // This returns no posts
				// Exclude by ids
			} else {
				$query->set( 'author__not_in', $ids );
			}
		}

		return $query;
	}

/**
 * Helper function to get all post IDs for a given post type.
 */
	private function get_all_post_type_ids( $post_type ) {
		$all_posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return $all_posts;
	}

	public function is_bbpress( $query ) {
		return $query->get( '___s2_is_bbp_has_replies' );
	}

	/**
	 * Flags a WP Query has being a `bbp_has_replies()` query.
	 *
	 * @attaches-to ``add_filter('bbp_has_replies_query');``
	 *
	 * @param array $args Query arguments passed by the filter.
	 *
	 * @return array The array of ``$args``.
	 *
	 * @see Workaround for bbPress and the `s` key. See: <http://bit.ly/1obLpv4>
	 */
	public function bbpress_flag_replies( $args ) {
		return array_merge( $args, array( '___s2_is_bbp_has_replies' => true ) );
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
