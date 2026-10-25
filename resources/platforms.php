<?php

/*
|--------------------------------------------------------------------------
| The built-in platforms
|--------------------------------------------------------------------------
|
| Every platform is plain data, so an app overrides or adds one from
| config('share.custom') with the same keys:
|
|   label        name shown to readers (passed through __())
|   kind         web     an http(s) share page: tracked through the redirect route
|                app     a mobile app scheme or mailto:/sms:: counted by beacon
|                client  done in the browser (copy, native, print, qr, embed)
|   url          the share address; {placeholders} allowed (mastodon's {instance})
|   query        query parameters; each value is a template, and one that
|                renders empty is left out. Values are RFC 3986 encoded.
|   separator    put between url and query (default "?"; sms uses "?&")
|   requires     fields the share must have; "a|b" means either
|   limit        characters a post may have; the message is shortened to fit
|   link_weight  characters a link counts as against the limit (X counts 23)
|   medium       utm_medium for links tagged from this platform
|   popup        [width, height] of the desktop popup, or false for a new tab
|   mobile       true when it only works on phones (hidden on desktop by the script)
|   icon         key in resources/icons.php, raw <svg>, or a CSS class ("fab fa-x")
|   aliases      other keys that mean this platform ("twitter" => x)
|
| Placeholders: {url} {title} {message} {text} {image} {via} {hashtags}
| {hashtag_line} {first_hashtag} {tags} {site} and any other key of the
| platform's definition. {message} is the post text: the message given,
| else the title. {body} is the message, a blank line and the link.
|
*/

