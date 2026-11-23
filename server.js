const http = require('http'), fs = require('fs'), path = require('path');
const PORT = process.env.PORT || 3000, HOST = process.env.HOST || '127.0.0.1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

const SITES = [
  ['Instagram','https://www.instagram.com/{u}/',{unk:1}],
  ['Threads','https://www.threads.net/@{u}',{unk:1}],
  ['Twitter / X','https://x.com/{u}',{unk:1}],
  ['Facebook','https://www.facebook.com/{u}',{unk:1}],
  ['TikTok','https://www.tiktok.com/@{u}',{unk:1}],
  ['LinkedIn','https://www.linkedin.com/in/{u}',{unk:1}],
  ['Snapchat','https://www.snapchat.com/add/{u}',{unk:1}],
  ['Steam','https://steamcommunity.com/id/{u}',{miss:/could not be found/i}],
  ['Etsy','https://www.etsy.com/people/{u}',{unk:1}],
  ['VSCO','https://vsco.co/{u}',{unk:1}],
  ['Reddit','https://www.reddit.com/user/{u}/about.json',{api:'reddit',page:'https://www.reddit.com/user/{u}'}],
  ['Telegram','https://t.me/{u}',{miss:/<title>Telegram: Contact/i}],
  ['YouTube','https://www.youtube.com/@{u}',{}],
  ['Bluesky','https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor={u}.bsky.social',{api:'bsky',page:'https://bsky.app/profile/{u}.bsky.social'}],
  ['Mastodon','https://mastodon.social/@{u}',{}],
  ['Vimeo','https://vimeo.com/{u}',{}],
  ['Tinder','https://tinder.com/@{u}',{unk:1}],
  ['Twitch','https://www.twitch.tv/{u}',{unk:1}],
  ['Roblox','https://www.roblox.com/user.aspx?username={u}',{unk:1}],
  ['Chess.com','https://api.chess.com/pub/player/{u}',{api:'chess',page:'https://www.chess.com/member/{u}'}],
  ['Flickr','https://www.flickr.com/people/{u}',{}],
  ['OnlyFans','https://onlyfans.com/{u}',{unk:1}],
  ['Patreon','https://www.patreon.com/{u}',{}],
  ['Pinterest','https://www.pinterest.com/{u}/',{}],
  ['Spotify','https://open.spotify.com/user/{u}',{unk:1}],
  ['SoundCloud','https://soundcloud.com/{u}',{}],
  ['Linktree','https://linktr.ee/{u}',{}],
  ['Medium','https://medium.com/@{u}',{}],
  ['Wikipedia','https://en.wikipedia.org/wiki/User:{u}',{}],
  ['DeviantArt','https://www.deviantart.com/{u}',{}],
  ['Tripadvisor','https://www.tripadvisor.com/Profile/{u}',{unk:1}],
  ['eBay','https://www.ebay.com/usr/{u}',{unk:1}],
  ['Poshmark','https://poshmark.com/closet/{u}',{unk:1}],
  ['Whatnot','https://www.whatnot.com/user/{u}',{unk:1}],
  ['Dribbble','https://dribbble.com/{u}',{}],
  ['Behance','https://www.behance.net/{u}',{}],
  ['Tumblr','https://{u}.tumblr.com',{}],
  ['Goodreads','https://www.goodreads.com/{u}',{unk:1}],
  ['GitHub','https://api.github.com/users/{u}',{api:'github',page:'https://github.com/{u}'}],
];

const meta = (html, p) => {
  const m = html.match(new RegExp('<meta[^>]+(?:property|name)=["\']' + p + '["\'][^>]*content=["\']([^"\']*)', 'i'))
        || html.match(new RegExp('<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' + p + '["\']', 'i'));
  return m ? m[1].replace(/&amp;/g, '&').replace(/&quot;/g, '"') : '';
};

async function check([name, tpl, o], u) {
  const url = tpl.replace('{u}', encodeURIComponent(u));
  const page = (o.page || tpl).replace('{u}', encodeURIComponent(u));
  const out = { name, url: page, status: 'unknown' };
  if (o.unk) return out;
  try {
    const r = await fetch(url, { headers: { 'User-Agent': UA, 'Accept-Language': 'en' }, redirect: 'follow', signal: AbortSignal.timeout(8000) });
    if (r.status === 404 || r.status === 410) return { ...out, status: 'free' };
    if (r.status !== 200) return out;
    if (o.api) {
      const j = await r.json();
      const pic = { github: j.avatar_url, reddit: j.data && (j.data.snoovatar_img || (j.data.icon_img || '').split('?')[0]), bsky: j.avatar, chess: j.avatar }[o.api];
      const dn = { github: j.name, reddit: '', bsky: j.displayName, chess: j.name }[o.api];
      return { ...out, status: 'found', pic: pic || '', title: dn || '' };
    }
    const html = (await r.text()).slice(0, 400000);
    if (o.miss && o.miss.test(html)) return { ...out, status: 'free' };
    return { ...out, status: 'found', pic: meta(html, 'og:image'), title: meta(html, 'og:title') };
  } catch { return out; }
}

http.createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/api/scan') {
    const h = (u.searchParams.get('u') || '').trim();
    if (!/^[A-Za-z0-9._-]{1,40}$/.test(h)) { res.writeHead(400); return res.end('Invalid handle'); }
    res.writeHead(200, { 'Content-Type': 'application/x-ndjson', 'Cache-Control': 'no-store' });
    await Promise.all(SITES.map(s => check(s, h).then(r => res.write(JSON.stringify(r) + '\n'))));
    return res.end();
  }
  if (u.pathname === '/') { res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); return fs.createReadStream(path.join(__dirname, 'index.html')).pipe(res); }
  res.writeHead(404); res.end();
}).listen(PORT, HOST, () => console.log(`HandleScout running at http://${HOST}:${PORT}`));
