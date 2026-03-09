<?php get_header(); ?>

<?php
$listing_context = photoblog_get_photo_listing_context();
$photos = new WP_Query( $listing_context['query_args'] );
$current_page = isset( $listing_context['query_args']['paged'] ) ? (int) $listing_context['query_args']['paged'] : 1;
$max_pages = (int) $photos->max_num_pages;
?>

<main
  class="photo-grid"
  id="photo-grid"
  data-current-page="<?php echo esc_attr( $current_page ); ?>"
  data-max-pages="<?php echo esc_attr( $max_pages ); ?>"
  data-taxonomy="<?php echo esc_attr( $listing_context['taxonomy'] ); ?>"
  data-term-slug="<?php echo esc_attr( $listing_context['term_slug'] ); ?>"
  data-tag-slug="<?php echo esc_attr( $listing_context['tag_slug'] ); ?>"
>
  <?php
  echo photoblog_render_photo_grid_items( $photos, $listing_context['archive_tag_slug'] );
  wp_reset_postdata();
  ?>
</main>

<div class="photo-grid-status" id="photo-grid-status" aria-live="polite">
  <span class="photo-grid-status__message"></span>
</div>

<div class="photo-grid-sentinel" id="photo-grid-sentinel" aria-hidden="true"></div>

<?php get_footer(); ?>
