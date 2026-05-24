<?php

// ===================== CONFIG =====================
define('BOT_TOKEN', '8705550649:AAHaUwOGEchF0Apwu4iTBXljb9Eku7FfCAU');
define('OWNER_ID', '6818439612'); // Asosiy admin — o'chirib bo'lmaydi
define('JAVA_API', 'https://api.mcsrvstat.us/3/');
define('BEDROCK_API', 'https://api.mcsrvstat.us/bedrock/3/');
define('JAVA_ICON_API', 'https://api.mcsrvstat.us/icon/');
define('HOSTING_API', 'https://ipinfo.io/');
// ==================================================

define('STATE_DIR', __DIR__ . '/data/');

function ensureStateDir() {
    if (!is_dir(STATE_DIR)) mkdir(STATE_DIR, 0755, true);
}

function getState($userId) {
    ensureStateDir();
    $file = STATE_DIR . "state_$userId.json";
    if (file_exists($file)) return json_decode(file_get_contents($file), true);
    return ['state' => null];
}

function setState($userId, $state, $extra = []) {
    ensureStateDir();
    $file = STATE_DIR . "state_$userId.json";
    file_put_contents($file, json_encode(array_merge(['state' => $state], $extra)));
}

function clearState($userId) {
    ensureStateDir();
    $file = STATE_DIR . "state_$userId.json";
    if (file_exists($file)) unlink($file);
}

function getAdmins() {
    ensureStateDir();
    $file = STATE_DIR . "admins.json";
    if (file_exists($file)) return json_decode(file_get_contents($file), true) ?: [];
    return [];
}

function saveAdmins($admins) {
    ensureStateDir();
    $file = STATE_DIR . "admins.json";
    file_put_contents($file, json_encode(array_values($admins)));
}

function getBanned() {
    ensureStateDir();
    $file = STATE_DIR . "banned.json";
    if (file_exists($file)) return json_decode(file_get_contents($file), true) ?: [];
    return [];
}

function saveBanned($banned) {
    ensureStateDir();
    $file = STATE_DIR . "banned.json";
    file_put_contents($file, json_encode(array_values($banned)));
}

function isAdmin($userId) {
    if (strval($userId) === OWNER_ID) return true;
    return in_array(strval($userId), getAdmins());
}

function addAdmin($userId) {
    if (strval($userId) === OWNER_ID) return false;
    $admins = getAdmins();
    if (!in_array(strval($userId), $admins)) {
        $admins[] = strval($userId);
        saveAdmins($admins);
        return true;
    }
    return false;
}

function removeAdmin($userId) {
    if (strval($userId) === OWNER_ID) return false;
    $admins = array_filter(getAdmins(), fn($id) => $id !== strval($userId));
    saveAdmins($admins);
    return true;
}

function isBanned($userId) {
    return in_array(strval($userId), getBanned());
}

function banUser($userId) {
    $banned = getBanned();
    if (!in_array(strval($userId), $banned)) {
        $banned[] = strval($userId);
        saveBanned($banned);
        return true;
    }
    return false;
}

function unbanUser($userId) {
    $banned = array_filter(getBanned(), fn($id) => $id !== strval($userId));
    saveBanned($banned);
    return true;
}

// ─── Telegram API ────────────────────────────────

function tgRequest($method, $params) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chatId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    return tgRequest('sendMessage', $params);
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($keyboard) $params['reply_markup'] = $keyboard;
    return tgRequest('editMessageText', $params);
}

