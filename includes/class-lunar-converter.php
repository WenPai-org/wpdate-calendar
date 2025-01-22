<?php
class WPLC_Lunar_Converter {
    // 农历数据表 1900-2100 年
    private static $lunar_info = array(
        0x04bd8,0x04ae0,0x0a570,0x054d5,0x0d260,0x0d950,0x16554,0x056a0,0x09ad0,0x055d2,//1900-1909
        0x04ae0,0x0a5b6,0x0a4d0,0x0d250,0x1d255,0x0b540,0x0d6a0,0x0ada2,0x095b0,0x14977,//1910-1919
        0x04970,0x0a4b0,0x0b4b5,0x06a50,0x06d40,0x1ab54,0x02b60,0x09570,0x052f2,0x04970,//1920-1929
        0x06566,0x0d4a0,0x0ea50,0x06e95,0x05ad0,0x02b60,0x186e3,0x092e0,0x1c8d7,0x0c950,//1930-1939
        0x0d4a0,0x1d8a6,0x0b550,0x056a0,0x1a5b4,0x025d0,0x092d0,0x0d2b2,0x0a950,0x0b557,//1940-1949
        0x06ca0,0x0b550,0x15355,0x04da0,0x0a5b0,0x14573,0x052b0,0x0a9a8,0x0e950,0x06aa0,//1950-1959
        0x0aea6,0x0ab50,0x04b60,0x0aae4,0x0a570,0x05260,0x0f263,0x0d950,0x05b57,0x056a0,//1960-1969
        0x096d0,0x04dd5,0x04ad0,0x0a4d0,0x0d4d4,0x0d250,0x0d558,0x0b540,0x0b6a0,0x195a6,//1970-1979
        0x095b0,0x049b0,0x0a974,0x0a4b0,0x0b27a,0x06a50,0x06d40,0x0af46,0x0ab60,0x09570,//1980-1989
        0x04af5,0x04970,0x064b0,0x074a3,0x0ea50,0x06b58,0x055c0,0x0ab60,0x096d5,0x092e0,//1990-1999
        0x0c960,0x0d954,0x0d4a0,0x0da50,0x07552,0x056a0,0x0abb7,0x025d0,0x092d0,0x0cab5,//2000-2009
        0x0a950,0x0b4a0,0x0baa4,0x0ad50,0x055d9,0x04ba0,0x0a5b0,0x15176,0x052b0,0x0a930,//2010-2019
        0x07954,0x06aa0,0x0ad50,0x05b52,0x04b60,0x0a6e6,0x0a4e0,0x0d260,0x0ea65,0x0d530,//2020-2029
        0x05aa0,0x076a3,0x096d0,0x04afb,0x04ad0,0x0a4d0,0x1d0b6,0x0d250,0x0d520,0x0dd45,//2030-2039
        0x0b5a0,0x056d0,0x055b2,0x049b0,0x0a577,0x0a4b0,0x0aa50,0x1b255,0x06d20,0x0ada0,//2040-2049
    );
    
    // 天干
    private static $gan = array('甲','乙','丙','丁','戊','己','庚','辛','壬','癸');
    // 地支
    private static $zhi = array('子','丑','寅','卯','辰','巳','午','未','申','酉','戌','亥');
    // 生肖
    private static $animals = array('鼠','牛','虎','兔','龙','蛇','马','羊','猴','鸡','狗','猪');
    
    // 节气
    private static $solar_terms = array(
        '小寒','大寒','立春','雨水','惊蛰','春分',
        '清明','谷雨','立夏','小满','芒种','夏至',
        '小暑','大暑','立秋','处暑','白露','秋分',
        '寒露','霜降','立冬','小雪','大雪','冬至'
    );
    
    // 节气数据表
    private static $solar_terms_offset = array(
        '0123', '2112', '4101', '6090',
        '8079', '9068', '1157', '3146',
        '5135', '7124', '9113', '1102',
        '3091', '5080', '7069', '9058',
        '1047', '3036', '5025', '7014',
        '9003', '0992', '2981', '4970'
    );

    // 农历节日
    private static $lunar_festivals = array(
        '0101' => '春节',
        '0115' => '元宵节',
        '0505' => '端午节',
        '0707' => '七夕节',
        '0815' => '中秋节',
        '0909' => '重阳节',
        '1208' => '腊八节',
        '1230' => '除夕'
    );

    // 阳历节日
    private static $solar_festivals = array(
        '0101' => '元旦',
        '0214' => '情人节',
        '0308' => '妇女节',
        '0312' => '植树节',
        '0401' => '愚人节',
        '0501' => '劳动节',
        '0504' => '青年节',
        '0601' => '儿童节',
        '0701' => '建党节',
        '0801' => '建军节',
        '0910' => '教师节',
        '1001' => '国庆节',
        '1225' => '圣诞节'
    );

