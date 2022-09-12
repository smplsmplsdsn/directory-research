<?php

// 設定（このファイルを修正しなくても、getパラメータで上書きすることが可能）
$dir_tgt = '';      // 開始ディレクトリ名(末尾はスラッシュなし)
$dir_depth = 'all';      // 取得する階層の深さ
$honban = '';    // URLを書き換える場合は、書き換えたいURL（末尾はスラッシュなし）
$is_link = false;    // リストにリンクをつけたい時「true」いらない場合「false」
$is_download = false;   // csvファイルをダウロードするとき「true」、いらない場合「false」
$extension = 'html, php';    // 取得したい拡張子（複数ある場合はカンマ区切り、全部のときは空にする）
$exclude = '_build, assets';    // 取得対象から除外したいディレクトリ（複数ある場合はカンマ区切り）同名ディレクトリの処理を変更するときは、開始ディレクトリから、スラッシュ区切りで指定する

$remove_title = '';     // 下層ページの共通タイトルテキスト（表示時、除去します）
?>

<?php
// パラメータで上書き用
if ($_GET['dir']) $dir_tgt = $_GET['dir'];
if ($_GET['depth']) $dir_depth = $_GET['depth'].trim();
if ($_GET['url']) $honban = $_GET['url'];
if ($_GET['link'] && $_GET['link'] !== 'false') $is_link = true;
if ($_GET['extension']) $extension = $_GET['extension'];
if ($_GET['title']) $remove_title = $_GET['title'];
if ($_GET['download'] && $_GET['download'] !== 'false') $is_download = true;
if ($_GET['exclude']) $exclude = $_GET['exclude'];

// セキュリティ対策「上部の階層は見れないようにする」
if (strpos($dir_tgt, '..') !== false && empty($_SERVER['HTTPS']) === false) {
    echo 'NG ../';
    die();
}

// 階層の深さ
if (ctype_digit($dir_depth)) {
    $dir_depth = (+$dir_depth);
} else {
    $dir_depth = 'all';
}

// スラッシュ調整
$dir_tgt = rtrim($dir_tgt, '/');

