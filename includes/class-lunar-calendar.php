<?php
class WPDate_Lunar_Calendar {
    private static $instance = null;
    private $admin;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->admin = new WPLC_Admin(); // 初始化管理类
    }

    private function load_dependencies() {
        require_once WPLC_PLUGIN_DIR . 'includes/class-lunar-converter.php';
        require_once WPLC_PLUGIN_DIR . 'includes/class-lunar-calendar-admin.php';
    }

    private function init_hooks() {
        // 日期显示钩子
        add_filter('get_the_date', array($this, 'append_lunar_date'), 10, 2);
        add_filter('the_date', array($this, 'append_lunar_date'), 10, 2);
        add_filter('get_the_time', array($this, 'append_lunar_date'), 10, 2);
        add_filter('the_time', array($this, 'append_lunar_date'), 10, 2);
        add_filter('get_the_modified_date', array($this, 'append_lunar_date'), 10, 2);
        add_filter('the_modified_date', array($this, 'append_lunar_date'), 10, 2);

        // 添加样式和脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_loaded', array($this, 'register_ajax_handlers'));
        add_action('init', array($this, 'register_shortcode'));

        // 在后台右上角显示农历
        add_action('admin_notices', array($this, 'display_lunar_in_admin'));
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'wpdate-calendar',
            WPLC_PLUGIN_URL . 'assets/css/lunar-calendar.css',
            array(),
            WPLC_VERSION
        );
    }

    public function get_lunar_date($solar_date) {
        $cache_key = 'wplc_' . md5($solar_date);
        $lunar_date = get_transient($cache_key);

        if (false !== $lunar_date) {
            return $lunar_date;
        }

        $date = date_parse($solar_date);
        if (!$date || !isset($date['year'], $date['month'], $date['day'])) {
            return '';
        }

        $lunar = WPLC_Lunar_Converter::solar_to_lunar(
            $date['year'],
            $date['month'],
            $date['day']
        );

        if (!$lunar) {
            return '';
        }

        $festival = '';
        if (get_option('wplc_show_festivals', true)) {
            $festival = WPLC_Lunar_Converter::get_festival($lunar['month'], $lunar['day']);
        }

        $solar_term = '';
        if (get_option('wplc_show_solar_terms', true)) {
            $solar_term = WPLC_Lunar_Converter::get_solar_term($date['month'], $date['day']);
        }

        $lunar_text = sprintf(
            '%s年%s月%s%s',
            WPLC_Lunar_Converter::get_chinese_year($lunar['year']),
            WPLC_Lunar_Converter::get_chinese_month($lunar['month'], $lunar['leap']),
            WPLC_Lunar_Converter::get_chinese_day($lunar['day']),
            ($festival ? " {$festival}" : '') . ($solar_term ? " {$solar_term}" : '')
        );

        set_transient($cache_key, $lunar_text, DAY_IN_SECONDS);
        return $lunar_text;
    }

    public function append_lunar_date($the_date, $format = '') {
    // 排除RSS订阅和后台请求
    if (is_feed() || is_admin()) {
        return $the_date;
    }

    // 检查是否启用农历显示
    if (!get_option('wplc_enable_lunar', true)) {
        return $the_date;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return $the_date;
    }

    $post_date = get_post_time('Y-m-d', false, $post_id);
    if (!$post_date) {
        return $the_date;
    }

    $lunar_info = $this->get_lunar_date_info($post_date);
    if (!$lunar_info) {
        return $the_date;
    }

    $replacements = array(
        '%date%'           => $the_date,
        '%lunar_year%'     => $lunar_info['year']. '年', 
        '%lunar_month%'    => $lunar_info['month']. '月',
        '%lunar_day%'      => $lunar_info['day'],
        '%zodiac%'         => $lunar_info['zodiac'], 
        '%lunar_festival%' => $lunar_info['lunar_festival'],
        '%solar_festival%' => $lunar_info['solar_festival'],
        '%festival%'       => $lunar_info['festival'],
        '%solar_term%'     => $lunar_info['solar_term'],
        '%lunar%'          => sprintf('%s%s%s', 
                            $lunar_info['year'],
                            $lunar_info['month'],
                            $lunar_info['day']
                        )
    );

    $display_format = get_option('wplc_display_format', '%date% (%lunar%)');
    $display_items = get_option('wplc_display_items', array(
        'year' => true,
        'month' => true,
        'day' => true,
        'zodiac' => false,
        'festival' => true,
        'solar_term' => true
    ));

    if (!$display_items['year']) {
        $replacements['%lunar_year%'] = '';
    }
    if (!$display_items['month']) {
        $replacements['%lunar_month%'] = '';
    }
    if (!$display_items['day']) {
        $replacements['%lunar_day%'] = '';
    }
    if (!$display_items['zodiac']) {
        $replacements['%zodiac%'] = '';
    }
    if (!$display_items['festival']) {
        $replacements['%festival%'] = '';
    }
    if (!$display_items['solar_term']) {
        $replacements['%solar_term%'] = '';
    }

    $final_text = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $display_format
    );

    $final_text = preg_replace('/\s+/', ' ', $final_text);
    $final_text = preg_replace('/\(\s*\)/', '', $final_text);
    $final_text = trim($final_text);

    return sprintf('<span class="lunar-date">%s</span>', $final_text);
}

    private function get_lunar_date_info($date) {
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return false;
    }

    list($year, $month, $day) = explode('-', date('Y-m-d', $timestamp));
    $lunar = WPLC_Lunar_Converter::solar_to_lunar($year, $month, $day);

    if (!$lunar) {
        return false;
    }

    $festivals = $this->get_festivals($lunar['month'], $lunar['day']);
    $solar_term = $this->get_solar_term($year, $month, $day);

    return array(
        'year'           => $this->get_lunar_year($lunar['year']),
        'month'          => $this->get_lunar_month($lunar['month']),
        'day'            => $this->get_lunar_day($lunar['day']),
        'zodiac'         => $this->get_zodiac($lunar['year']),
        'lunar_festival' => $festivals['lunar'],
        'solar_festival' => $festivals['solar'],
        'festival'       => $festivals['combined'],
        'solar_term'     => $solar_term
    );
    }

    private function get_lunar_year($year) {
    $heavenly_stems = array('甲','乙','丙','丁','戊','己','庚','辛','壬','癸');
    $earthly_branches = array('子','丑','寅','卯','辰','巳','午','未','申','酉','戌','亥');
    $zodiac = array('鼠','牛','虎','兔','龙','蛇','马','羊','猴','鸡','狗','猪');

    $year = $year - 4;
    $heavenly_stem = $heavenly_stems[$year % 10];
    $earthly_branch = $earthly_branches[$year % 12];
    $zodiac_name = $zodiac[$year % 12];

    return $heavenly_stem . $earthly_branch . $zodiac_name;
    }

    private function get_lunar_month($month) {
        $months = array('正','二','三','四','五','六','七','八','九','十','冬','腊');
        return $months[$month - 1];
    }

    private function get_lunar_day($day) {
        $days = array('初一','初二','初三','初四','初五','初六','初七','初八','初九','初十',
                     '十一','十二','十三','十四','十五','十六','十七','十八','十九','二十',
                     '廿一','廿二','廿三','廿四','廿五','廿六','廿七','廿八','廿九','三十');
        return $days[$day - 1];
    }

    private function get_zodiac($year) {
    $zodiac = array('鼠','牛','虎','兔','龙','蛇','马','羊','猴','鸡','狗','猪');
    return $zodiac[($year - 4) % 12];
    }

    private function get_festivals($lunar_month, $lunar_day) {
        $lunar_festivals = array(
            '1-1'   => '春节',
            '1-15'  => '元宵节',
            '2-2'   => '龙抬头',
            '5-5'   => '端午节',
            '7-7'   => '七夕节',
            '7-15'  => '中元节',
            '8-15'  => '中秋节',
            '9-9'   => '重阳节',
            '10-1'  => '寒衣节',
            '10-15' => '下元节',
            '12-8'  => '腊八节',
            '12-23' => '小年',
            '12-30' => '除夕'
        );

        $solar_festivals = array(
            '1-1'   => '元旦',
            '2-14'  => '情人节',
            '3-8'   => '妇女节',
            '3-12'  => '植树节',
            '4-1'   => '愚人节',
            '5-1'   => '劳动节',
            '5-4'   => '青年节',
            '6-1'   => '儿童节',
            '9-10'  => '教师节',
            '10-1'  => '国庆节',
            '12-24' => '平安夜',
            '12-25' => '圣诞节'
        );

        $lunar_key = $lunar_month . '-' . $lunar_day;
        $solar_key = date('n-j');

        $lunar_festival = isset($lunar_festivals[$lunar_key]) ? $lunar_festivals[$lunar_key] : '';
        $solar_festival = isset($solar_festivals[$solar_key]) ? $solar_festivals[$solar_key] : '';

        $combined = array_filter(array($lunar_festival, $solar_festival));

        return array(
            'lunar' => $lunar_festival,
            'solar' => $solar_festival,
            'combined' => implode('/', $combined)
        );
    }

    private function get_solar_term($year, $month, $day) {
        $terms = array(
            array(6.11,20.84,4.6295,    '小寒','立春','惊蛰'),
            array(20.84,35.6,19.4599,   '大寒','雨水','春分'),
            array(35.6,50.36,34.2904,   '立春','惊蛰','清明'),
            array(50.36,65.12,49.1208,  '雨水','春分','谷雨'),
            array(65.12,79.88,63.9513,  '惊蛰','清明','立夏'),
            array(79.88,94.64,78.7817,  '春分','谷雨','小满'),
            array(94.64,109.4,93.6122,  '清明','立夏','芒种'),
            array(109.4,124.16,108.4426,'谷雨','小满','夏至'),
            array(124.16,138.92,123.2731,'立夏','芒种','小暑'),
            array(138.92,153.68,138.1035,'小满','夏至','大暑'),
            array(153.68,168.44,152.934,'芒种','小暑','立秋'),
            array(168.44,183.2,167.7644,'夏至','大暑','处暑'),
            array(183.2,197.96,182.5949,'小暑','立秋','白露'),
            array(197.96,212.72,197.4253,'大暑','处暑','秋分'),
            array(212.72,227.48,212.2558,'立秋','白露','寒露'),
            array(227.48,242.24,227.0862,'处暑','秋分','霜降'),
            array(242.24,257,241.9167,  '白露','寒露','立冬'),
            array(257,271.76,256.7471,  '秋分','霜降','小雪'),
            array(271.76,286.52,271.5776,'寒露','立冬','大雪'),
            array(286.52,301.28,286.408,'霜降','小雪','冬至'),
            array(301.28,316.04,301.2385,'立冬','大雪','小寒'),
            array(316.04,330.8,316.0689,'小雪','冬至','大寒'),
            array(330.8,345.56,330.8994,'大雪','小寒','立春'),
            array(345.56,360.32,345.7298,'冬至','大寒','雨水')
        );

        // 计算节气
        $term_name = '';
        $solar_longitude = $this->get_solar_longitude($year, $month, $day);

        foreach ($terms as $term) {
            if ($solar_longitude >= $term[0] && $solar_longitude < $term[1]) {
                if (abs($solar_longitude - $term[2]) < 0.5) {
                    $term_name = $term[3];
                }
                break;
            }
        }

        return $term_name;
    }

    private function get_solar_longitude($year, $month, $day) {
        $days = floor(365.242 * ($year - 2000) + 30.42 * $month + $day - 21);
        return ($days * 0.985647 + 15) % 360;
    }

    public function register_shortcode() {
        add_shortcode('lunar_date', array($this, 'lunar_date_shortcode'));
    }

    public function lunar_date_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'format' => get_option('wplc_display_format', '%date% (%lunar%)'),
            'date' => ''
        ), $atts, 'lunar_date');

        if (!empty($atts['date'])) {
            $post_date = $atts['date'];
        } else {
            $post = get_post($atts['post_id']);
            if (!$post) {
                return '';
            }
            $post_date = $post->post_date;
        }

        $lunar_info = $this->get_lunar_date_info($post_date);
        if (!$lunar_info) {
            return '';
        }

        $replacements = array(
            '%date%'           => date_i18n(get_option('date_format'), strtotime($post_date)),
            '%lunar_year%'     => $lunar_info['year'] . '年',
            '%lunar_month%'    => $lunar_info['month'] . '月',
            '%lunar_day%'      => $lunar_info['day'],
            '%zodiac%'         => $lunar_info['zodiac'],
            '%lunar_festival%' => $lunar_info['lunar_festival'],
            '%solar_festival%' => $lunar_info['solar_festival'],
            '%festival%'       => $lunar_info['festival'],
            '%solar_term%'     => $lunar_info['solar_term'],
            '%lunar%'          => sprintf('%s年%s月%s', 
                                $lunar_info['year'],
                                $lunar_info['month'],
                                $lunar_info['day']
                            )
        );

        $final_text = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $atts['format']
        );

        return sprintf('<span class="lunar-date">%s</span>', $final_text);
    }

    public function register_ajax_handlers() {
        add_action('wp_ajax_lunar_date_preview', array($this, 'handle_preview_ajax'));
    }

    public function handle_preview_ajax() {
        check_ajax_referer('lunar_date_preview', 'nonce');

        $format = sanitize_text_field($_POST['format']);
        $display_items = isset($_POST['display_items']) ? array_map('sanitize_text_field', $_POST['display_items']) : array();

        $sample_date = current_time('mysql');
        $lunar_info = $this->get_lunar_date_info($sample_date);

        if (!$lunar_info) {
            wp_send_json_error(array('message' => '无法获取农历信息'));
        }

        $replacements = array(
            '%date%'           => date_i18n(get_option('date_format'), strtotime($sample_date)),
            '%lunar_year%'     => $lunar_info['year'] . '年',
            '%lunar_month%'    => $lunar_info['month'] . '月',
            '%lunar_day%'      => $lunar_info['day'],
            '%zodiac%'         => $lunar_info['zodiac'],
            '%lunar_festival%' => $lunar_info['lunar_festival'],
            '%solar_festival%' => $lunar_info['solar_festival'],
            '%festival%'       => $lunar_info['festival'],
            '%solar_term%'     => $lunar_info['solar_term'],
            '%lunar%'          => sprintf('%s年%s月%s', 
                                $lunar_info['year'],
                                $lunar_info['month'],
                                $lunar_info['day']
                            )
        );

        if (!$display_items['year']) {
            $replacements['%lunar_year%'] = '';
        }
        if (!$display_items['month']) {
            $replacements['%lunar_month%'] = '';
        }
        if (!$display_items['day']) {
            $replacements['%lunar_day%'] = '';
        }
        if (!$display_items['zodiac']) {
            $replacements['%zodiac%'] = '';
        }
        if (!$display_items['festival']) {
            $replacements['%festival%'] = '';
        }
        if (!$display_items['solar_term']) {
            $replacements['%solar_term%'] = '';
        }

        $final_text = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $format
        );

        $final_text = preg_replace('/\s+/', ' ', $final_text);
        $final_text = preg_replace('/\(\s*\)/', '', $final_text);
        $final_text = trim($final_text);

        wp_send_json_success(array(
            'preview' => sprintf(
                '<div class="preview-title">%s</div><div class="preview-content">%s</div>',
                __('预览效果：', 'wpdate-calendar'),
                $final_text
            )
        ));
    }

/**
 * 在后台右上角显示农历日期
 */
public function display_lunar_in_admin() {
    if (!get_option('wplc_show_lunar_in_admin', true)) {
        return;
    }

    $screen = get_current_screen();
    if ($screen->id !== 'dashboard') {
        return;
    }

    $current_date = current_time('Y-m-d');

    $lunar_info = $this->get_lunar_date_info($current_date);
    if (!$lunar_info) {
        return;
    }

    $lunar_text = sprintf(
        '%s年%s月%s',
        $lunar_info['year'],
        $lunar_info['month'],
        $lunar_info['day']
    );

    echo '
    <div id="lunar-date-admin" style="float: right; padding: 5px 10px; margin: 0; font-size: 12px; line-height: 1.6666;">
        ' . esc_html($lunar_text) . '
    </div>
    ';
}

}