<?php get_header(); ?>

<main class="photo-grid">
  <?php
  $photos = new WP_Query([
    'post_type' => 'photo',
    'posts_per_page' => 24,
  ]);

  while ($photos->have_posts()):
    $photos->the_post();
  ?>
    <article class="photo">
      <a href="<?php the_permalink(); ?>">
        <?php the_post_thumbnail('large'); ?>
      </a>
      <h2><?php the_title(); ?></h2>
    </article>
  <?php endwhile; wp_reset_postdata(); ?>
</main>

<?php get_footer(); ?>
