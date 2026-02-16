<?php get_header(); ?>

<main class="photo-grid" id="photo-grid">
  <?php
  // Photo grid query — handles archives and the main listing
  // If a tag query var is present, treat this as a tag archive and query photos with that tag
  $archive_tag_slug = '';
  if ( get_query_var('tag') ) {
    $tag_slug = get_query_var('tag');
    $archive_tag_slug = $tag_slug;
    // Search for a matching term within taxonomies attached to the 'photo' post type
    $photo_taxonomies = get_object_taxonomies('photo');
    $tax_query = array();
    $found = false;
    foreach ($photo_taxonomies as $ptax) {
      $term = get_term_by('slug', $tag_slug, $ptax);
      if ($term && !is_wp_error($term)) {
        $found = true;
        $tax_query[] = array(
          'taxonomy' => $ptax,
          'field' => 'slug',
          'terms' => $tag_slug,
        );
        break;
      }
    }

    if ($found) {
      $photos = new WP_Query([
        'post_type' => 'photo',
        'posts_per_page' => 24,
        'tax_query' => $tax_query,
      ]);
    } else {
      // No matching term for photos — return empty result set
      $photos = new WP_Query([
        'post_type' => 'photo',
        'posts_per_page' => 24,
        'post__in' => array(0),
      ]);
    }

  // If we're on a custom taxonomy archive, use a tax_query targeting the current term
  } elseif ( is_tax() ) {
    $term = get_queried_object();
    // If this taxonomy is attached to photos, remember the slug so links can carry context
    $photo_taxonomies = get_object_taxonomies('photo');
    if (in_array($term->taxonomy, $photo_taxonomies)) {
      $archive_tag_slug = $term->slug;
    }
    $photos = new WP_Query([
      'post_type' => 'photo',
      'posts_per_page' => 24,
      'tax_query' => [[
        'taxonomy' => $term->taxonomy,
        'field' => 'slug',
        'terms' => $term->slug,
      ]],
    ]);

  // Fallback: default photo listing
  } else {
    $photos = new WP_Query([
      'post_type' => 'photo',
      'posts_per_page' => 24,
    ]);
  }

  while ($photos->have_posts()):
    $photos->the_post();
    $thumb_id = get_post_thumbnail_id();
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : ''; 
  ?>
    <div class="photo-item">
      <?php $link = get_permalink(); if ($archive_tag_slug) { $link = add_query_arg('from_tag', rawurlencode($archive_tag_slug), $link); } ?>
      <a href="<?php echo esc_url($link); ?>">
        <?php if ($thumb_url): ?>
          <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" />
        <?php else: ?>
          <span class="photo-placeholder"><?php the_title(); ?></span>
        <?php endif; ?>
      </a>
    </div>
  <?php endwhile; wp_reset_postdata(); ?>
</main>

<?php get_footer(); ?>
