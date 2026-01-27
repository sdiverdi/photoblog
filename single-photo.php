<?php get_header(); ?>

<main class="single-photo">
  <?php while (have_posts()): the_post(); ?>
    <article>
      <figure>
        <?php the_post_thumbnail('photo-large'); ?>

        <?php if (get_the_content()): ?>
          <figcaption>
            <?php the_content(); ?>
          </figcaption>
        <?php endif; ?>
      </figure>
    </article>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