function answerCallback($callbackId, $text = '') {
    tgRequest('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

function sendPhotoBinary($chatId, $imageData, $caption, $keyboard = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendPhoto';
    $boundary = uniqid();
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"chat_id\"\r\n\r\n$chatId\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"caption\"\r\n\r\n$caption\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"parse_mode\"\r\n\r\nMarkdown\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"icon.png\"\r\nContent-Type: image/png\r\n\r\n$imageData\r\n";
    if ($keyboard) {
        $kb = is_array($keyboard) ? json_encode($keyboard) : $keyboard;
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"reply_markup\"\r\n\r\n$kb\r\n";
    }
    $body .= "--$boundary--\r\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: multipart/form-data; boundary=$boundary"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// ─── Keyboards ───────────────────────────────────

function mainMenuKeyboard($userId = null) {
    $kb = [
        [
            ['text' => '☕ Java server', 'callback_data' => 'btn_java'],
            ['text' => '🟩 Bedrock server', 'callback_data' => 'btn_bedrock'],
        ],
        [
            ['text' => '💿 Hosting info', 'callback_data' => 'btn_hosting'],
            ['text' => 'ℹ️ Bot haqida', 'callback_data' => 'btn_about'],
        ],
        [
            ['text' => '☎️ Murojaat', 'callback_data' => 'btn_contact'],
        ],
    ];
    if ($userId && isAdmin($userId)) {
        $kb[] = [['text' => '💾 Boshqarish', 'callback_data' => 'admin_panel']];
    }
    return json_encode(['inline_keyboard' => $kb]);
}

function adminPanelKeyboard() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🚫 Foydalanuvchi banlash', 'callback_data' => 'admin_ban']],
            [['text' => '✅ Foydalanuvchi bandan olish', 'callback_data' => 'admin_unban']],
            [['text' => '📢 Foydalanuvchiga xabar', 'callback_data' => 'admin_msg']],
            [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']],
        ]
    ]);
}

function replyToUserKeyboard($targetId) {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '💿 Javob berish', 'callback_data' => "reply_$targetId"]],
        ]
    ]);
}

// ─── Helpers ─────────────────────────────────────

function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UZCraftSpyBot/1.0');
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function resolveHost($address) {
    $host = explode(':', trim($address))[0];
    $ip = gethostbyname($host);
    return $ip ?: $host;
}

function detectProvider($org) {
    $o = strtolower($org);
    if (str_contains($o, 'amazon') || str_contains($o, 'aws')) return '☁️ Amazon AWS';
    if (str_contains($o, 'google')) return '☁️ Google Cloud';
    if (str_contains($o, 'microsoft') || str_contains($o, 'azure')) return '☁️ Microsoft Azure';
    if (str_contains($o, 'hetzner')) return '🖥 Hetzner';
    if (str_contains($o, 'ovh')) return '🖥 OVH';
    if (str_contains($o, 'digitalocean')) return '🖥 DigitalOcean';
    if (str_contains($o, 'linode') || str_contains($o, 'akamai')) return '🖥 Linode/Akamai';
    if (str_contains($o, 'vultr')) return '🖥 Vultr';
    if (str_contains($o, 'oracle')) return '☁️ Oracle Cloud';
    if (str_contains($o, 'cloudflare')) return '🔶 Cloudflare';
    if (str_contains($o, 'aeza')) return '🖥 Aeza';
    if (str_contains($o, 'contabo')) return '🖥 Contabo';
    if (str_contains($o, 'hostinger')) return '🖥 Hostinger';
    if (str_contains($o, 'minehut')) return '🎮 Minehut';
    if (str_contains($o, 'aternos')) return '🎮 Aternos';
    if (str_contains($o, 'shockbyte')) return '🖥 Shockbyte';
    if (str_contains($o, 'apex')) return '🖥 Apex Hosting';
    if (str_contains($o, 'bisect')) return '🖥 BisectHosting';
    if (str_contains($o, 'pebblehost')) return '🖥 PebbleHost';
    if (str_contains($o, 'scalacube')) return '🖥 ScalaCube';
    if (str_contains($o, 'mcprohosting')) return '🖥 MCProHosting';
    return '🖥 ' . $org;
}