return [

    'facebook' => [
        'label' => 'Facebook',
        'url' => 'https://www.facebook.com/sharer/sharer.php',
        'query' => ['u' => '{url}', 'hashtag' => '{first_hashtag}'],
        'color' => '#0866FF',
    ],

    'x' => [
        'label' => 'X',
        'url' => 'https://x.com/intent/post',
        'query' => ['text' => '{message}', 'url' => '{url}', 'via' => '{via}', 'hashtags' => '{hashtags}'],
        'requires' => ['message|url'],
        'limit' => 280,
        'link_weight' => 23,
        'color' => '#000000',
        'aliases' => ['twitter'],
    ],

    'threads' => [
        'label' => 'Threads',
        'url' => 'https://www.threads.com/intent/post',
        'query' => ['text' => '{message}', 'url' => '{url}'],
        'requires' => ['message|url'],
        'limit' => 500,
        'color' => '#000000',
    ],

    'bluesky' => [
        'label' => 'Bluesky',
        'url' => 'https://bsky.app/intent/compose',
        'query' => ['text' => '{body}'],
        'requires' => ['message|url'],
        'limit' => 300,
        'color' => '#1185FE',
    ],

    'mastodon' => [
        'label' => 'Mastodon',
        'url' => 'https://{instance}/share',
        'query' => ['text' => '{body}'],
        'requires' => ['message|url'],
        'instance' => 'mastodon.social',
        'limit' => 500,
        'link_weight' => 23,
        'color' => '#6364FF',
    ],

    // Share₂Fedi asks the reader for their own instance (Mastodon, Misskey, Pleroma…).
    'fediverse' => [
        'label' => 'Fediverse',
        'url' => 'https://s2f.kytta.dev/',
        'query' => ['text' => '{body}'],
        'requires' => ['message|url'],
        'limit' => 500,
        'link_weight' => 23,
        'icon' => 'mastodon',
        'color' => '#8C8DFF',
    ],

    'linkedin' => [
        'label' => 'LinkedIn',
        'url' => 'https://www.linkedin.com/sharing/share-offsite/',
        'query' => ['url' => '{url}'],
        'color' => '#0A66C2',
    ],

    'whatsapp' => [
        'label' => 'WhatsApp',
        'url' => 'https://wa.me/',
        'query' => ['text' => '{body}'],
        'requires' => ['message|url'],
        'medium' => 'messaging',
        // wa.me hands over to the app or WhatsApp Web; a popup only gets in the way.
        'popup' => false,
        'color' => '#25D366',
    ],

    'telegram' => [
        'label' => 'Telegram',
        'url' => 'https://t.me/share/url',
        'query' => ['url' => '{url}', 'text' => '{message}'],
        'medium' => 'messaging',
        'color' => '#26A5E4',
    ],

    'messenger' => [
        'label' => 'Messenger',
        'kind' => 'app',
        'url' => 'fb-messenger://share/',
        'query' => ['link' => '{url}'],
        'medium' => 'messaging',
        'mobile' => true,
        'color' => '#0866FF',
    ],

    'viber' => [
        'label' => 'Viber',
        'kind' => 'app',
        'url' => 'viber://forward',
        'query' => ['text' => '{body}'],
        'requires' => ['message|url'],
        'medium' => 'messaging',
        'mobile' => true,
        'color' => '#7360F2',
    ],

    'line' => [
        'label' => 'LINE',
        'url' => 'https://social-plugins.line.me/lineit/share',
        'query' => ['url' => '{url}', 'text' => '{message}'],
        'medium' => 'messaging',
        'color' => '#00C300',
    ],

    'snapchat' => [
        'label' => 'Snapchat',
        'url' => 'https://www.snapchat.com/scan',
        'query' => ['attachmentUrl' => '{url}'],
        'popup' => false,
        'color' => '#FFFC00',
        'foreground' => '#000000',
    ],

    'reddit' => [
        'label' => 'Reddit',
        'url' => 'https://www.reddit.com/submit',
        'query' => ['url' => '{url}', 'title' => '{title}'],
        'color' => '#FF4500',
    ],

    'pinterest' => [
        'label' => 'Pinterest',
        'url' => 'https://www.pinterest.com/pin/create/button/',
        'query' => ['url' => '{url}', 'media' => '{image}', 'description' => '{message}'],
        'requires' => ['url', 'image'],
        'limit' => 500,
        'color' => '#BD081C',
    ],

    'tumblr' => [
        'label' => 'Tumblr',
        'url' => 'https://www.tumblr.com/widgets/share/tool',
        'query' => ['posttype' => 'link', 'canonicalUrl' => '{url}', 'title' => '{title}', 'caption' => '{text}', 'tags' => '{tags}'],
        'color' => '#36465D',
    ],

    'hackernews' => [
        'label' => 'Hacker News',
        'url' => 'https://news.ycombinator.com/submitlink',
        'query' => ['u' => '{url}', 't' => '{title}'],
        'limit' => 80,
        'color' => '#F0652F',
    ],

    'flipboard' => [
        'label' => 'Flipboard',
        'url' => 'https://share.flipboard.com/bookmarklet/popout',
        'query' => ['v' => '2', 'url' => '{url}', 'title' => '{title}'],
        'medium' => 'bookmark',
        'color' => '#E12828',
    ],

    'buffer' => [
        'label' => 'Buffer',
        'url' => 'https://buffer.com/add',
        'query' => ['url' => '{url}', 'text' => '{message}'],
        'color' => '#231F20',
    ],

    'xing' => [
        'label' => 'XING',
        'url' => 'https://www.xing.com/spi/shares/new',
        'query' => ['url' => '{url}'],
        'color' => '#006567',
    ],

    'vk' => [
        'label' => 'VK',
        'url' => 'https://vk.com/share.php',
        'query' => ['url' => '{url}', 'title' => '{title}', 'image' => '{image}'],
        'color' => '#0077FF',
    ],

    'odnoklassniki' => [
        'label' => 'OK',
        'url' => 'https://connect.ok.ru/offer',
        'query' => ['url' => '{url}', 'title' => '{title}', 'imageUrl' => '{image}'],
        'color' => '#EE8208',
        'aliases' => ['ok'],
    ],

    'weibo' => [
        'label' => 'Weibo',
        'url' => 'https://service.weibo.com/share/share.php',
        'query' => ['url' => '{url}', 'title' => '{message}', 'pic' => '{image}'],
        'color' => '#E6162D',
    ],

    'qzone' => [
        'label' => 'Qzone',
        'url' => 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey',
        'query' => ['url' => '{url}', 'title' => '{title}', 'summary' => '{text}', 'pics' => '{image}'],
        'color' => '#FECE00',
        'foreground' => '#000000',
    ],

    'naver' => [
        'label' => 'Naver',
        'url' => 'https://share.naver.com/web/shareView',
        'query' => ['url' => '{url}', 'title' => '{title}'],
        'color' => '#03C75A',
    ],

    'hatena' => [
        'label' => 'Hatena',
        'url' => 'https://b.hatena.ne.jp/add',
        'query' => ['mode' => 'confirm', 'url' => '{url}', 'title' => '{title}'],
        'medium' => 'bookmark',
        'color' => '#00A4DE',
    ],

    'trello' => [
        'label' => 'Trello',
        'url' => 'https://trello.com/add-card',
        'query' => ['mode' => 'popup', 'url' => '{url}', 'name' => '{title}', 'desc' => '{text}'],
        'medium' => 'work',
        'color' => '#0052CC',
    ],

    'teams' => [
        'label' => 'Microsoft Teams',
        'url' => 'https://teams.microsoft.com/share',
        'query' => ['href' => '{url}', 'msgText' => '{message}', 'preview' => 'true'],
        'medium' => 'work',
        'icon' => 'microsoft',
        'color' => '#5B5FC7',
    ],

    'classroom' => [
        'label' => 'Google Classroom',
        'url' => 'https://classroom.google.com/share',
        'query' => ['url' => '{url}', 'title' => '{title}'],
        'medium' => 'work',
        'color' => '#0F9D58',
    ],

    'gmail' => [
        'label' => 'Gmail',
        'url' => 'https://mail.google.com/mail/',
        'query' => ['view' => 'cm', 'fs' => '1', 'su' => '{title}', 'body' => '{mail_body}'],
        'requires' => ['message|url'],
        'medium' => 'email',
        'color' => '#EA4335',
    ],

    'outlook' => [
        'label' => 'Outlook',
        'url' => 'https://outlook.live.com/mail/0/deeplink/compose',
        'query' => ['subject' => '{title}', 'body' => '{mail_body}'],
        'requires' => ['message|url'],
        'medium' => 'email',
        'icon' => 'email',
        'color' => '#0078D4',
    ],

    'email' => [
        'label' => 'Email',
        'kind' => 'app',
        'url' => 'mailto:',
        'query' => ['subject' => '{title}', 'body' => '{mail_body}'],
        'requires' => ['message|url'],
        'medium' => 'email',
        'color' => '#6B7280',
        'aliases' => ['mail'],
    ],

    'sms' => [
        'label' => 'Text message',
        'kind' => 'app',
        'url' => 'sms:',
        // "?&body=" is the form both iOS and Android read.
        'separator' => '?&',
        'query' => ['body' => '{body}'],
        'requires' => ['message|url'],
        'medium' => 'messaging',
        'mobile' => true,
        'color' => '#16A34A',
    ],

    /*
    | Done in the browser by resources/js/share.js.
    */

    'copy' => [
        'label' => 'Copy link',
        'kind' => 'client',
        'requires' => ['url|message'],
        'medium' => 'copy',
        'color' => '#334155',
        'aliases' => ['link'],
    ],

    // The device's own share sheet (Instagram, TikTok, Signal, AirDrop… whatever is installed).
    'native' => [
        'label' => 'More apps',
        'kind' => 'client',
        'requires' => ['url|message|files'],
        'medium' => 'share',
        'color' => '#334155',
    ],

    'qr' => [
        'label' => 'QR code',
        'kind' => 'client',
        'requires' => ['url'],
        'medium' => 'qr',
        'color' => '#111827',
    ],

    'embed' => [
        'label' => 'Embed',
        'kind' => 'client',
        'requires' => ['embed'],
        'medium' => 'embed',
        'color' => '#334155',
    ],

    'print' => [
        'label' => 'Print',
        'kind' => 'client',
        'requires' => [],
        'medium' => 'print',
        'color' => '#334155',
    ],

];