//　定数
$url = dirname((empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
$path = dirname(__FILE__);
$dir_url = ($dir_tgt === '')? $url: $url . '/' . $dir_tgt;
$dir_path = ($dir_tgt === '')? $path: $path . '/' . $dir_tgt;

$extension = preg_replace('/[ 　]+/u', '', $extension);
$array_extension = explode(',', $extension);

$exclude = preg_replace('/[ 　]+/u', '', $exclude);
$array_exclude = explode(',', $exclude);

for ($i = 0; $i < count($array_exclude); $i++) {
    if (strpos($array_exclude[$i], '/') !== false) {
        $array_exclude[$i] = ltrim($array_exclude[$i], '/');
        $array_exclude[$i] = rtrim($array_exclude[$i], '/');
        $array_exclude[$i] = $dir_path . '/' . $array_exclude[$i];
    }
}


// ファイル作成
$is_file_create = false;
$file_num = 0;
$file_path = "tmp-".date('Ymd-Hi-s').".csv";
if ($is_download) {
    if (touch($file_path)) {
        $is_file_create = true;

        $res = new SplFileObject($file_path, "w");
        $res -> fputcsv([]);
    }
}


/**
 * ディレクトリの深さを取得する
 * 
 * @param string $file 調べるパス
 * @return number 整数(1〜)
 */
function get_dir_deep($file) {
    global $dir_path;

    $local_path = str_replace($dir_path, '', $file);
    $dir_count = count(explode('/', $local_path)) - 1;

    return $dir_count;
}

/**
 * HTMLに出力する
 * 
 * @param string $file 調べるパス
 * @param string dir|file $fileがディレクトリか取得したい拡張子のファイルかの情報 
 * @return void
 */
function echo_html($file, $type) {
    global $is_link;
    global $dir_url;
    global $dir_path;
    global $honban;
    global $remove_title;
    global $is_file_create;
    global $res;
    global $file_num;
    global $dir_depth;

    $link = str_replace($dir_path, $dir_url, $file);
    $link = str_replace($dir_url, $honban, $link);

    $num = get_dir_deep($file);
    $num = (+$num);

    $basename = basename($file);

    $title = '';

    if (!is_int($dir_depth) || $dir_depth >= $num) {

        if ($type === 'file') {

            if ($content = file_get_contents($file)) {

                //文字コードをUTF-8に変換し、正規表現でタイトルを抽出
                if (preg_match('/<title(.*?)<\/title>/i', mb_convert_encoding($content, 'utf-8', 'auto'), $result)) {
                    $title = $result[1];
                    $array_title = explode('>', $title);
                    array_shift($array_title);
                    $title = implode('>', $array_title);
                    $title = str_replace($remove_title, '', $title);

                    $title_htmlentities = htmlentities($title, ENT_QUOTES, 'utf-8');

                }
            }
        }

        $html = '';
        $html .= '<li data-num="' . $num . '" data-type="' . $type . '" data-title="' . $title_htmlentities . '">';
        if ($is_link) $html .= '<a href="' . $link . '">';
        $html .= $basename;
        if ($is_link) $html .= '</a>';
        $html .= '</li>';

        echo $html;


        if ($is_file_create) {

            $csv = [];        
            $csv[] = $title;
            if ($type === 'file') {
                $file_num++;
                $csv[] = $file_num;
            } else {
                $csv[] = '';
            }

            if ($is_link) {
                $csv[] = $link;
            }

            for ($i = 1; $i <= $num; $i++) {
                if ($i === $num) {
                    $csv[] = $basename;
                    break;
                } else {
                    $csv[] = '';
                }
            }
            $res -> fputcsv($csv);
        }
    }
}

/**
 * 除外ディレクトリか判別する
 * 
 * @param string $file 調査する文字列
 * @return boolean
 */
function is_exclude($file) {
    global $array_exclude;

    return in_array(basename($file), $array_exclude) || in_array($file, $array_exclude);
}

/**
 * ディレクトリ内をループ処理する
 * 
 * @param string $dir 調べる起点となるディレクトリ
 * @return void
 */
function set_list($dir) {
    global $extension;
    global $array_extension;

    if (is_dir($dir)) {

        foreach (glob($dir . '/{*}', GLOB_BRACE) as $file) {

            if (is_dir($file)) {
                echo_html($file, 'dir');
                if (is_exclude($file)) {
                    continue;
                } else {                 
                    set_list($file);
                }
            } else if (is_file($file)) {
                $fileinfo = pathinfo($file);
                $fileinfo_extension = $fileinfo['extension'];

                if ($extension === '' || in_array($fileinfo_extension, $array_extension)) {
                    echo_html($file, 'file');                    
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ディレクトリ調査</title>
    <style>
        body {font-size: 12px;line-height: 1.5;margin:0; padding: 20px;}
        table {font-size: 100%;border-collapse: collapse}
        th,td {border: 1px solid #ccc;padding: 5px;}
        ul {display:none;}
    </style>
</head>
<body>
    <ul>
        <?php set_list($dir_path); ?>
    </ul>
        
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
    <script>
        (() => {
            let max_ul = 0,
                i = 0,
                file_num = 0,
                html = ''

            // ディレクタの深さを決定する
            $('li').each(function () {                
                max_ul = Math.max(max_ul, $(this).attr('data-num'))
            })

            max_ul++;

            // li を tr・td に変換する
            $('li').each(function (i) {                
                const _this = $(this),
                      num = _this.attr('data-num'),
                      type = _this.attr('data-type')
                
                if (type === 'file') {
                    file_num++
                }
                
                html += '<tr>'
                html += '<td>' + ((type === 'file')? file_num: '') + '</td>'

                for (i = 1; i < max_ul; i++) {
                    html += '<td>' + ((+num === i)? _this.html(): '') + '</td>'
                }
                html += '<td>' + _this.attr('data-title') + '</td>'

                html += '</tr>'
            })

            html = '<table>' + html + '</table>'
            $('body').append(html)
        })()

    </script>
</body>
</html>
