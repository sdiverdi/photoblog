<?php

function photoblog_get_photos_per_page() {
  return 24;
}

function photoblog_get_photo_listing_context( $args = array() ) {
  $photo_taxonomies = get_object_taxonomies( 'photo' );
  $paged = 1;

  if ( isset( $args['paged'] ) ) {
    $paged = max( 1, absint( $args['paged'] ) );
  } else {
    $paged = max( 1, absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );
  }

  $context = array(
    'archive_tag_slug' => '',
    'taxonomy' => '',
    'term_slug' => '',
    'tag_slug' => '',
    'query_args' => array(
      'post_type' => 'photo',
      'posts_per_page' => photoblog_get_photos_per_page(),
      'paged' => $paged,
      'orderby' => 'date',
      'order' => 'DESC',
    ),
  );

  $tag_slug = '';
  if ( array_key_exists( 'tag_slug', $args ) ) {
    $tag_slug = sanitize_title( $args['tag_slug'] );
  } elseif ( get_query_var( 'tag' ) ) {
    $tag_slug = sanitize_title( get_query_var( 'tag' ) );
  }

  if ( $tag_slug ) {
    $context['archive_tag_slug'] = $tag_slug;
    $context['tag_slug'] = $tag_slug;

    foreach ( $photo_taxonomies as $photo_taxonomy ) {
      $term = get_term_by( 'slug', $tag_slug, $photo_taxonomy );
      if ( $term && ! is_wp_error( $term ) ) {
        $context['taxonomy'] = $photo_taxonomy;
        $context['term_slug'] = $tag_slug;
        $context['query_args']['tax_query'] = array(
          array(
            'taxonomy' => $photo_taxonomy,
            'field' => 'slug',
            'terms' => $tag_slug,
          ),
        );

        return $context;
      }
    }

    $context['query_args']['post__in'] = array( 0 );

    return $context;
  }

  $taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
  $term_slug = isset( $args['term_slug'] ) ? sanitize_title( $args['term_slug'] ) : '';

  if ( ( ! $taxonomy || ! $term_slug ) && is_tax() ) {
    $term = get_queried_object();
    if ( $term && isset( $term->taxonomy, $term->slug ) && in_array( $term->taxonomy, $photo_taxonomies, true ) ) {
      $taxonomy = $term->taxonomy;
      $term_slug = $term->slug;
    }
  }

  if ( $taxonomy && $term_slug && in_array( $taxonomy, $photo_taxonomies, true ) ) {
    $context['archive_tag_slug'] = $term_slug;
    $context['taxonomy'] = $taxonomy;
    $context['term_slug'] = $term_slug;
    $context['query_args']['tax_query'] = array(
      array(
        'taxonomy' => $taxonomy,
        'field' => 'slug',
        'terms' => $term_slug,
      ),
    );
  }

  return $context;
}

function photoblog_get_photo_label( $post_id ) {
  $label_candidates = array( 'steve', 'zoe', 'gabby' );
  $photo_taxonomies = get_object_taxonomies( 'photo' );

  foreach ( $photo_taxonomies as $photo_taxonomy ) {
    $terms = get_the_terms( $post_id, $photo_taxonomy );
    if ( ! $terms || is_wp_error( $terms ) ) {
      continue;
    }

    foreach ( $terms as $term ) {
      $slug = strtolower( $term->slug );
      if ( in_array( $slug, $label_candidates, true ) ) {
        return $slug;
      }
    }
  }

  return '';
}

function photoblog_get_photo_group_data( $post_id ) {
  return array(
    'key' => get_the_date( 'Y-m', $post_id ),
    'label' => get_the_date( 'F Y', $post_id ),
  );
}

function photoblog_get_photo_group_break_markup( $label ) {
  ob_start();
  ?>
  <div class="photo-group-break" aria-label="<?php echo esc_attr( $label ); ?>">
    <span class="photo-group-break__label"><?php echo esc_html( $label ); ?></span>
  </div>
  <?php

  return trim( ob_get_clean() );
}

