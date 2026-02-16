<?php get_header(); ?>

<main class="single-photo">
  <?php while (have_posts()): the_post(); ?>
    <article class="photo-layout">
      <div class="photo-left">
        <?php if (has_post_thumbnail()): the_post_thumbnail('photo-large'); endif; ?>
      </div>

      <aside class="photo-info">
        <h1 class="photo-title"><?php the_title(); ?></h1>

        <?php if (get_the_content()): ?>
          <div class="photo-description"><?php the_content(); ?></div>
        <?php endif; ?>

        <?php
          // Collect terms from any taxonomy attached to this post (fall back if 'post_tag' is empty)
          $post_id = get_the_ID();
          $taxonomies = get_post_taxonomies($post_id);
          $collected = array();
          if (!empty($taxonomies)) {
            foreach ($taxonomies as $tax) {
              // Skip categories and post formats (not tags)
              if (in_array($tax, array('category','post_format'))) continue;
              $terms = get_the_terms($post_id, $tax);
              if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $t) {
                  $collected[] = array(
                    'name' => $t->name,
                    'link' => get_term_link($t),
                    'taxonomy' => $tax
                  );
                }
              }
            }
          }
        ?>

        <?php if (!empty($collected)): ?>
          <div class="photo-tags-wrap">
            <ul class="photo-tags">
              <?php foreach ($collected as $ct): ?>
                <li><a href="<?php echo esc_url($ct['link']); ?>" rel="tag"><?php echo esc_html($ct['name']); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </aside>
    </article>
    
    <?php
      // Newer / Older navigation (explicit):
      // Left = newer, Right = older. If referrer is a tag archive, scope to that tag.
      $newer_link = '';
      $older_link = '';
      $newer_title = '';
      $older_title = '';

      // Prefer an explicit `from_tag` query param (set by archive links). Fall back to referrer.
      $tag_slug = '';
      if (isset($_GET['from_tag']) && $_GET['from_tag']) {
        $tag_slug = wp_unslash($_GET['from_tag']);
      } else {
        $ref = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
        if ($ref) {
          $path = wp_parse_url($ref, PHP_URL_PATH);
          if ($path) {
            $parts = explode('/', trim($path, '/'));
            $tag_index = array_search('tag', $parts);
            if ($tag_index !== false && isset($parts[$tag_index + 1])) {
              $tag_slug = urldecode($parts[$tag_index + 1]);
            }
          }
        }
      }

      $post_type = get_post_type($post_id);
      // Tag-scoped navigation: build an ordered list of IDs in the tag and find neighbors by index
      if ($tag_slug) {
        // Find the correct taxonomy that contains this term for the current post type.
        $term = false;
        $object_taxonomies = get_object_taxonomies($post_type);
        if (!empty($object_taxonomies)) {
          foreach ($object_taxonomies as $tax) {
            $t = get_term_by('slug', $tag_slug, $tax);
            if ($t && !is_wp_error($t)) {
              $term = $t;
              break;
            }
          }
        }

        // Fallback to the common 'post_tag' if not found above.
        if ((!$term || is_wp_error($term))) {
          $t = get_term_by('slug', $tag_slug, 'post_tag');
          if ($t && !is_wp_error($t)) {
            $term = $t;
          }
        }

        if ($term && !is_wp_error($term)) {
          $ids = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'tax_query' => array(array(
              'taxonomy' => $term->taxonomy,
              'field' => 'slug',
              'terms' => $tag_slug,
            )),
          ));

          if (!empty($ids)) {
            $current_index = array_search($post_id, $ids, true);
            if ($current_index !== false) {
              if ($current_index > 0) {
                $newer_id = $ids[$current_index - 1];
                $newer_link = get_permalink($newer_id);
                $newer_title = get_the_title($newer_id);
                $newer_link = add_query_arg('from_tag', rawurlencode($tag_slug), $newer_link);
              }
              if ($current_index < count($ids) - 1) {
                $older_id = $ids[$current_index + 1];
                $older_link = get_permalink($older_id);
                $older_title = get_the_title($older_id);
                $older_link = add_query_arg('from_tag', rawurlencode($tag_slug), $older_link);
              }
            }
          }
        }
      }

      // Global fallback: find nearest newer and older by date
      if (empty($newer_link) || empty($older_link)) {
        $post_date = get_post_time('Y-m-d H:i:s', true, $post_id);

        // Prepare an optional tax_query for fallback queries when a term was found
        $fallback_tax_query = array();
        if (!empty($term) && !is_wp_error($term)) {
          $fallback_tax_query = array(array(
            'taxonomy' => $term->taxonomy,
            'field' => 'slug',
            'terms' => $tag_slug,
          ));
        }

        if (empty($newer_link)) {
          $newer_args = array(
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'ASC',
            'date_query' => array(array(
              'after' => $post_date,
              'inclusive' => false,
            )),
            'fields' => 'ids',
          );
          if (!empty($fallback_tax_query)) {
            $newer_args['tax_query'] = $fallback_tax_query;
          }
          $newer = get_posts($newer_args);
          if ($newer) {
            $newer_link = get_permalink($newer[0]);
            $newer_title = get_the_title($newer[0]);
            if ($tag_slug) {
              $newer_link = add_query_arg('from_tag', rawurlencode($tag_slug), $newer_link);
            }
          }
        }

        if (empty($older_link)) {
          $older_args = array(
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(array(
              'before' => $post_date,
              'inclusive' => false,
            )),
            'fields' => 'ids',
          );
          if (!empty($fallback_tax_query)) {
            $older_args['tax_query'] = $fallback_tax_query;
          }
          $older = get_posts($older_args);
          if ($older) {
            $older_link = get_permalink($older[0]);
            $older_title = get_the_title($older[0]);
            if ($tag_slug) {
              $older_link = add_query_arg('from_tag', rawurlencode($tag_slug), $older_link);
            }
          }
        }
      }
    ?>

    <?php if ($newer_link || $older_link): ?>
      <?php if ($newer_link): ?>
        <a class="photo-nav-edge photo-nav-left" href="<?php echo esc_url($newer_link); ?>" aria-label="Newer photo">&lt;</a>
      <?php endif; ?>
      <?php if ($older_link): ?>
        <a class="photo-nav-edge photo-nav-right" href="<?php echo esc_url($older_link); ?>" aria-label="Older photo">&gt;</a>
      <?php endif; ?>
    <?php endif; ?>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
