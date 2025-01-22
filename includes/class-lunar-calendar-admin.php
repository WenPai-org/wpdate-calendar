<?php
class WPLC_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 加载管理页面的脚本和样式
     */
    public function enqueue_admin_scripts($hook) {
        // 只在插件设置页面加载脚本和样式
        if ('settings_page_wpdate-calendar' !== $hook) {
            return;
        }

        // 加载样式文件
        wp_enqueue_style(
            'wpdate-calendar-admin',
            WPLC_PLUGIN_URL . 'assets/css/style.css',
            array(),
            '1.0.0'
        );

        // 加载脚本
        wp_enqueue_script(
            'wpdate-calendar-admin',
            WPLC_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            WPLC_VERSION, // 使用插件版本常量
            true
        );

        // 传递数据到前端
        wp_localize_script(
            'wpdate-calendar-admin', // 脚本句柄，必须与 wp_enqueue_script 中的句柄一致
            'wplcAdmin', // 前端 JavaScript 对象的名称
            array(
                'nonce' => wp_create_nonce('lunar_date_preview'), // 添加 nonce
                'ajaxurl' => admin_url('admin-ajax.php') // 添加 ajaxurl
            )
        );
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            '文派日历设置', // 页面标题
            '农历日期',     // 菜单标题
            'manage_options', // 权限
            'wpdate-calendar', // 菜单 slug
            array($this, 'display_admin_page') // 回调函数
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        // 注册设置项
        register_setting('wplc_options', 'wplc_enable_lunar');
        register_setting('wplc_options', 'wplc_display_items');
        register_setting('wplc_options', 'wplc_display_format');
        register_setting('wplc_options', 'wplc_enable_solar_festival'); // 新增阳历节日设置
        register_setting('wplc_options', 'wplc_show_lunar_in_admin');

        // 添加设置区块
        add_settings_section(
            'wplc_display_section', // 区块 ID
            __('显示设置', 'wpdate-calendar'), // 区块标题
            array($this, 'display_section_info'), // 回调函数
            'wpdate-calendar' // 页面 slug
        );

        // 添加启用农历显示字段
        add_settings_field(
            'wplc_enable_lunar', // 字段 ID
            __('启用农历显示', 'wpdate-calendar'), // 字段标题
            array($this, 'enable_lunar_field'), // 回调函数
            'wpdate-calendar', // 页面 slug
            'wplc_display_section' // 区块 ID
        );

        // 添加在后台右上角显示农历字段
        add_settings_field(
            'wplc_show_lunar_in_admin', // 字段 ID
            __('启用问候显示', 'wpdate-calendar'), // 字段标题
            array($this, 'show_lunar_in_admin_field'), // 回调函数
            'wpdate-calendar', // 页面 slug
            'wplc_display_section' // 区块 ID
        );

        // 添加显示格式字段
        add_settings_field(
            'wplc_display_format', // 字段 ID
            __('显示格式', 'wpdate-calendar'), // 字段标题
            array($this, 'display_format_field'), // 回调函数
            'wpdate-calendar', // 页面 slug
            'wplc_display_section' // 区块 ID
        );

        // 添加显示项目字段
        add_settings_field(
            'wplc_display_items', // 字段 ID
            __('显示项目', 'wpdate-calendar'), // 字段标题
            array($this, 'display_items_field'), // 回调函数
            'wpdate-calendar', // 页面 slug
            'wplc_display_section' // 区块 ID
        );
    }

    /**
     * 启用农历显示字段的回调函数
     */
    public function enable_lunar_field() {
        ?>
        <label>
            <input type="checkbox" name="wplc_enable_lunar" value="1" <?php checked(get_option('wplc_enable_lunar', true)); ?>>
            <?php _e('自动在文章日期旁显示农历', 'wpdate-calendar'); ?>
        </label>
        <?php
    }

    /**
     * 在后台右上角显示农历字段的回调函数
     */
    public function show_lunar_in_admin_field() {
        ?>
        <label>
            <input type="checkbox" name="wplc_show_lunar_in_admin" value="1" <?php checked(get_option('wplc_show_lunar_in_admin', true)); ?>>
            <?php _e('在网站后台右上角显示农历', 'wpdate-calendar'); ?>
        </label>
        <?php
    }

    /**
     * 显示设置区块的描述信息
     */
    public function display_section_info() {
        echo '配置农历日期的显示方式';
    }

    /**
     * 显示格式字段的回调函数
     */
    public function display_format_field() {
        $format = get_option('wplc_display_format', '%date% (%lunar%)');
        ?>
        <input type="text" name="wplc_display_format" value="<?php echo esc_attr($format); ?>" class="regular-text">
        <p class="description" style="text-align: justify;">
            <?php _e('自定义您需要显示的信息，可用日期格式参数：', 'wpdate-calendar'); ?>
            <br><?php _e('原始日期', 'wpdate-calendar'); ?> - <code>%date%</code>
            <br><?php _e('农历年份', 'wpdate-calendar'); ?> - <code>%lunar_year%</code>
            <br><?php _e('农历月份', 'wpdate-calendar'); ?> - <code>%lunar_month%</code>
            <br><?php _e('农历日期', 'wpdate-calendar'); ?> - <code>%lunar_day%</code>
            <br><?php _e('生肖属相', 'wpdate-calendar'); ?> - <code>%zodiac%</code>
            <br><?php _e('农历节日', 'wpdate-calendar'); ?> - <code>%lunar_festival%</code>
            <br><?php _e('阳历节日', 'wpdate-calendar'); ?> - <code>%solar_festival%</code>
            <br><?php _e('完整农历', 'wpdate-calendar'); ?> - <code>%lunar%</code>
        </p>
        <?php
    }

    /**
     * 显示项目字段
     */
    public function display_items_field() {
        $items = get_option('wplc_display_items', array(
            'year' => true,
            'month' => true,
            'day' => true,
            'zodiac' => false,
            'lunar_festival' => true,
            'solar_festival' => true,
            'solar_term' => true
        ));

        // 如果 $items 是字符串，则将其转换为数组
        if (is_string($items)) {
            $items = maybe_unserialize($items);
            if (!is_array($items)) {
                $items = array();
            }
        }

        ?>
        <fieldset>
            <legend class="screen-reader-text">显示项目设置</legend>
            <h4>基本信息</h4>
            <label>
                <input type="checkbox" name="wplc_display_items[year]" value="1" <?php checked(isset($items['year']) ? $items['year'] : false); ?>>
                农历年份
            </label><br>
            <label>
                <input type="checkbox" name="wplc_display_items[month]" value="1" <?php checked(isset($items['month']) ? $items['month'] : false); ?>>
                农历月份
            </label><br>
            <label>
                <input type="checkbox" name="wplc_display_items[day]" value="1" <?php checked(isset($items['day']) ? $items['day'] : false); ?>>
                农历日期
            </label><br>
            <label>
                <input type="checkbox" name="wplc_display_items[zodiac]" value="1" <?php checked(isset($items['zodiac']) ? $items['zodiac'] : false); ?>>
                生肖属相
            </label><br>

            <h4>节日与节气</h4>
            <label>
                <input type="checkbox" name="wplc_display_items[lunar_festival]" value="1" <?php checked(isset($items['lunar_festival']) ? $items['lunar_festival'] : false); ?>>
                农历节日
            </label><br>
            <label>
                <input type="checkbox" name="wplc_display_items[solar_festival]" value="1" <?php checked(isset($items['solar_festival']) ? $items['solar_festival'] : false); ?>>
                阳历节日
            </label><br>
            <label>
            <input type="checkbox" name="wplc_display_items[solar_term]" value="1" <?php checked(isset($items['solar_term']) ? $items['solar_term'] : false); ?>>
            二十四节气
            </label>
        </fieldset>
        <?php
    }