function photoblog_get_photo_item_markup( $post_id, $archive_tag_slug = '' ) {
  $thumb_id = get_post_thumbnail_id( $post_id );
  $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
  $photo_label = photoblog_get_photo_label( $post_id );
  $link = get_permalink( $post_id );

  if ( $archive_tag_slug ) {
    $link = add_query_arg( 'from_tag', rawurlencode( $archive_tag_slug ), $link );
  }

  ob_start();
  ?>
  <div class="photo-item" id="photo-<?php echo esc_attr( $post_id ); ?>" data-photo-id="<?php echo esc_attr( $post_id ); ?>">
    <a href="<?php echo esc_url( $link ); ?>">
    <?php if ( $thumb_url ) : ?>
      <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy" />
      <?php if ( $photo_label ) : ?>
      <span class="photo-label"><span class="photo-label-text"><?php echo esc_html( $photo_label ); ?></span></span>
      <?php endif; ?>
    <?php else : ?>
      <span class="photo-placeholder"><?php echo esc_html( get_the_title( $post_id ) ); ?></span>
    <?php endif; ?>
    </a>
  </div>
  <?php

  return trim( ob_get_clean() );
}

function photoblog_render_photo_grid_items( $photos, $archive_tag_slug = '', $starting_group_key = '' ) {
  ob_start();
  $last_group_key = $starting_group_key;

  while ( $photos->have_posts() ) {
    $photos->the_post();
    $group = photoblog_get_photo_group_data( get_the_ID() );
    if ( $group['key'] !== $last_group_key ) {
      echo photoblog_get_photo_group_break_markup( $group['label'] );
      $last_group_key = $group['key'];
    }
    echo photoblog_get_photo_item_markup( get_the_ID(), $archive_tag_slug );
  }

  return ob_get_clean();
}

function photoblog_get_previous_photo_group_key( $listing_context ) {
  $query_args = isset( $listing_context['query_args'] ) ? $listing_context['query_args'] : array();
  $paged = isset( $query_args['paged'] ) ? max( 1, absint( $query_args['paged'] ) ) : 1;

  if ( $paged <= 1 ) {
    return '';
  }

  $previous_offset = ( ( $paged - 1 ) * photoblog_get_photos_per_page() ) - 1;
  if ( $previous_offset < 0 ) {
    return '';
  }

  unset( $query_args['paged'] );
  $query_args['posts_per_page'] = 1;
  $query_args['offset'] = $previous_offset;
  $query_args['fields'] = 'ids';
  $query_args['no_found_rows'] = true;

  $previous_posts = get_posts( $query_args );
  if ( empty( $previous_posts ) ) {
    return '';
  }

  $group = photoblog_get_photo_group_data( $previous_posts[0] );

  return $group['key'];
}

function photoblog_get_photo_archive_page( $post_id, $args = array() ) {
  $listing_context = photoblog_get_photo_listing_context(
    array(
      'taxonomy' => isset( $args['taxonomy'] ) ? $args['taxonomy'] : '',
      'term_slug' => isset( $args['term_slug'] ) ? $args['term_slug'] : '',
      'tag_slug' => isset( $args['tag_slug'] ) ? $args['tag_slug'] : '',
      'paged' => 1,
    )
  );

  $query_args = array(
    'post_type' => 'photo',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'orderby' => 'date',
    'order' => 'DESC',
  );

  if ( isset( $listing_context['query_args']['tax_query'] ) ) {
    $query_args['tax_query'] = $listing_context['query_args']['tax_query'];
  }

  if ( isset( $listing_context['query_args']['post__in'] ) ) {
    $query_args['post__in'] = $listing_context['query_args']['post__in'];
  }

  $post_ids = get_posts( $query_args );
  $post_index = array_search( (int) $post_id, $post_ids, true );

  if ( false === $post_index ) {
    return 1;
  }

  return (int) floor( $post_index / photoblog_get_photos_per_page() ) + 1;
}

// Enable featured images
add_theme_support('post-thumbnails');

// Large image size for single photo view
add_image_size('photo-large', 2400, 0, false);

