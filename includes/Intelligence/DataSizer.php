<?php

namespace Autoblog\Intelligence;

/**
 * Filters and sorts raw data.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class DataSizer {

	/**
	 * Filter raw data items.
	 *
	 * @param array $items Array of raw data items.
	 * @return array Filtered items.
	 */
	public function filter( $items ) {
		
        $filtered = array();

        foreach ( $items as $item ) {
            
            // Example filter: exclude items with no content
            if ( empty( $item['content'] ) ) {
                continue;
            }

            // Example filter: exclude items with very short content (unless it's an image post, handled later)
            if ( strlen( $item['content'] ) < 50 ) {
                continue;
            }

            $filtered[] = $item;

        }

        return $filtered;

	}

    /**
     * Sort items by date (newest first).
     * 
     * @param array $items Array of items.
     * @return array Sorted items.
     */
    public function sort( $items ) {
        
        usort( $items, function( $a, $b ) {
            $date_a = isset( $a['pubDate'] ) ? strtotime( $a['pubDate'] ) : 0;
            $date_b = isset( $b['pubDate'] ) ? strtotime( $b['pubDate'] ) : 0;

            if ( $date_a == $date_b ) {
                return 0;
            }
            return ( $date_a > $date_b ) ? -1 : 1;
        });

        return $items;

    }

}
