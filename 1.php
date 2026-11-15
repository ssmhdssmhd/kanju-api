<?php
/* ==========================================================================
 * 登录账号（仅在内容需要登录时才会使用，公开内容不会触发登录）
 *   账号：ssmhd
 *   密码：Ma446716536@.      <<< 末尾的英文句点也是密码的一部分
 * ========================================================================== */

/**
 * 官解取流接口
 *
 *   /1.php?url=https%3A%2F%2Fm.v.qq.com%2Fx%2Fm%2Fplay%3Fcid%3Dxxx%26vid%3Dxxx
 *   /1.php?url=空枪                     直接传剧名
 *   /1.php?url=...&ep=12                指定集数
 *   /1.php?url=...&line=1080P-官方R     指定线路
 *
 * 成功：{"code":200,"msg":"success","url":"https://.../index.m3u8","title":"完美世界","ext":"m3u8"}
 * 失败：{"code":404,"msg":"未找到匹配影片"}
 *
 * 匹配策略：官方播放页地址 -> 标题 -> 同名资源列表 -> 每部的所有线路逐条尝试；
 * 某条线路要求登录时自动跳过，继续试其他线路/其他同名资源，全部失败才返回 401。
 *
 * 反射自 看剧AI v0.1.28（com.aimovie.mobile）的 /v1 接口：HMAC-SHA256 请求签名。
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const KJ_BASE   = 'https://kanju1.com';
const KJ_LOGIN_BASE = 'https://kanju.ai';   // 登录接口所在站点
const KJ_KEY    = '557d0e4ae929f438da6bd84412374e6086b8af09b3fed54bf22601d5bf8c54a0';
// 仅当内容返回 playback_user_session_required（需要登录）时才会用到下面这组账号
const KJ_ACCOUNT  = 'ssmhd';
const KJ_PASSWORD = 'Ma446716536@.';   // 末尾的 . 属于密码
// 可选：已经手动抓到 Cookie 时填这里（或请求带 ?cookie=），优先级高于自动登录
const KJ_COOKIE = '';
const UA_MOBILE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
const TIMEOUT   = 20;
const MAX_TRIES = 10;

/** 输出 JSON 并结束（业务码放 body，HTTP 恒 200，方便播放器直接解析） */
function reply(int $code, string $msg, array $extra = []): void
{
    http_response_code(200);
    echo json_encode(array_merge(['code' => $code, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 通用 HTTP 请求
 * @return array{0:int,1:string} [status, body]
 */
function http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        return [0, curl_error($ch)];
    }
    curl_close($ch);
    return [$status, (string) $resp];
}

/**
 * 按需登录：只在播放接口返回「需要登录」时调用。
 * 登录协议取自 APK：POST /auth/native-project-jwt/v1/login
 *   body: {"authProtocol":"aimovie.native-auth.project-jwt.v1","account":"...","password":"..."}
 *   header: Sec-AI-Movie-Native-Auth: aimovie.native-auth.project-jwt.v1
 * 返回 native_jwt，结果缓存，避免重复登录触发风控。
 * @return string 登录得到的 native_jwt；失败返回 ''
 */
function kj_login(): string
{
    static $jwt = null;
    if ($jwt !== null) {
        return $jwt;
    }
    $jwt = '';

    $cache = sys_get_temp_dir() . '/kj_session_' . md5(KJ_ACCOUNT) . '.json';
    if (is_file($cache)) {
        $saved = json_decode((string) @file_get_contents($cache), true);
        if (is_array($saved) && ($saved['expires'] ?? 0) > time() && !empty($saved['jwt'])) {
            $jwt = (string) $saved['jwt'];
            return $jwt;
        }
    }

    $body = json_encode([
        'authProtocol' => 'aimovie.native-auth.project-jwt.v1',
        'account'      => KJ_ACCOUNT,
        'password'     => KJ_PASSWORD,
    ], JSON_UNESCAPED_UNICODE);

    [$status, $raw] = http_request('POST', KJ_LOGIN_BASE . '/auth/native-project-jwt/v1/login', [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: okhttp/4.12.0',
        'Sec-AI-Movie-Native-Auth: aimovie.native-auth.project-jwt.v1',
    ], $body);

    $data = json_decode($raw, true);
    if (is_array($data) && !empty($data['native_jwt']) && is_string($data['native_jwt'])) {
        $jwt = $data['native_jwt'];
        @file_put_contents($cache, json_encode(['jwt' => $jwt, 'expires' => time() + 1800]));
    }
    return $jwt;
}


/**
 * 调用看剧AI接口（/v1 全部需要 HMAC 签名：METHOD\nPATH\n毫秒时间戳\n随机串）
 */
function kj_api(string $method, string $path, ?array $payload = null, ?string $cookieOverride = null, ?string $bearer = null): array
{
    $ts    = (string) (int) (microtime(true) * 1000);
    $nonce = bin2hex(random_bytes(8));
    $sig   = hash_hmac('sha256', $method . "\n" . $path . "\n" . $ts . "\n" . $nonce, KJ_KEY);

    $headers = [
        'x-ai-movie-timestamp: ' . $ts,
        'x-ai-movie-nonce: ' . $nonce,
        'x-ai-movie-signature: ' . $sig,
        'Origin: ' . KJ_BASE,
        'Referer: ' . KJ_BASE . '/',
        'Accept: application/json',
        'User-Agent: ' . UA_MOBILE,
    ];
    $body = null;
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $cookie = $cookieOverride ?? trim((string) ($_GET['cookie'] ?? ''));
    if ($cookie === '') {
        $cookie = KJ_COOKIE;
    }
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    if ($bearer !== null && $bearer !== '') {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }

    [$status, $raw] = http_request($method, KJ_BASE . $path, $headers, $body);
    if ($status === 0) {
        reply(502, '上游连接失败: ' . $raw);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        reply(502, '上游返回异常 (HTTP ' . $status . ')');
    }
    return [$status, $data];
}

/** 抓播放页标题 */
function page_title(string $url): array
{
    [$status, $html] = http_request('GET', $url, [
        'User-Agent: ' . UA_MOBILE,
        'Accept: text/html,application/xhtml+xml',
        'Accept-Language: zh-CN,zh;q=0.9',
    ]);
    if ($status === 0 || $html === '') {
        return ['', ''];
    }

    $title = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<meta[^>]+itemprop=["\']name["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $title = $m[1];
    } elseif (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
        $title = $m[1];
    }

    $title = html_entity_decode(trim(strip_tags($title)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = preg_replace('/[【\[][^】\]]*(腾讯视频|爱奇艺|优酷|芒果|哔哩哔哩|B站)[】\]]/u', '', $title);
    $title = preg_replace('/(在线观看|高清完整版|免费观看|-?\s*(腾讯视频|爱奇艺|优酷|芒果TV|哔哩哔哩)).*$/u', '', $title);
    return [trim((string) $title), $html];
}

/** 从标题里拆出剧名与集数 */
function split_title(string $title): array
{
    $ep = 0;
    if (preg_match('/(?:第\s*)?(\d{1,4})\s*[集话話期]/u', $title, $m)) {
        $ep = (int) $m[1];
    } elseif (preg_match('/[\s_-](\d{1,4})\s*$/u', $title, $m)) {
        $ep = (int) $m[1];
    }
    $name = preg_replace('/(?:第\s*\d{1,4}\s*[集话話期]|[\s_-]\d{1,4}\s*$)/u', '', $title);
    return [trim((string) $name), $ep];
}

/** 归一化用于模糊匹配 */
function norm(string $s): string
{
    return preg_replace('/[^\p{Han}a-zA-Z0-9]/u', '', mb_strtolower($s, 'UTF-8')) ?? '';
}

/** 搜索影片，返回候选列表 [variant_id, title]（按匹配度排序，便于逐个尝试） */
function find_variants(string $name): array
{
    [, $data] = kj_api('GET', '/v1/suggest?q=' . rawurlencode($name));
    $list     = $data['suggestions'] ?? [];
    if (!$list) {
        reply(404, '未找到匹配影片: ' . $name);
    }

    $want  = norm($name);
    $hits  = [];
    foreach ($list as $item) {
        $target = $item['target'] ?? null;
        if (empty($target['variant_id'])) {
            continue;
        }
        $got   = norm((string) ($target['title'] ?? ''));
        $score = 0;
        if ($got !== '' && $got === $want) {
            $score = 100;
        } elseif ($got !== '' && (str_contains($got, $want) || str_contains($want, $got))) {
            $score = 60;
        }
        if ($score === 0) {
            continue;
        }
        if (!empty($target['direct_playable'])) {
            $score += 10;
        }
        $hits[] = [$score, (string) $target['variant_id'], (string) ($target['title'] ?? $name)];
    }
    if (!$hits) {
        reply(404, '未找到匹配影片: ' . $name);
    }
    usort($hits, static fn($a, $b) => $b[0] <=> $a[0]);
    return $hits;
}

/** 取指定集，返回 token */
function find_episode(string $variantId, int $ep): array
{
    [, $data] = kj_api('GET', '/v1/catalog/' . rawurlencode($variantId) . '/episodes');
    $eps      = $data['episodes'] ?? [];
    if (!$eps) {
        return ['', ''];
    }
    // 有集号（剧集）按集号匹配；无集号（电影）优先正片
    foreach ($eps as $item) {
        if ((int) ($item['number'] ?? 0) === $ep && !empty($item['token'])) {
            return [(string) $item['token'], (string) ($item['title'] ?? '')];
        }
    }
    foreach ($eps as $item) {
        if (($item['episode_track'] ?? '') === 'main' && !empty($item['token'])) {
            return [(string) $item['token'], (string) ($item['title'] ?? '')];
        }
    }
    foreach ($eps as $item) {
        if (!empty($item['token'])) {
            return [(string) $item['token'], (string) ($item['title'] ?? '')];
        }
    }
    return ['', ''];
}


/** 线路权重：清晰度高的优先 */
function line_weight(string $label): int
{
    if (preg_match('/4K|2160/i', $label)) {
        return 0;
    }
    if (preg_match('/1080/i', $label)) {
        return 1;
    }
    if (preg_match('/高清|蓝光|HD/i', $label)) {
        return 2;
    }
    return 3;
}

/**
 * 解析线路拿到真实地址
 * 某条线路要求登录时不再直接退出，而是继续尝试同片的其他线路/其他同名资源；
 * 只有全部失败且确实遇到「需要登录」，才把 $needLogin 置 true，由调用方统一返回提示。
 */
function resolve_url(string $token, string $wantLine, bool &$needLogin): ?array
{
    [, $data] = kj_api('GET', '/v1/playback/resolve/' . rawurlencode($token));
    $lines    = $data['line_options'] ?? [];
    if (!$lines) {
        return null;
    }

    if ($wantLine !== '') {
        usort($lines, static fn($a, $b) =>
            (str_contains((string) ($b['label'] ?? ''), $wantLine) <=> str_contains((string) ($a['label'] ?? ''), $wantLine)));
    } else {
        usort($lines, static fn($a, $b) => line_weight((string) ($a['label'] ?? '')) <=> line_weight((string) ($b['label'] ?? '')));
    }

    $fallback = null;
    $locked   = [];   // 需要登录的线路，留到最后重试
    $tries    = 0;
    foreach ($lines as $line) {
        if ($tries >= MAX_TRIES) {
            break;
        }
        $ticket = str_replace('resolve://', '', (string) ($line['url'] ?? ''));
        if ($ticket === '') {
            continue;
        }
        [$status, $res] = kj_api('POST', '/v1/playback/resolve-line?view=compact', ['ticket' => $ticket]);

        // 该线路需要登录：先跳过，试试其他公开线路
        if (($res['code'] ?? '') === 'playback_user_session_required') {
            $needLogin = true;
            $locked[]  = [$ticket, (string) ($line['label'] ?? '')];
            continue;
        }
        $tries++;
        $url  = (string) ($res['line']['url'] ?? '');
        $kind = (string) ($res['line']['url_kind'] ?? '');
        if ($status !== 201 && $status !== 200) {
            continue;
        }
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }
        $label = (string) ($line['label'] ?? '');
        if ($kind === 'm3u8' || str_contains($url, '.m3u8')) {
            return [$url, 'm3u8', $label];
        }
        if ($fallback === null) {
            $fallback = [$url, $kind !== '' ? $kind : 'mp4', $label];
        }
    }
    if ($fallback !== null) {
        return $fallback;
    }

    // 全是需要登录的线路：账号自动登录后重试一次（登录态有效时会直接成功）
    if ($locked) {
        static $loginTried = false;
        $jwt = '';
        if (!$loginTried && trim((string) ($_GET['cookie'] ?? '')) === '' && KJ_COOKIE === '') {
            $loginTried = true;
            $jwt        = kj_login();
        }
        if ($jwt !== '') {
            foreach ($locked as [$ticket, $label]) {
                [$status, $res] = kj_api('POST', '/v1/playback/resolve-line?view=compact', ['ticket' => $ticket], null, $jwt);
                $url  = (string) ($res['line']['url'] ?? '');
                $kind = (string) ($res['line']['url_kind'] ?? '');
                if (($status === 201 || $status === 200) && preg_match('#^https?://#i', $url)) {
                    return [$url, $kind === 'm3u8' || str_contains($url, '.m3u8') ? 'm3u8' : ($kind ?: 'mp4'), $label];
                }
            }
        }
    }
    return null;
}

/* ------------------------------ 主流程 ------------------------------ */

$input    = trim((string) ($_GET['url'] ?? $_GET['title'] ?? ''));
$wantEp   = isset($_GET['ep']) ? max(0, (int) $_GET['ep']) : 0;
$wantLine = trim((string) ($_GET['line'] ?? ''));

if ($input === '') {
    reply(400, '缺少参数 url，例如 /1.php?url=' . rawurlencode('https://m.v.qq.com/x/m/play?cid=xxx&vid=xxx'));
}

$title = $input;
if (preg_match('#^https?://#i', $input)) {
    [$title] = page_title($input);
    if ($title === '') {
        reply(502, '播放页抓取失败，请直接传剧名: /1.php?url=' . rawurlencode('影片名'));
    }
}

[$name, $titleEp] = split_title($title);
if ($name === '') {
    $name = $title;
}
$ep = $wantEp > 0 ? $wantEp : max(1, $titleEp);

$reason    = '未找到可播放线路';
$needLogin = false;
foreach (find_variants($name) as [, $variantId, $showTitle]) {
    [$token] = find_episode($variantId, $ep);
    if ($token === '') {
        $reason = '该影片没有可播放剧集';
        continue;
    }
    $resolved = resolve_url($token, $wantLine, $needLogin);
    if ($resolved === null) {
        continue;
    }
    reply(200, 'success', [
        'url'   => $resolved[0],
        'title' => $showTitle,
        'ext'   => $resolved[1],
    ]);
}

if ($needLogin) {
    reply(401, '该内容的所有公开线路都需要登录，请把 App 的 Cookie 填入 KJ_COOKIE 或请求带 ?cookie=');
}
reply(404, $reason);

