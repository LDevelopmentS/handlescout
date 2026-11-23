# HandleScout

Type a username and see which of 39 sites have a public profile for it, with the profile picture and display name when one is found. Results are Profile found, Not taken, or Unknown (the site blocks automated checks, so the row links you to check manually).

GitHub only stores the code. It cannot run it, so GitHub Pages will not work. Pick one of the two versions below.

## Option 1: Node (run on your computer)

1. Install Node 18 or newer from https://nodejs.org
2. Open a terminal in the `node` folder
3. Run `node server.js`
4. Open http://127.0.0.1:3000

## Option 2: PHP (any shared host or cPanel)

1. Upload everything in the `php` folder to a folder on your web host
2. Open that folder's address in your browser

Needs PHP 7.4+ with cURL and outbound connections allowed.

## Notes

- Instagram, X, TikTok, Facebook, LinkedIn and several others block automated visits, so they will usually show Unknown.
- Profile pictures come from each site's public page preview and may be missing on some sites.
- Only public profile pages are read. Nothing is stored.
- Use responsibly: do not use this to harass, stalk or dox anyone.
