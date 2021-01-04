<?php

/**
 * 验证码配置，如果需要对某验证码单独配置，可以按‘配置名’=>[]的方式设置
 */
return [
    // 默认配置
    'default' => [
        'char_preset' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // 预设字符集，不支持多字节字符
        'length'     => 5, // 验证码位数
        'width'      => 0, // 图片宽
        'height'     => 0, // 图片高
        'font_size'   => 48, // 验证码字体大小(px)
        'bg'         => [243, 251, 254], // 背景颜色
        'use_curve'   => true, // 是否画混淆曲线
        'use_noise'   => true, // 是否添加杂点
        'use_img_bg'   => true, // 是否使用背景图片
    ],

    // 独立配置
    'sign' => [
        'char_preset' => '0123456789', // 预设字符集
        'length'     => 4, // 验证码位数
        'width'      => 100, // 图片宽
        'height'     => 36, // 图片高
        'font_size'   => 24, // 验证码字体大小(px)
        'use_img_bg'   => false, // 是否使用背景图片
    ],
];
