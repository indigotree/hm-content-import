<?php

namespace HMCI\Import_Type;

class Term extends Base {

	static function insert( $term, $taxonomy, $canonical_id = false, $args = array(), $term_meta = array() ) {

		// Got term by canonical ID marker
		if ( $canonical_id && $current_id = static::get_id_from_canonical_id( $canonical_id, $taxonomy )  ) {
			$term_id     = $current_id;

		// Got term by name
		} else  {
			$term_exists = get_term_by( 'name', $term, $taxonomy );
			$term_id     = ! empty( $term_exists->term_id ) ? $term_exists->term_id : $term_exists;
		}

		// term already exists, update it
		if ( ! is_wp_error( $term_id ) && $term_id && $args ) {

			$term_id = wp_update_term( $term_id, $taxonomy, $args );

		// term doesn't exist, insert it
		} elseif ( ! is_wp_error( $term_id ) && ! $term_id ) {

			$term_id = wp_insert_term( $term, $taxonomy, $args );
		}

		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		// Get actual term ID
		if ( ! empty( $term_id['term_id'] ) ) {
			$term_id = $term_id['term_id'];
		} else if ( ! is_numeric( $term_id ) ) {
			return new \WP_Error( 'Unexpected term response object' );
		}

		// Canonical ID provided, set it
		if ( $canonical_id ) {
			static::set_canonical_id( $term_id, $canonical_id, $taxonomy );
		}

		// Meta data provided, set it
		if ( $term_meta && is_array( $term_meta ) ) {
			static::set_meta( $term_id, $term_meta );
		}

		return $term_id;
	}

	static function set_meta( $post_id, $meta_data ) {

		if ( ! is_callable( 'delete_term_meta' ) || ! is_callable( 'update_term_meta' ) ) {
			return;
		}

		foreach ( $meta_data as $meta_key => $meta_value ) {

			if ( is_null( $meta_value ) ) {
				delete_term_meta( $post_id, $meta_key );
			} else {
				update_term_meta( $post_id, $meta_key, $meta_value );
			}
		}

	}

	static function exists( $canonical_id, $taxonomy = null ) {

		return (bool) static::get_id_from_canonical_id( $canonical_id, $taxonomy );
	}

	static function get_id_from_canonical_id( $canonical_id, $taxonomy = null ) {

		if ( ! is_callable( 'delete_term_meta' ) || ! is_callable( 'update_term_meta' ) ) {
			return false;
		}

		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->termmeta WHERE meta_key = %s AND meta_value = %s", static::get_canonical_id_key_suffixed( $taxonomy ), $canonical_id ) );
	}

	static function set_canonical_id( $id, $canonical_id, $taxonomy = null ) {

		if ( ! $canonical_id || ! ! is_callable( 'update_term_meta' ) ) {
			return;
		}

		update_term_meta( $id, static::get_canonical_id_key_suffixed( $taxonomy ), $canonical_id );
	}

	static function get_canonical_id_key_suffixed( $taxonomy = null ) {

		return ( $taxonomy ) ? static::get_canonical_id_key() . '_' . $taxonomy :  static::get_canonical_id_key();
	}

}