function buildServerText($address, $bedrock = false) {
    $apiUrl = ($bedrock ? BEDROCK_API : JAVA_API) . urlencode($address);
    $data = fetchJson($apiUrl);
    $edition = $bedrock ? '🟩 Bedrock' : '☕ Java';

    if (!$data) return "❌ API ga ulanib bo'lmadi.";
    if (empty($data['online'])) return "🔴 *Server offline*\n📡 IP: `$address`\n🎮 *Edition:* $edition";

    $online = $data['players']['online'] ?? 0;
    $max = $data['players']['max'] ?? 0;
    $version = $data['version'] ?? "Noma'lum";
    $software = $data['software'] ?? "Noma'lum";
    $hostname = $data['hostname'] ?? $address;
    $port = $data['port'] ?? ($bedrock ? 19132 : 25565);
    $motd = $data['motd']['clean'][0] ?? '';

    $playerList = '';
    if (!empty($data['players']['list'])) {
        $names = array_slice($data['players']['list'], 0, 10);
        $nameStr = implode(', ', array_map(fn($p) => '`' . ($p['name'] ?? '?') . '`', $names));
        $playerList = "\n👥 *Online:* $nameStr";
        if (count($data['players']['list']) > 10)
            $playerList .= ' (+' . (count($data['players']['list']) - 10) . ')';
    }

    $pluginsLine = '';
    if (!$bedrock) {
        $plugins = $data['plugins']['names'] ?? [];
        $mods = $data['mods']['names'] ?? [];
        if (!empty($plugins)) {
            $pluginsLine .= "\n🔌 *Pluginlar (" . count($plugins) . "):* " . implode(', ', array_slice($plugins, 0, 5));
            if (count($plugins) > 5) $pluginsLine .= ' (+' . (count($plugins) - 5) . ')';
        }
        if (!empty($mods)) {
            $pluginsLine .= "\n⚙️ *Modlar (" . count($mods) . "):* " . implode(', ', array_slice($mods, 0, 5));
            if (count($mods) > 5) $pluginsLine .= ' (+' . (count($mods) - 5) . ')';
        }
    }

    $ip = resolveHost($address);
    $h = fetchJson(HOSTING_API . "$ip/json");
    $hostingLine = '';
    if ($h && !empty($h['org'])) {
        $hostingLine = "\n🏢 *Hosting:* " . detectProvider($h['org']);
        if (!empty($h['city']) && !empty($h['country']))
            $hostingLine .= "\n🌍 *Joylashuv:* {$h['city']}, {$h['country']}";
    }

    return "✅ *$hostname*\n" .
        "━━━━━━━━━━━━━━━━━\n" .
        "🎮 *Edition:* $edition\n" .
        "📡 *IP:* `$address`\n" .
        "🔢 *Port:* `$port`\n" .
        "🟢 *Holat:* Online\n" .
        "👾 *Versiya:* `$version`\n" .
        "💾 *Software:* `$software`\n" .
        "👤 *O'yinchilar:* `$online/$max`" .
        $playerList . "\n" .
        "📝 *MOTD:* " . ($motd ?: 'Yoq') .
        $pluginsLine . $hostingLine . "\n" .
        "━━━━━━━━━━━━━━━━━";
}

function buildHostingMessage($address) {
    $ip = resolveHost($address);
    $data = fetchJson(HOSTING_API . "$ip/json");
    if (!$data) return "❌ Ma'lumot olib bo'lmadi.";
    $org = $data['org'] ?? "Noma'lum";
    return "💿 *Hosting Ma'lumoti*\n" .
        "━━━━━━━━━━━━━━━━━\n" .
        "🌐 *IP:* `$ip`\n" .
        "🏢 *Hosting:* " . detectProvider($org) . "\n" .
        "🏙 *Shahar:* " . ($data['city'] ?? "Noma'lum") . ", " . ($data['region'] ?? '') . "\n" .
        "🌍 *Mamlakat:* " . ($data['country'] ?? "Noma'lum") . "\n" .
        "🖥 *Hostname:* `" . ($data['hostname'] ?? "Noma'lum") . "`\n" .
        "🕐 *Timezone:* " . ($data['timezone'] ?? "Noma'lum") . "\n" .
        "━━━━━━━━━━━━━━━━━";
}

