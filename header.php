<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-R6CE59L849"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-R6CE59L849');
</script>

  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php bloginfo('name'); ?></title>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php if ( ! is_singular('photo') ): ?>
<header>
  <h1>
    <a href="<?php echo esc_url(home_url('/')); ?>">
      <?php bloginfo('name'); ?>
    </a>
  </h1>
  <?php
    if ( is_home() || is_tag() || is_tax() || get_query_var('tag') ) :
      $tag_slugs = array('steve','gabby','zoe');
      $filters = array();
      $filters['everyone'] = array(
        'label' => 'Everyone',
        'link'  => esc_url( home_url('/') ),
      );
      foreach ( $tag_slugs as $s ) {
        $term = get_term_by('slug', $s, 'post_tag');
        if ( $term ) {
          $link = get_tag_link( $term->term_id );
        } else {
          $tag_base = get_option( 'tag_base' );
          if ( ! $tag_base ) {
            $tag_base = 'tag';
          }
          $link = home_url( user_trailingslashit( $tag_base . '/' . rawurlencode( $s ) ) );
        }
        $filters[$s] = array(
          'label' => ucfirst($s),
          'link'  => $link,
        );
      }

      $current = 'everyone';
      // Prefer explicit taxonomy/tag queries
      if ( is_tag() || is_tax() ) {
        $queried = get_queried_object();
        if ( $queried && isset($queried->slug) ) {
          $current = $queried->slug;
        }
      } elseif ( get_query_var('tag') ) {
        $current = get_query_var('tag');
      }

      echo '<p class="now-showing">Now showing: ';
      foreach ( $filters as $slug => $f ) {
        $class = 'now-showing__item';
        if ( $slug === $current ) {
          $class .= ' active';
        }

        if ( $slug === $current ) {
          echo '<span class="' . esc_attr($class) . '">' . esc_html( $f['label'] ) . '</span> ';
        } else {
          echo '<a class="' . esc_attr($class) . '" href="' . esc_url( $f['link'] ) . '">' . esc_html( $f['label'] ) . '</a> ';
        }
      }
      echo '</p>';
    endif;
  ?>
</header>
<?php endif; ?>
