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