/**
 * 显示管理页面
 */
public function display_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // 获取当前活动的标签页
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'display_settings';

    ?>
    <div class="wrap wplc-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- 标签页导航 -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=wpdate-calendar&tab=display_settings" class="nav-tab <?php echo $active_tab == 'display_settings' ? 'nav-tab-active' : ''; ?>">设置选项</a>
            <a href="?page=wpdate-calendar&tab=about" class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>">关于插件</a>
        </h2>

        <form action="options.php" method="post">
            <?php
            if ($active_tab == 'display_settings') {
                settings_fields('wplc_options');
                do_settings_sections('wpdate-calendar');
                submit_button();
            } elseif ($active_tab == 'about') {
                $this->display_about_tab();
            }
            ?>
        </form>

        <!-- 农历日期预览 -->
        <?php if ($active_tab == 'display_settings') : ?>
            <div id="lunar_date_preview">
                <h3><?php _e('预览效果', 'wpdate-calendar'); ?></h3>
                <p><?php _e('当前设置下的农历日期显示效果：', 'wpdate-calendar'); ?></p>
                <div id="lunar_preview_content">
                    <?php
                    $example_date = date_i18n(get_option('date_format'));
                    $example_lunar = '辛丑年正月初一';
                    $example_format = get_option('wplc_display_format', '%date% %lunar%');

                    $preview_data = array(
                        '%date%' => $example_date,
                        '%lunar%' => $example_lunar,
                        '%lunar_year%' => '辛丑年',
                        '%lunar_month%' => '正月',
                        '%lunar_day%' => '初一',
                        '%zodiac%' => '牛',
                        '%lunar_festival%' => '春节',
                        '%solar_festival%' => '元旦',
                        '%solar_term%' => '立春'
                    );

                    $preview_text = str_replace(
                        array_keys($preview_data),
                        array_values($preview_data),
                        $example_format
                    );

                    echo esc_html($preview_text);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/** 
 * 显示“关于插件”标签页的内容
 */
