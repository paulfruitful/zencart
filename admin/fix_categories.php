<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once('includes/application_top.php');

define('TABLE_PRODUCTS', DB_PREFIX . 'products');
define('TABLE_CATEGORIES', DB_PREFIX . 'categories');
define('TABLE_PRODUCTS_TO_CATEGORIES', DB_PREFIX . 'products_to_categories');

// Get all categories and their product count
$categories_query = "SELECT c.categories_id, COUNT(p2c.products_id) AS product_count
                     FROM " . TABLE_CATEGORIES . " c
                     LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c
                     ON c.categories_id = p2c.categories_id
                     LEFT JOIN " . TABLE_PRODUCTS . " p
                     ON p2c.products_id = p.products_id AND p.products_status = 1
                     WHERE c.categories_status = 1
                     GROUP BY c.categories_id";
$categories_result = $db->Execute($categories_query);

$categories_data = [];
$disabled_categories =[];

// Store categories and their product count in an array
while (!$categories_result->EOF) {
    $category_id = $categories_result->fields['categories_id'];
    $product_count = $categories_result->fields['product_count'];
    
    $categories_data[$category_id]['product_count'] = $product_count;
    
    $categories_result->MoveNext();
}

// Check categories for products and subcategories with products
foreach ($categories_data as $category_id => &$category_info) {
    $parent_id = $category_id;
    $has_products = $category_info['product_count'] > 0;

    while ($parent_id != 0) {
        $parent_query = "SELECT parent_id
                         FROM " . TABLE_CATEGORIES . "
                         WHERE categories_id = " . (int)$parent_id;
        $parent_result = $db->Execute($parent_query);

        $parent_id = $parent_result->fields['parent_id'];
        
        // If a parent category is already disabled, disable the current category as well
        if (in_array($parent_id, $disabled_categories)) {
            $has_products = false;
            break;
        }
        
        // Check parent category for enabled subcategories with products
        $subcategories_query = "SELECT c.categories_id
                                FROM " . TABLE_CATEGORIES . " c
                                LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c
                                ON c.categories_id = p2c.categories_id
                                LEFT JOIN " . TABLE_PRODUCTS . " p
                                ON p2c.products_id = p.products_id AND p.products_status = 1
                                WHERE c.parent_id = " . (int)$parent_id . "
                                AND c.categories_status = 1
                                GROUP BY c.categories_id
                                HAVING COUNT(p2c.products_id) > 0";
        $subcategories_result = $db->Execute($subcategories_query);
        
        if (!$subcategories_result->EOF) {
            break;
        }
    }
    
    // Disable category if it has no products or subcategories with products
    if (!$has_products) {
        $db->Execute("UPDATE " . TABLE_CATEGORIES . " SET categories_status = 0 WHERE categories_id = " . (int)$category_id);
        $disabled_categories[] = $category_id;
    }
}

$db->Close();
?>