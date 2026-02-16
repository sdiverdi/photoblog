<?php get_header(); ?>

<main class="photo-grid" id="photo-grid">
  <?php
  $photos = new WP_Query([
    'post_type' => 'photo',
    'posts_per_page' => 24,
  ]);

  while ($photos->have_posts()):
    $photos->the_post();
    $thumb_id = get_post_thumbnail_id();
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : ''; 
  ?>
    <div class="photo-item">
      <a href="<?php the_permalink(); ?>">
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
