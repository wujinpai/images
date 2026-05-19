<?php

namespace Verot\Upload;

require __DIR__ . '/function.php';
require __DIR__ . '/class.upload.php';
require __DIR__ . '/cnb_upload.php';
header("Content-type: application/json; charset=utf-8");

if ($config['mustLogin']) {
    if (!is_who_login('status')) {
        exit(json_encode(
            array(
                "result"  => "failed",
                "code"    => 401,
                "message" => "本站已开启登陆上传,您尚未登陆",
            ),
            JSON_UNESCAPED_UNICODE
        ));
    }
}

if (empty($_FILES['file'])) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 204,
            "message" => "没有选择上传的文件",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if (empty($_POST['sign']) || time() - $_POST['sign'] > 12306) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 403,
            "systime" => time(),
            "message" => "上传签名错误,请刷新重试",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if ($config['check_ip']) {
    if (checkIP(null, $config['check_ip_list'], $config['check_ip_model'])) {
        exit(json_encode(
            array(
                "result"  => "failed",
                "code"    => 205,
                "message" => "你不能上传任何文件",
            ),
            JSON_UNESCAPED_UNICODE
        ));
    }
}

if ($config['ip_upload_counts'] > 0 && !is_who_login('status')) {
    if (false === get_ip_upload_log_counts(real_ip())) {
        exit(json_encode(
            array(
                "result"  => "failed",
                "code"    => 202,
                "message" => sprintf("游客限制每日上传 %d 张", $config['ip_upload_counts']),
            ),
            JSON_UNESCAPED_UNICODE
        ));
    }
}

if ($config['chunks']) {
    $chunk = chunk($_POST['name']);
    $handle = new Upload($chunk, 'zh_CN');
} else {
    $handle = new Upload($_FILES['file'], 'zh_CN');
}

if ($handle->uploaded) {
    if ($config['allowed']) {
        $handle->allowed = array('image/*');
    }

    if ($handle->file_src_name_ext === 'svg') {
        $svg = file_get_contents($handle->file_src_pathname);
        if (preg_match('/<script[\s\S]*?<\/script>/', $svg) || stripos($svg, 'href=')) {
            exit(json_encode(
                array(
                    "result"  => "failed",
                    "code"    => 406,
                    "message" => "请勿上传非法文件",
                ),
                JSON_UNESCAPED_UNICODE
            ));
        }
    }

    $handle->file_new_name_body = imgName($handle->file_src_name_body);
    $handle->file_max_size = $config['maxSize'];
    $handle->image_max_width = $config['maxWidth'];
    $handle->image_max_height = $config['maxHeight'];
    $handle->image_min_width = $config['minWidth'];
    $handle->image_min_height = $config['minHeight'];
    if ($handle->file_src_name_ext !== 'webp' && !isGifAnimated($handle->file_src_pathname)) {
        $handle->image_convert = $config['imgConvert'];
    }
    $handle->png_compression = 9 - round($config['compress_ratio'] / 11.2);
    $handle->webp_quality = $config['compress_ratio'];
    $handle->jpeg_quality = $config['compress_ratio'];

    $Img_path = config_path();
    if ($config['admin_path_status']) {
        if (checkLogin() === 204) {
            $Img_path = config_path($config['admin_path'] . date('/Y/m/d/'));
        }
    }
    if ($config['guest_path_status']) {
        if (checkLogin() === 205) {
            $getCok = json_decode($_COOKIE['auth']);
            $Img_path = config_path($getCok[0] . date('/Y/m/d/'));
        }
    }

    $handle->process(APP_ROOT . $Img_path);

    if ($handle->processed) {
        if ($config['md5_black']) {
            $befor_upload_file_md5 = md5_file($handle->file_src_pathname);
            $after_upload_file_md5 = md5_file($handle->file_dst_pathname);
            if (stristr($config['md5_blacklist'], $befor_upload_file_md5) || stristr($config['md5_blacklist'], $after_upload_file_md5)) {
                if (file_exists($handle->file_dst_pathname)) unlink($handle->file_dst_pathname);
                exit(json_encode(
                    array(
                        "result"  => "failed",
                        "code"    => 205,
                        "message" => "当前文件禁止上传",
                    ),
                    JSON_UNESCAPED_UNICODE
                ));
            }
        }

        $pathIMG = $Img_path . $handle->file_dst_name;
        $localFilePath = $handle->file_dst_pathname;

        @water($localFilePath);
        @process_compress($localFilePath);

        if ($config['cnb_status']) {
            $cnbResult = cnb_upload($localFilePath, $handle->file_dst_name);

            if ($cnbResult['success']) {
                $imageUrl = $cnbResult['url'];
                $handleThumb = $cnbResult['url'];
                $delUrl = "Admin closed user delete";
                @unlink($localFilePath);
                cnb_write_index($handle->file_dst_name, $cnbResult['imgPath'], $cnbResult['url'], $handle->file_src_name_body, $handle->file_src_size);
            } else {
                if (file_exists($localFilePath)) @unlink($localFilePath);
                $reJson = array(
                    "result"  => "failed",
                    "code"    => 500,
                    "message" => "上传到 cnb.cool 失败: " . $cnbResult['message'],
                );
                unset($handle);
                exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
            }
        } else {
            $imageUrl = rand_imgurl() . $pathIMG;
            $processUrl = $config['domain'] . $pathIMG;

            if ($config['hide_path'] === 1) {
                $imageUrl = str_replace($config['path'], '/', $imageUrl);
            }

            if ($config['hide'] === 1) {
                $imageUrl = $config['domain'] . '/app/hide.php?key=' . urlHash($pathIMG, 0, crc32($config['hide_key']));
            }

            if ($config['show_user_hash_del']) {
                $delUrl = $config['domain'] . '/app/del.php?hash=' . urlHash($pathIMG, 0);
            } else {
                $delUrl = "Admin closed user delete";
            }

            $handleThumb = $config['domain'] . '/app/thumb.php?img=' . $pathIMG;
            if ($config['thumbnail'] === 2) {
                $handle->image_resize = true;
                $handle->image_x = $config['thumbnail_w'];
                $handle->image_y = $config['thumbnail_h'];
                $handle->image_no_enlarging = true;
                $handle->file_new_name_body = date('Y_m_d_') . $handle->file_dst_name_body;
                $handle->process(APP_ROOT . $config['path'] . 'cache/');
                $handleThumb = $config['domain'] . $config['path'] . 'cache/' . $handle->file_dst_name;
            }

            @any_upload($pathIMG, APP_ROOT . $pathIMG, 'upload');
        }

        $reJson = array(
            "result"  => "success",
            "code"    => 200,
            "url"     => $imageUrl,
            "srcName" => $handle->file_src_name_body,
            "thumb"   => $handleThumb,
            "del"     => $delUrl,
        );
        echo json_encode($reJson, JSON_UNESCAPED_UNICODE);
        $handle->clean();
    } else {
        $reJson = array(
            "result"  =>  "failed",
            "code"    =>  400,
            "message" =>  $handle->error,
            "memory"  => getDistUsed(memory_get_peak_usage()),
        );
        unset($handle);
        exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
    }

    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    @write_ip_upload_count_logs();

    if (!$config['cnb_status']) {
        @write_upload_logs($pathIMG, $handle->file_src_name, $handle->file_dst_pathname, $handle->file_src_size);
        @process_checkImg($processUrl);
    }

    unset($handle);
}
