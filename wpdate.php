<?php
/**
 * Plugin Name: WPDate
 * Plugin URI: https://wpdate.com/lunar-calendar
 * Description: 让 WordPress 显示中国农历日期，精准展示万年历、节气及传统节日，支持高度自定义，为您的网站增添东方文化韵味。
 * Version: 1.0.0
 * Author: WPDate.com
 * Author URI: https://wpdate.com
 * License: GPL v2 or later
 * Text Domain: wpdate-calendar
 * Domain Path: /languages
 */


if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('WPLC_VERSION', '1.0.0');
define('WPLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPLC_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载主类
require_once WPLC_PLUGIN_DIR . 'includes/class-lunar-calendar.php';

// 初始化插件
function wpdate_lunar_calendar_init() {
    return WPDate_Lunar_Calendar::get_instance();
}

// 激活插件时的处理
function wpdate_lunar_calendar_activate() {
    // 添加默认选项
    add_option('wplc_enable_lunar', true);
    add_option('wplc_display_format', '【%lunar%】');
    add_option('wplc_show_festivals', true);
    add_option('wplc_show_solar_terms', true);
    
    // 刷新重写规则
    flush_rewrite_rules();
}

// 停用插件时的处理
function wpdate_lunar_calendar_deactivate() {
    // 清理缓存
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wplc_%'");
    
    // 刷新重写规则
    flush_rewrite_rules();
}

// 注册激活和停用钩子
register_activation_hook(__FILE__, 'wpdate_lunar_calendar_activate');
register_deactivation_hook(__FILE__, 'wpdate_lunar_calendar_deactivate');

// 加载语言文件
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'wpdate-calendar',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});

// 启动插件
add_action('plugins_loaded', 'wpdate_lunar_calendar_init');