// Register Photo post type
add_action('init', function () {
  register_post_type('photo', [
    'labels' => [
      'name' => 'Photos',
      'singular_name' => 'Photo',
    ],
    'public' => true,
    'menu_icon' => 'dashicons-camera',
    'supports' => [
      'title',
      'editor',     // caption
      'thumbnail',  // the photo
    ],
    'has_archive' => true,
    'rewrite' => ['slug' => 'photos'],
    'show_in_rest' => false, // classic editor
  ]);

  // Add new taxonomy, NOT hierarchical (like tags)
  $labels = array(
    'name' => _x( 'Tags', 'taxonomy general name' ),
    'singular_name' => _x( 'Tag', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Tags' ),
    'popular_items' => __( 'Popular Tags' ),
    'all_items' => __( 'All Tags' ),
    'parent_item' => null,
    'parent_item_colon' => null,
    'edit_item' => __( 'Edit Tag' ), 
    'update_item' => __( 'Update Tag' ),
    'add_new_item' => __( 'Add New Tag' ),
    'new_item_name' => __( 'New Tag Name' ),
    'separate_items_with_commas' => __( 'Separate tags with commas' ),
    'add_or_remove_items' => __( 'Add or remove tags' ),
    'choose_from_most_used' => __( 'Choose from the most used tags' ),
    'menu_name' => __( 'Tags' ),
  ); 
  register_taxonomy('tag','photo',array(
    'hierarchical' => false,
    'labels' => $labels,
    'show_ui' => true,
    'update_count_callback' => '_update_post_term_count',
    'query_var' => true,
    'rewrite' => array( 'slug' => 'tag' ),
  ));
});

// Remove default Posts & Comments from admin
add_action('admin_menu', function () {
  remove_menu_page('edit.php');        // Posts
  remove_menu_page('edit-comments.php');
});

// Disable comments everywhere
add_action('admin_init', function () {
  foreach (['post', 'page', 'photo'] as $type) {
    remove_post_type_support($type, 'comments');
    remove_post_type_support($type, 'trackbacks');
  }
});

add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style(
    'photoblog-style',
    get_stylesheet_uri(),
    [],
    wp_get_theme()->get('Version')
  );
  // Justified grid script (small, in-theme) to size grid items by image aspect ratio
  wp_enqueue_script(
    'photoblog-justified',
    get_template_directory_uri() . '/assets/js/justified.js',
    [],
    wp_get_theme()->get('Version'),
    true
  );

  wp_localize_script(
    'photoblog-justified',
    'photoblogGrid',
    [
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'nonce' => wp_create_nonce( 'photoblog_load_more_photos' ),
    ]
  );
});

function photoblog_ajax_load_more_photos() {
    check_ajax_referer( 'photoblog_load_more_photos', 'nonce' );

    $page = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
    $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
    $term_slug = isset( $_POST['termSlug'] ) ? sanitize_title( wp_unslash( $_POST['termSlug'] ) ) : '';
    $tag_slug = isset( $_POST['tagSlug'] ) ? sanitize_title( wp_unslash( $_POST['tagSlug'] ) ) : '';

    $listing_context = photoblog_get_photo_listing_context(
        array(
            'paged' => $page,
            'taxonomy' => $taxonomy,
            'term_slug' => $term_slug,
            'tag_slug' => $tag_slug,
        )
    );

    $photos = new WP_Query( $listing_context['query_args'] );
    $starting_group_key = photoblog_get_previous_photo_group_key( $listing_context );
    $html = photoblog_render_photo_grid_items( $photos, $listing_context['archive_tag_slug'], $starting_group_key );
    $max_pages = (int) $photos->max_num_pages;

    wp_reset_postdata();

    wp_send_json_success(
        array(
            'html' => $html,
            'page' => $page,
            'maxPages' => $max_pages,
            'hasMore' => $page < $max_pages,
        )
    );
}
add_action( 'wp_ajax_photoblog_load_more_photos', 'photoblog_ajax_load_more_photos' );
add_action( 'wp_ajax_nopriv_photoblog_load_more_photos', 'photoblog_ajax_load_more_photos' );

// Remove Add Media button from add photo page.
add_filter( 'wp_editor_settings', function( $settings ) {
    $screen = get_current_screen();
    if ( $screen && $screen->post_type == 'photo' ) {
        $settings['media_buttons'] = false;
    }
    return $settings;
});

// Make tags lowercase only
function force_tags_lowercase( $data, $postarr ) {
    if ( isset( $_POST['tags_input'] ) ) {
        $tags = explode( ',', $_POST['tags_input'] );
        $lowercase_tags = array_map( 'strtolower', $tags );
        $_POST['tags_input'] = implode( ',', $lowercase_tags );
    }
    return $data;
}
add_filter( 'wp_insert_post_data', 'force_tags_lowercase', 99, 2 );

function modify_post_type_admin_bar( $args, $post_type ) {
    if ( 'photo' !== $post_type ) {
        $args['show_in_admin_bar'] = false;
    }
    return $args;
}
add_filter( 'register_post_type_args', 'modify_post_type_admin_bar', 10, 2 );
