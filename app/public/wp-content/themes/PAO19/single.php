<?php get_header(); ?>
<style>
    .post_blog{
        padding: 20px 0px;
    }
</style>
<div id="content" class="home_content container page_content">
    <div class="left_and_sidebar">
        <div id="home_content_left" class="content_left_part">
            <?php
            if (have_posts()) {
                the_post();

                // Main Post Content
                ?>
                <div class="outer-wrapper single_post single-sport new_container">
                    <div id="post-<?php the_ID(); ?>" <?php post_class('post_inner_content'); ?>>
                        <div class="post_blog">
                            <div class="post_data">
                                <div class="left_post_data">
                                    <span class="post_category">
                                        <?php
                                        $categories = wp_get_post_terms(get_the_ID(), 'category', array('order' => 'ASC', 'fields' => 'all'));
                                        foreach ($categories as $category) {
                                            $term_link = get_term_link($category);
                                            ?>
                                            <a href="<?php echo esc_url($term_link); ?>">
                                                <span class="post_category_<?php echo esc_attr($category->name); ?>">
                                                    <?php echo esc_html($category->name); ?>
                                                </span>
                                            </a>
                                            <?php
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="line"></div>
                            <h2><?php echo esc_html(get_the_title()); ?></h2>
                            <?php
                            $author_id = get_the_author_meta('ID');
                            $author_url = get_author_posts_url($author_id);
                            ?>
                            <span class="post_author"><?php echo "By <a href='" . esc_url($author_url) . "'><span>" . esc_html(get_the_author()) . "</span></a>"; ?></span>
                            <span class="post_date"><?php echo esc_html(get_the_date('M j, Y')); ?></span>
                            <div class="post_all_content">
                                <?php
                                $post_format = get_post_format();
                                $post_thumbnail_id = get_post_thumbnail_id();
                                $post_img_url = wp_get_attachment_url($post_thumbnail_id);

                                if ($post_format != 'video' || !$post_video) {
                                    ?>
                                    <div class="a_post_img_class image" style="background-image:url(<?php echo esc_url($post_img_url); ?>);"></div>
                                    <?php
                                } else {
                                    // Handle video format if needed
                                }

                                // Display post content
                                $post_content = apply_filters('the_content', get_the_content());
                                echo '<div class="text">' . $post_content . '</div>';

                                // Display tags if available
                                the_tags('<div id="pao-tags-content-wrapper"><h3 class="pao-tags-pre">Tags:</h3><ul class="pao-post-tags-wrapper"><li class="pao-post-tag">', '<span class="pao-tag-comma">, </span></li><li class="pao-post-tag">', '</li></ul></div>');
 ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Related Posts Section
                $orig_post = $post;
                $categories = get_the_category($post->ID);

                if ($categories) {
                    $category_ids = array();
                    foreach ($categories as $individual_category) {
                        $category_ids[] = $individual_category->term_id;
                    }

                    $args = array(
                        'category__in' => $category_ids,
                        'post__not_in' => array($post->ID),
                        'posts_per_page' => 2, // Number of related posts to display initially
                        'orderby' => 'date', // Order by date. You can change this as needed
                    );

                    $related_query = new WP_Query($args);

                    if ($related_query->have_posts()) {
                        ?>
                        <div id="related_posts">
                            <div id="related_posts_content" class="row">
                                <?php
                                while ($related_query->have_posts()) {
                                    $related_query->the_post();
                                    ?>
                                    <div id="post-<?php the_ID(); ?>" <?php post_class('outer-wrapper single_post single-sport new_container'); ?>>
                                        <div class="post_inner_content">
                                            <div class="post_blog">
                                                <div class="post_data">
                                                    <div class="left_post_data">
                                                        <span class="post_category">
                                                            <?php
                                                            $related_categories = wp_get_post_terms(get_the_ID(), 'category', array('order' => 'ASC', 'fields' => 'all'));
                                                            foreach ($related_categories as $related_category) {
                                                                $related_term_link = get_term_link($related_category);
                                                                ?>
                                                                <a href="<?php echo esc_url($related_term_link); ?>">
                                                                    <span class="post_category_<?php echo esc_attr($related_category->name); ?>">
                                                                        <?php echo esc_html($related_category->name); ?>
                                                                    </span>
                                                                </a>
                                                                <?php
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="line"></div>
                                                <h2><?php echo esc_html(get_the_title()); ?></h2>
                                                <?php
                                                $related_author_id = get_the_author_meta('ID');
                                                $related_author_url = get_author_posts_url($related_author_id);
                                                ?>
                                                <span class="post_author"><?php echo "By <a href='" . esc_url($related_author_url) . "'><span>" . esc_html(get_the_author()) . "</span></a>"; ?></span>
                                                <span class="post_date"><?php echo esc_html(get_the_date('M j, Y')); ?></span>
                                                <div class="post_all_content">
                                                    <?php
                                                    $related_post_format = get_post_format();
                                                    $related_post_thumbnail_id = get_post_thumbnail_id();
                                                    $related_post_img_url = wp_get_attachment_url($related_post_thumbnail_id);

                                                    if ($related_post_format != 'video' || !$related_post_video) {
                                                        ?>
                                                        <div class="a_post_img_class image" style="background-image:url(<?php echo esc_url($related_post_img_url); ?>);"></div>
                                                        <?php
                                                    } else {
                                                        // Handle video format if needed
                                                    }

                                                    // Display related post content
                                                    $related_post_content = apply_filters('the_content', get_the_content());
                                                    echo '<div class="text">' . $related_post_content . '</div>';

                                                    // Display tags if available
                                                    the_tags('<div id="pao-tags-content-wrapper"><h3 class="pao-tags-pre">Tags:</h3><ul class="pao-post-tags-wrapper"><li class="pao-post-tag">', '<span class="pao-tag-comma">, </span></li><li class="pao-post-tag">', '</li></ul></div>');
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                        wp_reset_postdata();
                    }
                }

                $post = $orig_post;
            } else {
                echo '<p>No posts found.</p>';
            }
            ?>
        </div>
        <div id="sidebar">
            <?php include 'inc/custom-sidebar.php'; ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
<script>
jQuery(function($){
    var page = 1;
    var loading = false;
    var $window = $(window);
    var $content = $('#related_posts_content'); // ID of the container to append related posts

    $window.scroll(function() {
        var scrollHeight = $(document).height();
        var scrollPosition = $window.height() + $window.scrollTop();
        if ((scrollHeight - scrollPosition) / scrollHeight === 0 && !loading) {
            page++;
            loading = true;
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'load_more_related_posts',
                    page: page,
                    post_id: '<?php echo get_the_ID(); ?>'
                },
                success: function(response) {
                    $content.append(response);
                    loading = false;
                }
            });
        }
    });
});
</script>
