<?php
/**
 * Transient cache for wpcargo_container to improve performance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Use posts_pre_query to return cached results and skip the DB query entirely
add_filter('posts_pre_query', 'merc_container_cache_posts_pre_query', 10, 2);
function merc_container_cache_posts_pre_query($posts, $query) {
    // Only cache main queries for wpcargo_container in admin or API
    if ($query->get('post_type') === 'wpcargo_container') {
        $cache_key = 'merc_container_query_' . md5(serialize($query->query_vars));
        $cached_results = get_transient($cache_key);
        
        if ($cached_results !== false) {
            // Found in cache, return the posts array to skip the SQL query
            // We also need to set found_posts if it was paginated
            if (isset($cached_results['found_posts'])) {
                $query->found_posts = $cached_results['found_posts'];
                $query->max_num_pages = $cached_results['max_num_pages'];
            }
            return $cached_results['posts'];
        }
    }
    return $posts; // Return null/empty to let WP run the query
}

// Save the query results to cache after WP runs it
add_filter('the_posts', 'merc_container_cache_the_posts', 10, 2);
function merc_container_cache_the_posts($posts, $query) {
    if ($query->get('post_type') === 'wpcargo_container' && !empty($posts)) {
        $cache_key = 'merc_container_query_' . md5(serialize($query->query_vars));
        if (get_transient($cache_key) === false) {
            $cache_data = [
                'posts' => $posts,
                'found_posts' => $query->found_posts,
                'max_num_pages' => $query->max_num_pages
            ];
            // Cache for 30 minutes
            set_transient($cache_key, $cache_data, 30 * MINUTE_IN_SECONDS);
        }
    }
    return $posts;
}

// Invalidate cache on container save
add_action('save_post_wpcargo_container', 'merc_invalidate_container_cache', 10, 3);
function merc_invalidate_container_cache($post_id, $post, $update) {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_merc_container_query_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_merc_container_query_%'");
}

