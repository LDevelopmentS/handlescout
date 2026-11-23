<?php
// HandleScout PHP backend. Upload next to index.html. No Node needed (PHP 7.4+ with cURL).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$h = trim($_GET['u'] ?? '');
if (!preg_match('/^[A-Za-z0-9._-]{1,40}$/', $h)) { http_response_code(400); echo '[]'; exit; }

// [name, check URL, options]  unk = site blocks bots, never claim found/free; miss = regex on a 200 "no such user" page
$SITES = [
 ['Instagram','https://www.instagram.com/{u}/',['unk'=>1]],
 ['Threads','https://www.threads.net/@{u}',['unk'=>1]],
 ['Twitter / X','https://x.com/{u}',['unk'=>1]],
 ['Facebook','https://www.facebook.com/{u}',['unk'=>1]],
 ['TikTok','https://www.tiktok.com/@{u}',['unk'=>1]],
 ['LinkedIn','https://www.linkedin.com/in/{u}',['unk'=>1]],
 ['Snapchat','https://www.snapchat.com/add/{u}',['unk'=>1]],
 ['Steam','https://steamcommunity.com/id/{u}',['miss'=>'/could not be found/i']],
 ['Etsy','https://www.etsy.com/people/{u}',['unk'=>1]],
 ['VSCO','https://vsco.co/{u}',['unk'=>1]],
 ['Reddit','https://www.reddit.com/user/{u}/about.json',['api'=>'reddit','page'=>'https://www.reddit.com/user/{u}']],
 ['Telegram','https://t.me/{u}',['miss'=>'/<title>Telegram: Contact/i']],
 ['YouTube','https://www.youtube.com/@{u}',[]],
 ['Bluesky','https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor={u}.bsky.social',['api'=>'bsky','page'=>'https://bsky.app/profile/{u}.bsky.social']],
 ['Mastodon','https://mastodon.social/@{u}',[]],
 ['Vimeo','https://vimeo.com/{u}',[]],
 ['Tinder','https://tinder.com/@{u}',['unk'=>1]],
 ['Twitch','https://www.twitch.tv/{u}',['unk'=>1]],
 ['Roblox','https://www.roblox.com/user.aspx?username={u}',['unk'=>1]],
 ['Chess.com','https://api.chess.com/pub/player/{u}',['api'=>'chess','page'=>'https://www.chess.com/member/{u}']],
 ['Flickr','https://www.flickr.com/people/{u}',[]],
 ['OnlyFans','https://onlyfans.com/{u}',['unk'=>1]],
 ['Patreon','https://www.patreon.com/{u}',[]],
 ['Pinterest','https://www.pinterest.com/{u}/',[]],
 ['Spotify','https://open.spotify.com/user/{u}',['unk'=>1]],
 ['SoundCloud','https://soundcloud.com/{u}',[]],
 ['Linktree','https://linktr.ee/{u}',[]],
 ['Medium','https://medium.com/@{u}',[]],
 ['Wikipedia','https://en.wikipedia.org/wiki/User:{u}',[]],
 ['DeviantArt','https://www.deviantart.com/{u}',[]],
 ['Tripadvisor','https://www.tripadvisor.com/Profile/{u}',['unk'=>1]],
 ['eBay','https://www.ebay.com/usr/{u}',['unk'=>1]],
 ['Poshmark','https://poshmark.com/closet/{u}',['unk'=>1]],
 ['Whatnot','https://www.whatnot.com/user/{u}',['unk'=>1]],
 ['Dribbble','https://dribbble.com/{u}',[]],
 ['Behance','https://www.behance.net/{u}',[]],
 ['Tumblr','https://{u}.tumblr.com',[]],
 ['Goodreads','https://www.goodreads.com/{u}',['unk'=>1]],
 ['GitHub','https://api.github.com/users/{u}',['api'=>'github','page'=>'https://github.com/{u}']],
];

function meta($html, $p) {
  $q = preg_quote($p, '/');
  if (preg_match('/<meta[^>]+(?:property|name)=["\']'.$q.'["\'][^>]*content=["\']([^"\']*)/i', $html, $m) ||
      preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$q.'["\']/i', $html, $m))
    return html_entity_decode($m[1]);
  return '';
}

$mh = curl_multi_init(); $handles = []; $out = [];
foreach ($SITES as $i => [$name, $tpl, $o]) {
  $page = str_replace('{u}', rawurlencode($h), $o['page'] ?? $tpl);
  $out[$i] = ['name'=>$name, 'url'=>$page, 'status'=>'unknown'];
  if (!empty($o['unk'])) continue;
  $ch = curl_init(str_replace('{u}', rawurlencode($h), $tpl));
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_FOLLOWLOCATION=>1, CURLOPT_MAXREDIRS=>4, CURLOPT_TIMEOUT=>8,
    CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_ENCODING=>'', CURLOPT_HTTPHEADER=>['Accept-Language: en'],
    CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36']);
  curl_multi_add_handle($mh, $ch); $handles[$i] = $ch;
}
do { curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1); } while ($running);

foreach ($handles as $i => $ch) {
  $o = $SITES[$i][2]; $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $body = curl_multi_getcontent($ch);
  curl_multi_remove_handle($mh, $ch); curl_close($ch);
  if ($code == 404 || $code == 410) { $out[$i]['status'] = 'free'; continue; }
  if ($code != 200) continue;
  if (!empty($o['api'])) {
    $j = json_decode($body, true); if (!is_array($j)) continue;
    $pic = ['github'=>$j['avatar_url']??'', 'reddit'=>$j['data']['snoovatar_img'] ?? explode('?', $j['data']['icon_img'] ?? '')[0],
            'bsky'=>$j['avatar']??'', 'chess'=>$j['avatar']??''][$o['api']];
    $t = ['github'=>$j['name']??'', 'reddit'=>'', 'bsky'=>$j['displayName']??'', 'chess'=>$j['name']??''][$o['api']];
    $out[$i] += ['status'=>'found'] ; $out[$i]['status']='found'; $out[$i]['pic']=$pic; $out[$i]['title']=$t; continue;
  }
  $body = substr($body, 0, 400000);
  if (!empty($o['miss']) && preg_match($o['miss'], $body)) { $out[$i]['status'] = 'free'; continue; }
  $out[$i]['status'] = 'found'; $out[$i]['pic'] = meta($body, 'og:image'); $out[$i]['title'] = meta($body, 'og:title');
}
echo json_encode(array_values($out));