    public static function solar_to_lunar($year, $month, $day) {
        // 计算与1900年1月31日相差的天数
        $offset = self::get_offset_days($year, $month, $day);
        
        // 计算农历年份
        $i = 1900;
        for($i = 1900; $i < 2101 && $offset > 0; $i++) {
            $year_days = self::get_year_days($i);
            $offset -= $year_days;
        }
        
        if($offset < 0) {
            $offset += $year_days;
            $i--;
        }
        
        $lunar_year = $i;
        
        // 计算农历月份
        $leap = self::get_leap_month($lunar_year);
        $is_leap = false;
        
        for($i = 1; $i < 13 && $offset > 0; $i++) {
            if($leap > 0 && $i == ($leap + 1) && !$is_leap) {
                --$i;
                $is_leap = true;
                $days = self::get_leap_days($lunar_year);
            } else {
                $days = self::get_month_days($lunar_year, $i);
            }
            
            if($is_leap && $i == ($leap + 1)) {
                $is_leap = false;
            }
            
            $offset -= $days;
        }
        
        if($offset == 0 && $leap > 0 && $i == $leap + 1) {
            if($is_leap) {
                $is_leap = false;
            } else {
                $is_leap = true;
                --$i;
            }
        }
        
        if($offset < 0) {
            $offset += $days;
            --$i;
        }
        
        $lunar_month = $i;
        $lunar_day = $offset + 1;
        
        // 计算天干地支年
        $gan_year = ($lunar_year - 4) % 10;
        $zhi_year = ($lunar_year - 4) % 12;
        
        // 构建结果数组
        $result = array(
            'year' => $lunar_year,
            'month' => $lunar_month,
            'day' => $lunar_day,
            'leap' => $is_leap,
            'gan_year' => self::$gan[$gan_year],
            'zhi_year' => self::$zhi[$zhi_year],
            'animal' => self::$animals[$zhi_year]
        );
        
        // 添加节气信息
        $result['solar_term'] = self::get_solar_term($year, $month, $day);
        
        // 添加阳历节日
        $solar_festival_key = sprintf('%02d%02d', $month, $day);
        $result['solar_festival'] = isset(self::$solar_festivals[$solar_festival_key]) 
            ? self::$solar_festivals[$solar_festival_key] 
            : '';
        
        // 添加农历节日
        $lunar_festival_key = sprintf('%02d%02d', $lunar_month, $lunar_day);
        $result['lunar_festival'] = isset(self::$lunar_festivals[$lunar_festival_key]) 
            ? self::$lunar_festivals[$lunar_festival_key] 
            : '';
        
        // 特殊处理除夕
        if ($lunar_month == 12 && 
            $lunar_day == self::get_month_days($lunar_year, 12)) {
            $result['lunar_festival'] = '除夕';
        }
        
        return $result;
    }

    private static function get_offset_days($year, $month, $day) {
        $offset = 0;
        
        for($i = 1900; $i < $year; $i++) {
            if(self::is_leap_year($i)) {
                $offset += 366;
            } else {
                $offset += 365;
            }
        }
        
        for($i = 1; $i < $month; $i++) {
            $offset += self::get_solar_month_days($year, $i);
        }
        
        $offset += $day - 1;
        
        return $offset - 30;
    }

    private static function is_leap_year($year) {
        return ($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0);
    }

    private static function get_solar_month_days($year, $month) {
        $days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        if ($month == 2 && self::is_leap_year($year)) {
            return 29;
        }
        return $days[$month - 1];
    }

    private static function get_year_days($year) {
        $sum = 348;
        for ($i = 0x8000; $i > 0x8; $i >>= 1) {
            $sum += (self::$lunar_info[$year - 1900] & $i) ? 1 : 0;
        }
        return $sum + self::get_leap_days($year);
    }

    private static function get_leap_days($year) {
        if (self::get_leap_month($year)) {
            return (self::$lunar_info[$year - 1900] & 0x10000) ? 30 : 29;
        }
        return 0;
    }

    private static function get_leap_month($year) {
        return self::$lunar_info[$year - 1900] & 0xf;
    }

    private static function get_month_days($year, $month) {
        return (self::$lunar_info[$year - 1900] & (0x10000 >> $month)) ? 30 : 29;
    }

    
private static function get_solar_term($year, $month, $day) {
    $index = ($month - 1) * 2;
    
    $term1_day = floor(self::get_term_day($year, $index));
    $term2_day = floor(self::get_term_day($year, $index + 1));
    
    if ($day == $term1_day) {
        return self::$solar_terms[$index];
    }
    if ($day == $term2_day) {
        return self::$solar_terms[$index + 1];
    }
    return '';
}


private static function get_term_day($year, $n) {
    $offset = self::$solar_terms_offset[$n];
    
    // 确保 offset 是一个有效的字符串
    if (!is_string($offset) || strlen($offset) < 4) {
        return 0; // 返回一个默认值以避免错误
    }
    
    $diff = $year - 1900;
    $century = floor($diff / 100);
    $year_offset = $diff % 100;
    
    // 修正计算公式
    $day = (int)substr($offset, 0, 2) + 
           $century * (int)substr($offset, 2, 1) + 
           floor($year_offset * (int)substr($offset, 3, 1) / 100);
    
    return $day;
}
}
