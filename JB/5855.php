<?php
/**
 * 解析接口聚合 - 由油猴脚本转换
 * 脚本来源：scriptcat.org 5855
 * 原脚本名：视频资源聚合搜索器
 *
 * 已剔除失效接口，仅保留实测可用解析源。
 *
 * 用法：/5855.php?url=<官方视频页地址>
 * 返回：{"code":200,"msg":"success","url":"...","title":"...","ext":"m3u8|mp4|player"}
 *   - ext=m3u8/mp4 : 直接抽到的播放直链
 *   - ext=player   : 解析接口播放器页地址（用 iframe 嵌入即可播放）
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204); exit;
}

// 实测可用的解析接口（按优先级：可返回直链的排前）
const APIS = [
        "https://jx.m3u8.tv/jiexi/?url="
    ];

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
const TIMEOUT = 15;

function reply(int $code, string $msg, array $extra = []): void {
    http_response_code(200);
    echo json_encode(array_merge(['code' => $code, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetch(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => ['User-Agent: ' . UA, 'Accept: application/json, text/html, */*'],
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r === false ? '' : (string) $r;
}

/** 递归在 JSON 里找视频直链 */
function walk_json($v): string {
    if (is_string($v)) {
        if (preg_match('#^https?://#i', $v) && preg_match('#\.(m3u8|mp4|flv)#i', $v)) return $v;
        return '';
    }
    if (is_array($v)) {
        foreach ($v as $item) {
            $r = walk_json($item);
            if ($r !== '') return $r;
        }
    }
    return '';
}

/** 从响应里抽取视频直链；抽不到返回空串 */
function extract_video(string $html): string {
    if ($html === '') return '';
    $json = json_decode($html, true);
    if (is_array($json)) {
        $u = walk_json($json);
        if ($u !== '') return $u;
        if (!empty($json['data']['url']) && is_string($json['data']['url'])
            && preg_match('#^https?://#i', $json['data']['url'])) {
            return $json['data']['url'];
        }
    }
    if (preg_match('#https?://[^\s"\'\\\\<>]+\.(m3u8|mp4|flv)[^\s"\'\\\\<>]*#i', $html, $m)) return $m[0];
    if (preg_match('#"(?:url|video|play_url|playurl|video_url|downurl|m3u8|src)"\s*:\s*"(https?://[^"]+)"#i', $html, $m)) return $m[1];
    return '';
}

function detect_ext(string $url): string {
    if (stripos($url, '.m3u8') !== false) return 'm3u8';
    if (stripos($url, '.flv') !== false) return 'flv';
    return 'mp4';
}

/** 抓播放页标题 */
function page_title(string $url): string {
    $html = fetch($url);
    if ($html === '') return '';
    $t = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) $t = $m[1];
    elseif (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) $t = $m[1];
    $t = html_entity_decode(trim(strip_tags($t)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_replace('/[【\[][^】\]]*(腾讯视频|爱奇艺|优酷|芒果|哔哩哔哩|B站)[】\]]/u', '', $t) ?? '';
}

$input = trim((string) ($_GET['url'] ?? ''));
if ($input === '') {
    reply(400, '缺少参数 url');
}
$title = preg_match('#^https?://#i', $input) ? page_title($input) : '';
$enc   = rawurlencode($input);

foreach (APIS as $api) {
    $vurl = extract_video(fetch($api . $enc));
    if ($vurl !== '') {
        reply(200, 'success', ['url' => $vurl, 'title' => $title, 'ext' => detect_ext($vurl)]);
    }
}

// 抽不到直链：返回播放器页地址，前端用 iframe 嵌入
reply(200, 'success', ['url' => APIS[0] . $enc, 'title' => $title, 'ext' => 'player']);
