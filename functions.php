<?php

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
});