function checkAndSendServer($chatId, $address, $bedrock, $keyboard = null) {
    $waiting = sendMessage($chatId, "⏳ Tekshirilmoqda...");
    $result = buildServerText($address, $bedrock);

    if (!$bedrock) {
        $iconUrl = JAVA_ICON_API . urlencode($address);
        // URL orqali rasm yuborish (binary dan ishonchliroq)
        $params = [
            'chat_id' => $chatId,
            'photo' => $iconUrl,
            'caption' => $result,
            'parse_mode' => 'Markdown',
        ];
        if ($keyboard) $params['reply_markup'] = $keyboard;
        $res = tgRequest('sendPhoto', $params);
        if (!empty($res['ok'])) {
            tgRequest('deleteMessage', ['chat_id' => $chatId, 'message_id' => $waiting['result']['message_id']]);
            return;
        }
    }
    editMessage($chatId, $waiting['result']['message_id'], $result, $keyboard);
}

// ─── Main ────────────────────────────────────────

$input = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

// ─── Callback Query ──────────────────────────────
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = $cb['message']['chat']['id'];
    $userId = $cb['from']['id'];
    $data = $cb['data'];
    answerCallback($cb['id']);

    if ($data === 'btn_java') {
        setState($userId, 'waiting_java');
        sendMessage($chatId, "☕ *Java* server IP yoki domenini yuboring:\n_(masalan: `play.hypixel.net`)_");

    } elseif ($data === 'btn_bedrock') {
        setState($userId, 'waiting_bedrock');
        sendMessage($chatId, "🟩 *Bedrock* server IP yoki domenini yuboring:\n_(masalan: `play.nethergames.org`)_");

    } elseif ($data === 'btn_hosting') {
        setState($userId, 'waiting_hosting');
        sendMessage($chatId, "🌐 IP yoki domen yuboring:");

    } elseif ($data === 'btn_about') {
        sendMessage($chatId,
            "🤖 *UZ CraftSpy Bot*\n\n" .
            "☕ *Java server:*\n• Online/Offline, O'yinchilar\n• Versiya, Software\n• Plugin va Mod soni\n• Server icon\n• Hosting provayder\n\n" .
            "🟩 *Bedrock server:*\n• Online/Offline, O'yinchilar\n• Versiya, Port\n• Hosting provayder\n\n" .
            "💿 *Hosting info:*\n• Provayder, Shahar, Mamlakat\n• Hostname, Timezone",
            mainMenuKeyboard($userId)
        );

    } elseif ($data === 'btn_contact') {
        setState($userId, 'waiting_contact');
        sendMessage($chatId, "💻 Iltimos xabaringizni yuboring, sizning xabaringiz adminlarga yuboriladi 💾");

    } elseif (str_starts_with($data, 'reply_') && isAdmin($userId)) {
        $targetId = str_replace('reply_', '', $data);
        setState($userId, 'admin_reply', ['target_id' => $targetId]);
        sendMessage($chatId, "✍️ Javob matnini yuboring:");

    } elseif ($data === 'admin_panel' && isAdmin($userId)) {
        sendMessage($chatId, "💾 *Boshqarish paneli*", adminPanelKeyboard());

    } elseif ($data === 'admin_ban' && isAdmin($userId)) {
        setState($userId, 'admin_ban');
        sendMessage($chatId, "🚫 Banlash uchun foydalanuvchi ID sini yuboring:");

    } elseif ($data === 'admin_unban' && isAdmin($userId)) {
        setState($userId, 'admin_unban');
        sendMessage($chatId, "✅ Bandan olish uchun foydalanuvchi ID sini yuboring:");

    } elseif ($data === 'admin_msg' && isAdmin($userId)) {
        setState($userId, 'admin_msg_id');
        sendMessage($chatId, "📢 Xabar yuboriladigan foydalanuvchi ID sini yuboring:");

    } elseif ($data === 'admin_back') {
        sendMessage($chatId, "👋 *UZ CraftSpy Bot*", mainMenuKeyboard($userId));
    }
    exit;
}

