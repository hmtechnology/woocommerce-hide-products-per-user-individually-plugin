<?php 
/*
Plugin Name: WooCommerce Hide Products per user individually
Description: Allows to hide specific woocommerce product categories per individual user.
Version: 1.0
Author: hmtechnology
Author URI: https://github.com/hmtechnology
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.txt
Plugin URI: https://github.com/hmtechnology/woocommerce-hide-products-per-user-individually-plugin
*/

// Add Product Cat custom fields to user profile
function add_product_category_settings_to_user_profile($user) {
    $categories = get_terms('product_cat', array('hide_empty' => false, 'parent' => 0));
    $current_user_categories = get_user_meta($user->ID, 'hidden_product_categories', true);
    
    ?>
    <h3><?php _e('Hide Product Categories', 'text-domain'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="hidden_product_categories"><?php _e('Categories to hide', 'text-domain'); ?></label></th>
            <td>
                <?php 
                foreach ($categories as $category) : 
                    $subcategories = get_terms('product_cat', array('hide_empty' => false, 'parent' => $category->term_id));
                    ?>
                    <label>
                        <input type="checkbox" name="hidden_product_categories[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, (array)$current_user_categories)); ?>>
                        <?php echo esc_html($category->name); ?>
                    </label><br>
                    <?php 
                    if ($subcategories) {
                        foreach ($subcategories as $subcategory) {
                            ?>
                            <label style="margin-left: 20px;">
                                <input type="checkbox" name="hidden_product_categories[]" value="<?php echo esc_attr($subcategory->slug); ?>" <?php checked(in_array($subcategory->slug, (array)$current_user_categories)); ?>>
                                <?php echo esc_html($subcategory->name); ?>
                            </label><br>
                            <?php
                        }
                    }
                endforeach; 
                ?>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'add_product_category_settings_to_user_profile');
add_action('edit_user_profile', 'add_product_category_settings_to_user_profile');


// Save custom field to user profile
function save_product_category_settings_for_user($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['hidden_product_categories'])) {
        $hidden_categories = array_map('sanitize_text_field', $_POST['hidden_product_categories']);
        update_user_meta($user_id, 'hidden_product_categories', $hidden_categories);
    } else {
        delete_user_meta($user_id, 'hidden_product_categories');
    }
}
add_action('personal_options_update', 'save_product_category_settings_for_user');
add_action('edit_user_profile_update', 'save_product_category_settings_for_user');


// Function to hide product categories for users
function hide_product_categories_for_users($query) {
    if (is_admin()) {
        return $query;
    }
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_hidden_categories = get_user_meta($current_user->ID, 'hidden_product_categories', true);

        if (!empty($user_hidden_categories)) {
            $tax_query = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $user_hidden_categories,
                    'operator' => 'NOT IN',
                ),
            );

            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('pre_get_posts', 'hide_product_categories_for_users');

// Function to hide product categories in sidebar widget for users
function hide_categories_in_sidebar_for_users($terms, $taxonomies, $args) {
    if (is_admin()) {
        return $terms;
    }
	
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_hidden_categories = get_user_meta($current_user->ID, 'hidden_product_categories', true);

        if (!empty($user_hidden_categories)) {
            foreach ($terms as $key => $term) {
                if (in_array($term->slug, $user_hidden_categories)) {
                    unset($terms[$key]);
                }
            }
        }
    }
    return $terms;
}
add_filter('get_terms', 'hide_categories_in_sidebar_for_users', 10, 3);

// Function to redirect user to shop page if accessing hidden product category or product
function redirect_to_shop_page_for_hidden_categories_or_products() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_hidden_categories = get_user_meta($current_user->ID, 'hidden_product_categories', true);

        if (!empty($user_hidden_categories)) {
            if (is_product()) {
                global $post;
                $product_id = $post->ID;
                $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
                if (array_intersect($user_hidden_categories, $product_categories)) {
                    wp_redirect(get_permalink(wc_get_page_id('shop')));
                    exit;
                }
            }

            if (is_product_category()) {
                $queried_object = get_queried_object();
                if (in_array($queried_object->slug, $user_hidden_categories)) {
                    wp_redirect(get_permalink(wc_get_page_id('shop')));
                    exit;
                }
            }
        }
    }
}
add_action('template_redirect', 'redirect_to_shop_page_for_hidden_categories_or_products');
