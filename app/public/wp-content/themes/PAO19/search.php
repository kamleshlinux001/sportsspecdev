<?php
/**
 * The template for displaying search results pages.
 *
 * @file    search.php
 * @package Wiser Themes
 * @filesource     wp-content/themes/wiserthemes/search.php
 * @since          available since Release 1.0
 */

get_header(); ?>


<div id="content" class="home_content container page_content">
    <div class="left_and_sidebar">
        <div id="home_content_left" class="content_left_part">
            <div class="serchad_value-part col-md-12">
                <h2 class="above_line"><?php echo 'Searched For: ' . esc_html(get_search_query(false)); ?></h2>
                <div class="line"></div>
            </div>
            <div class="isotope i_posts_container filtr-container">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        $post_id = get_the_ID();
                        $post_author_id = $post->post_author;
                        $post_title = $post->post_title;
                        $post_content = $post->post_content;
                        $post_excerpt = $post->post_excerpt;
                        $post_author = $post->post_author;
                        $author = get_the_author_meta('display_name', $post_author);
                        $add_external_link = get_field("add_external_link", $post_id);
                        $target = '';
                        $permalink = "";

                        if (!empty($add_external_link)) {
                            $target = "target='_blank'";
                            $permalink = $add_external_link;
                        } else {
                            $permalink = get_permalink($post_id);
                        }

                        // Define these variables as per your logic
                        $term_id = ''; // Example: get_the_terms($post_id, 'your_taxonomy');
                        $sport_category_for_class = ''; // Example: get_post_meta($post_id, 'sport_category', true);
                        $p_class = ''; // Example: get_post_meta($post_id, 'p_class', true);

                        ?>
                        <div id="i_post_<?php echo $post_id; ?>" data-category="<?php echo esc_attr($term_id); ?>" data-sort="value" data-timestamp="<?php echo strtotime(get_the_date('Y/m/d h:i:s', $post_id)); ?>" class="isotop_elements grid-item filtr-item i_show home_page_posts start <?php echo esc_attr($sport_category_for_class); ?> <?php echo esc_attr($p_class); ?>">
                            <div class="grid-item-inner">
                                <?php
                                if (has_post_thumbnail($post_id)) {
                                    $prevthumb = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'medium');
                                    $featured_image_url = $prevthumb[0];
                                } else {
                                    $featured_image_url = get_template_directory_uri() . '/resources/images/test_photo.png';
                                }
                                ?>
                                <div class="image col-md-4">
                                    <a <?php echo $target; ?> href="<?php echo esc_url($permalink); ?>" style="background-image:url(<?php echo esc_url($featured_image_url); ?>)">
                                        <!--<img src="<?php // echo $featured_image_url; ?>">-->
                                    </a>
                                </div>
                                <div class="text col-md-8">
                                    <h3><a class="" <?php echo $target; ?> href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($post_title); ?></a></h3>
                                    <!--<span class="category_post_author_and_date"><?php // echo $author . ' <i class="fa fa-circle" aria-hidden="true"></i> ' . date("M j,Y", strtotime($post_date)); ?> </span>-->
                                    <?php if (!empty($post_excerpt)) { ?>
                                        <p><?php echo esc_html($post_excerpt); ?></p>
                                    <?php } else { ?>
                                        <p><?php echo wp_trim_words($post_content, 15, '...'); ?></p>
                                    <?php } ?>
                                    <a class="a_read_more_button" <?php echo $target; ?> href="<?php echo esc_url($permalink); ?>">READ MORE</a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    global $wp_query;
                    // Pagination Parameters
                    $pagiArgs = array(
                        'end_size'  => 0,
                        'mid_size'  => 1,
                        'type'      => 'list',
                        'prev_next' => true,
                        'prev_text' => __('Previous'),
                        'next_text' => __('Next'),
                    );

                    ?>
                    <div class="pagenav">
                        <div class="alignleft"><?php previous_posts_link('< Previous', $wp_query->max_num_pages); ?></div>
                        <div class="alignright"><?php next_posts_link('Next >', $wp_query->max_num_pages); ?></div>
                    </div>
                    <?php
                } else {
                    get_template_part('content', 'none');
                }
                ?>
            </div>
        </div>

        <div id="sidebar">
            <?php include 'inc/custom-sidebar.php'; ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