// ─── Message ─────────────────────────────────────
if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = $msg['chat']['id'];
    $userId = $msg['from']['id'];
    $text = trim($msg['text'] ?? '');

    // Ban tekshiruv
    if (isBanned($userId)) {
        sendMessage($chatId, "🚫 Siz botdan foydalana olmaysiz.");
        exit;
    }

    // /start
    if (str_starts_with($text, '/start')) {
        clearState($userId);
        sendMessage($chatId,
            "👋 *UZ CraftSpy Bot*\n\n" .
            "🎮 Minecraft server ma'lumotlarini bilib oling!\n\n" .
            "Guruhda:\n`/server <IP>` — Java\n`/java <IP>` — Java\n`/bedrock <IP>` — Bedrock\n\n" .
            "Lichkada: quyidagi tugmalardan foydalaning 👇",
            mainMenuKeyboard($userId)
        );
        exit;
    }

    // /addadmin (ID) — faqat owner
    if (str_starts_with($text, '/addadmin') && strval($userId) === OWNER_ID) {
        $parts = explode(' ', $text, 2);
        $targetId = trim($parts[1] ?? '');
        if (!$targetId || !is_numeric($targetId)) {
            sendMessage($chatId, "❗ Ishlatish: `/addadmin 123456789`");
        } elseif (addAdmin($targetId)) {
            sendMessage($chatId, "✅ `$targetId` adminlikka qo'shildi.");
        } else {
            sendMessage($chatId, "⚠️ Bu foydalanuvchi allaqachon admin.");
        }
        exit;
    }

    // /unadmin (ID) — faqat owner
    if (str_starts_with($text, '/unadmin') && strval($userId) === OWNER_ID) {
        $parts = explode(' ', $text, 2);
        $targetId = trim($parts[1] ?? '');
        if (!$targetId || !is_numeric($targetId)) {
            sendMessage($chatId, "❗ Ishlatish: `/unadmin 123456789`");
        } elseif (strval($targetId) === OWNER_ID) {
            sendMessage($chatId, "❌ Asosiy adminni olib bo'lmaydi.");
        } else {
            removeAdmin($targetId);
            sendMessage($chatId, "✅ `$targetId` adminlikdan olindi.");
        }
        exit;
    }

    // /server yoki /java
    if (str_starts_with($text, '/server') || str_starts_with($text, '/java')) {
        $parts = explode(' ', $text, 2);
        if (empty($parts[1])) {
            sendMessage($chatId, "❗ Ishlatish: `/server <IP>`\nMisol: `/server play.hypixel.net`");
        } else {
            checkAndSendServer($chatId, trim($parts[1]), false, mainMenuKeyboard($userId));
        }
        exit;
    }

    // /bedrock
    if (str_starts_with($text, '/bedrock')) {
        $parts = explode(' ', $text, 2);
        if (empty($parts[1])) {
            sendMessage($chatId, "❗ Ishlatish: `/bedrock <IP>`\nMisol: `/bedrock play.nethergames.org`");
        } else {
            checkAndSendServer($chatId, trim($parts[1]), true, mainMenuKeyboard($userId));
        }
        exit;
    }

    // /cancel
    if (str_starts_with($text, '/cancel')) {
        clearState($userId);
        sendMessage($chatId, "❌ Bekor qilindi.", mainMenuKeyboard($userId));
        exit;
    }

    // State based
    $state = getState($userId);

    if ($state['state'] === 'waiting_java') {
        clearState($userId);
        checkAndSendServer($chatId, $text, false, mainMenuKeyboard($userId));
        exit;
    }

    if ($state['state'] === 'waiting_bedrock') {
        clearState($userId);
        checkAndSendServer($chatId, $text, true, mainMenuKeyboard($userId));
        exit;
    }

    if ($state['state'] === 'waiting_hosting') {
        clearState($userId);
        $waiting = sendMessage($chatId, "⏳ Tekshirilmoqda...");
        $result = buildHostingMessage($text);
        editMessage($chatId, $waiting['result']['message_id'], $result, mainMenuKeyboard($userId));
        exit;
    }

    // Murojaat xabari
    if ($state['state'] === 'waiting_contact') {
        clearState($userId);
        $username = $msg['from']['username'] ?? null;
        $name = $msg['from']['first_name'] ?? 'Noma\'lum';
        $userLink = $username ? "@$username" : "[$name](tg://user?id=$userId)";
        $adminMsg = "📱 *Yangi murojaat!*\n\n" .
            "👤 *Kimdan:* $userLink\n" .
            "🆔 *ID:* `$userId`\n\n" .
            "💬 *Xabar:*\n$text";

        // Barcha adminlarga yuborish
        $admins = getAdmins();
        $admins[] = OWNER_ID;
        foreach ($admins as $adminId) {
            sendMessage($adminId, $adminMsg, replyToUserKeyboard($userId));
        }
        sendMessage($chatId, "✅ Xabaringiz adminlarga yuborildi! Tez orada javob berishadi.", mainMenuKeyboard($userId));
        exit;
    }

    // Admin javob berish
    if ($state['state'] === 'admin_reply' && isAdmin($userId)) {
        $targetId = $state['target_id'] ?? null;
        clearState($userId);
        if ($targetId) {
            $res = sendMessage($targetId, "📩 *Admin javob berdi:*\n\n$text");
            if ($res['ok']) {
                sendMessage($chatId, "✅ Javob yuborildi.", adminPanelKeyboard());
            } else {
                sendMessage($chatId, "❌ Yuborib bo'lmadi. Foydalanuvchi botni bloklagan bo'lishi mumkin.", adminPanelKeyboard());
            }
        }
        exit;
    }

    // Admin: banlash
    if ($state['state'] === 'admin_ban' && isAdmin($userId)) {
        clearState($userId);
        if (!is_numeric($text)) {
            sendMessage($chatId, "❗ Faqat raqam (ID) yuboring.", adminPanelKeyboard());
        } elseif (strval($text) === OWNER_ID || isAdmin($text)) {
            sendMessage($chatId, "❌ Admin yoki owner ni banlash mumkin emas.", adminPanelKeyboard());
        } else {
            banUser($text);
            sendMessage($chatId, "✅ `$text` banlandi.", adminPanelKeyboard());
        }
        exit;
    }

    // Admin: bandan olish
    if ($state['state'] === 'admin_unban' && isAdmin($userId)) {
        clearState($userId);
        if (!is_numeric($text)) {
            sendMessage($chatId, "❗ Faqat raqam (ID) yuboring.", adminPanelKeyboard());
        } else {
            unbanUser($text);
            sendMessage($chatId, "✅ `$text` bandan olindi.", adminPanelKeyboard());
        }
        exit;
    }

    // Admin: xabar yuborish — ID olish
    if ($state['state'] === 'admin_msg_id' && isAdmin($userId)) {
        if (!is_numeric($text)) {
            sendMessage($chatId, "❗ Faqat raqam (ID) yuboring.");
            exit;
        }
        setState($userId, 'admin_msg_text', ['target_id' => $text]);
        sendMessage($chatId, "📝 Endi xabar matnini yuboring:");
        exit;
    }

    // Admin: xabar yuborish — matn yuborish
    if ($state['state'] === 'admin_msg_text' && isAdmin($userId)) {
        $targetId = $state['target_id'] ?? null;
        clearState($userId);
        if ($targetId) {
            $res = sendMessage($targetId, "📢 *Admin xabari:*\n\n$text");
            if ($res['ok']) {
                sendMessage($chatId, "✅ Xabar `$targetId` ga yuborildi.", adminPanelKeyboard());
            } else {
                sendMessage($chatId, "❌ Yuborib bo'lmadi. ID noto'g'ri yoki foydalanuvchi botni bloklagan.", adminPanelKeyboard());
            }
        }
        exit;
    }
}
