<?php
/**
 * /JB/ 解析接口聚合索引
 * 由 scriptcat.org 油猴脚本转换，仅保留实测可用的解析源
 * 用法：/JB/<脚本ID>.php?url=<官方视频页地址>
 */
header('Content-Type: text/html; charset=utf-8');
$list = [
    '1118' => "文库下载器|百度网盘直链解析下载|全网VIP视频解析无广告播放，全网独创自由选择自动解析接口|短视频无水印下载|淘宝、天",
    '1254' => "💯 懒人专用系列 ——— 全网 VIP 视频破解去广告",
    '1258' => "💯 懒人专用系列 ——— 视频下载",
    '1655' => "武恩赐影视-New",
    '2149' => "【黑猫警长】全网VIP视频免费解析，视频自动跳过广告，高清电视频道免费看",
    '2589' => "全网VIP视频免费破解去广告、【百度文库】破解|可复制下载、全网音乐直接下载、短视频去水印下载:支持抖音、快手等、知乎使",
    '271' => "视频解析",
    '3299' => "🫧404小站 — 🎬VIP追剧神器 | 完全免费 | 支持多平台 | (电脑/手机/平板...自适应)",
    '4107' => "全网VIP视频免费解析去广告【最新3.2】",
    '420' => "吴道友超帅，全网VIP视频免费破解去广告、全网音乐直接下载、知乎增强、百度网盘直接下载等多功能工具箱，功能可独立开关。长",
    '52' => "懒人专用，全网VIP视频免费破解去广告、全网音乐直接下载、知乎增强、短视频无水印下载、百度网盘直接下载等多功能工具箱，功",
    '5716' => "全网VIP视频解析助手",
    '5739' => "视频全平台解析下载助手",
    '5855' => "视频资源聚合搜索器",
    '61' => "懒人工具箱(兼容版)",
    '7648' => "AI视频解析 - 全网VIP视频免费看",
    '7883' => "Æther Player — 全网VIP视频免费解析 · 毛玻璃美学全网首发 · 免费破解去广告",
    '8035' => "全网VIP视频免费解析去广告【最新3.2】",
    '947' => "全网VIP视频免费破解去广告等CTuck",
];
$first = array_key_first($list);
if (!empty($_GET['url'])) {
    header('Location: ' . $first . '.php?url=' . rawurlencode($_GET['url']));
    exit;
}
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>JB 解析接口聚合</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;max-width:900px;margin:32px auto;padding:0 16px;color:#222}
h2{margin-bottom:8px}table{border-collapse:collapse;width:100%;font-size:14px}
td,th{border:1px solid #e3e3e3;padding:8px 10px;text-align:left}th{background:#f7f7f7}
a{color:#0366d6;text-decoration:none}code{background:#f3f3f3;padding:2px 5px;border-radius:3px}
</style></head><body>
<h2>JB 解析接口聚合（共 19 个）</h2>
<p>用法：<code>/JB/脚本ID.php?url=官方视频页地址</code>，返回 JSON <code>{"code":200,"url":"...","ext":"m3u8|mp4|player"}</code>。</p>
<table><tr><th>接口</th><th>原脚本名</th><th>可用解析源</th></tr>
<tr><td><a href="1118.php">1118.php</a></td><td>文库下载器|百度网盘直链解析下载|全网VIP视频解析无广告播放，全网独创自由选择自动解析接口|短视频无水印下载|淘宝、天</td><td>4</td></tr>
<tr><td><a href="1254.php">1254.php</a></td><td>💯 懒人专用系列 ——— 全网 VIP 视频破解去广告</td><td>2</td></tr>
<tr><td><a href="1258.php">1258.php</a></td><td>💯 懒人专用系列 ——— 视频下载</td><td>1</td></tr>
<tr><td><a href="1655.php">1655.php</a></td><td>武恩赐影视-New</td><td>5</td></tr>
<tr><td><a href="2149.php">2149.php</a></td><td>【黑猫警长】全网VIP视频免费解析，视频自动跳过广告，高清电视频道免费看</td><td>2</td></tr>
<tr><td><a href="2589.php">2589.php</a></td><td>全网VIP视频免费破解去广告、【百度文库】破解|可复制下载、全网音乐直接下载、短视频去水印下载:支持抖音、快手等、知乎使</td><td>3</td></tr>
<tr><td><a href="271.php">271.php</a></td><td>视频解析</td><td>2</td></tr>
<tr><td><a href="3299.php">3299.php</a></td><td>🫧404小站 — 🎬VIP追剧神器 | 完全免费 | 支持多平台 | (电脑/手机/平板...自适应)</td><td>5</td></tr>
<tr><td><a href="4107.php">4107.php</a></td><td>全网VIP视频免费解析去广告【最新3.2】</td><td>3</td></tr>
<tr><td><a href="420.php">420.php</a></td><td>吴道友超帅，全网VIP视频免费破解去广告、全网音乐直接下载、知乎增强、百度网盘直接下载等多功能工具箱，功能可独立开关。长</td><td>2</td></tr>
<tr><td><a href="52.php">52.php</a></td><td>懒人专用，全网VIP视频免费破解去广告、全网音乐直接下载、知乎增强、短视频无水印下载、百度网盘直接下载等多功能工具箱，功</td><td>3</td></tr>
<tr><td><a href="5716.php">5716.php</a></td><td>全网VIP视频解析助手</td><td>1</td></tr>
<tr><td><a href="5739.php">5739.php</a></td><td>视频全平台解析下载助手</td><td>1</td></tr>
<tr><td><a href="5855.php">5855.php</a></td><td>视频资源聚合搜索器</td><td>1</td></tr>
<tr><td><a href="61.php">61.php</a></td><td>懒人工具箱(兼容版)</td><td>3</td></tr>
<tr><td><a href="7648.php">7648.php</a></td><td>AI视频解析 - 全网VIP视频免费看</td><td>1</td></tr>
<tr><td><a href="7883.php">7883.php</a></td><td>Æther Player — 全网VIP视频免费解析 · 毛玻璃美学全网首发 · 免费破解去广告</td><td>4</td></tr>
<tr><td><a href="8035.php">8035.php</a></td><td>全网VIP视频免费解析去广告【最新3.2】</td><td>4</td></tr>
<tr><td><a href="947.php">947.php</a></td><td>全网VIP视频免费破解去广告等CTuck</td><td>3</td></tr>
</table></body></html>