public function display_about_tab() {
    $image_url = plugins_url('assets/images/wpdate-banner.jpg', dirname(__FILE__));
    ?>
    <div class="about-plugin-tab">
        <h2><?php _e('关于插件', 'wpdate-calendar'); ?></h2>
        <p><?php _e('文派日历（WPDate）是一款独特且实用的免费插件，可以让 WordPress 显示中国农历日期，精准展示万年历、节气及传统节日，支持高度自定义，为您的网站增添东方文化韵味。
', 'wpdate-calendar'); ?></p></br>
        <img src="<?php echo esc_url($image_url); ?>" alt="WPDate Banner" style="max-width: 100%; height: auto;">
        <p><?php _e('创业数十载，不知今夕是何年。
', 'wpdate-calendar'); ?></p></br>
        <p><?php _e('由于工作太忙很多时候会忘记时间和日期，即便假期和节日也会遗漏，未来希望可以多一些时间给到生活。WPDate 插件首发于 2025 年 1 月正值小年寒冬腊月。
', 'wpdate-calendar'); ?></p></br>
        <p><?php _e('春节临近，祝全球华人及中国用户新春快乐，阖家幸福！并且以此推进了文派开源（WenPai.org）项目一小步。
', 'wpdate-calendar'); ?></p></br>
        <h2><?php _e('帮助支持', 'wpdate-calendar'); ?></h2>
        <p><?php _e('关于更多此插件信息，您可以在下面地址找到，这将作为文派·寻鹿建站套件的一部分，我们将会在继续完善。
', 'wpdate-calendar'); ?></p></br>
        <p><?php _e('插件作者：', 'wpdate-calendar'); ?> <a href="https://wenpai.org" target="_blank">文派开源（WenPai.org）↗</a></p>
        <p><?php _e('官方网站：', 'wpdate-calendar'); ?> <a href="https://wpdate.com" target="_blank">WPDate.com ↗</a></p>
        <p><?php _e('插件主页：', 'wpdate-calendar'); ?> <a href="https://wenpai.org/plugins/wpdate-calendar" target="_blank">WenPai.org/plugins/wpdate-calendar</a></p>
        <p><?php _e('代码仓库：', 'wpdate-calendar'); ?> <a href="https://github.com/WenPai-org/wpdate-calendar" target="_blank">/WenPai-org/wpdate-calendar</a></p></br>
    <div class="plugin-copyright">
        <p><?php _e('Copyright © 2025 · WPDate.com , All Rights Reserved. 文派 （广州） 科技有限公司；
', 'wpdate-calendar'); ?></p>
    </div>
    </div>
    </div>
    <?php
}
}