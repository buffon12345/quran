<?php

// ---------------------------------------------------------------------
// -------------------------- CONFIGURATION --------------------------
// ---------------------------------------------------------------------

// حقوق الملكية: أحمد محمد - رقم الهاتف: 01270116359 - يوزر تليجرام: @buffon_1
// هذا الكود تم شراؤه وتطويره بواسطة أحمد محمد

$botToken = "8395846026:AAGK8cX5HP1aiDsZo3WIJcAKpAOzMcCMkL0"; // استبدل هذا بالتوكن الخاص بك
define('API_URL', 'https://api.telegram.org/bot' . $botToken . '/');

// إعدادات الأدمن
$adminUsers = [8090383823, 987654321]; // أضف هنا ID المطورين (يمكنك الحصول عليه من @userinfobot)
$adminChatId = 8090383823; // أضف هنا ID الدردشة الشخصية للمطور
$developerUsername = "@buffon_1"; // يوزر المطور
$developerPhone = "01270116359"; // رقم المطور

// إعدادات قاعدة بيانات الرسائل
$messagesFile = 'messages.json';
$statsFile = 'bot_stats.json';

// ---------------------------------------------------------------------
// -------------------------- DATA STORAGE ---------------------------
// ---------------------------------------------------------------------

$userDataFile = 'user_data.json';

function getUserData($userId) {
    global $userDataFile;
    if (!file_exists($userDataFile)) return [];
    $allData = json_decode(file_get_contents($userDataFile), true);
    return $allData[$userId] ?? [];
}

function saveUserData($userId, $data) {
    global $userDataFile;
    $allData = [];
    if (file_exists($userDataFile)) {
        $allData = json_decode(file_get_contents($userDataFile), true);
    }
    if (empty($data)) {
        unset($allData[$userId]);
    } else {
        $allData[$userId] = $data;
    }
    file_put_contents($userDataFile, json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ---------------------------------------------------------------------
// ------------------------ ADMIN FUNCTIONS ---------------------------
// ---------------------------------------------------------------------

function isAdmin($userId) {
    global $adminUsers;
    return in_array($userId, $adminUsers);
}

function saveMessage($fromUserId, $message, $type = 'user_to_admin') {
    global $messagesFile;
    
    $messages = [];
    if (file_exists($messagesFile)) {
        $messages = json_decode(file_get_contents($messagesFile), true);
    }
    
    $messageId = uniqid();
    $messages[$messageId] = [
        'from_user_id' => $fromUserId,
        'message' => $message,
        'type' => $type,
        'timestamp' => time(),
        'replied' => false
    ];
    
    file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $messageId;
}

function getUnreadMessages() {
    global $messagesFile;
    
    if (!file_exists($messagesFile)) return [];
    
    $messages = json_decode(file_get_contents($messagesFile), true);
    $unread = [];
    
    foreach ($messages as $id => $message) {
        if ($message['type'] == 'user_to_admin' && !$message['replied']) {
            $unread[$id] = $message;
        }
    }
    
    return $unread;
}

function markMessageAsReplied($messageId) {
    global $messagesFile;
    
    if (!file_exists($messagesFile)) return false;
    
    $messages = json_decode(file_get_contents($messagesFile), true);
    if (isset($messages[$messageId])) {
        $messages[$messageId]['replied'] = true;
        file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    return false;
}

function updateStats($action) {
    global $statsFile;
    
    $stats = [];
    if (file_exists($statsFile)) {
        $stats = json_decode(file_get_contents($statsFile), true);
    }
    
    $today = date('Y-m-d');
    
    if (!isset($stats[$today])) {
        $stats[$today] = [
            'users' => [],
            'quran_views' => 0,
            'adhkar_views' => 0,
            'prayer_views' => 0,
            'tasbeeh_uses' => 0,
            'messages_sent' => 0,
            'total_users' => count($stats) > 0 ? end($stats)['total_users'] : 0
        ];
    }
    
    switch ($action) {
        case 'new_user':
            $stats[$today]['total_users']++;
            break;
        case 'quran_view':
            $stats[$today]['quran_views']++;
            break;
        case 'adhkar_view':
            $stats[$today]['adhkar_views']++;
            break;
        case 'prayer_view':
            $stats[$today]['prayer_views']++;
            break;
        case 'tasbeeh_use':
            $stats[$today]['tasbeeh_uses']++;
            break;
        case 'message_sent':
            $stats[$today]['messages_sent']++;
            break;
    }
    
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getBotStats() {
    global $statsFile;
    
    if (!file_exists($statsFile)) return null;
    
    $stats = json_decode(file_get_contents($statsFile), true);
    $today = date('Y-m-d');
    
    $todayStats = $stats[$today] ?? [
        'quran_views' => 0,
        'adhkar_views' => 0,
        'prayer_views' => 0,
        'tasbeeh_uses' => 0,
        'messages_sent' => 0
    ];
    
    $totalUsers = 0;
    $totalQuran = 0;
    $totalAdhkar = 0;
    $totalPrayer = 0;
    $totalTasbeeh = 0;
    $totalMessages = 0;
    
    foreach ($stats as $day => $data) {
        $totalUsers = max($totalUsers, $data['total_users'] ?? 0);
        $totalQuran += $data['quran_views'] ?? 0;
        $totalAdhkar += $data['adhkar_views'] ?? 0;
        $totalPrayer += $data['prayer_views'] ?? 0;
        $totalTasbeeh += $data['tasbeeh_uses'] ?? 0;
        $totalMessages += $data['messages_sent'] ?? 0;
    }
    
    return [
        'today' => $todayStats,
        'total' => [
            'users' => $totalUsers,
            'quran_views' => $totalQuran,
            'adhkar_views' => $totalAdhkar,
            'prayer_views' => $totalPrayer,
            'tasbeeh_uses' => $totalTasbeeh,
            'messages_sent' => $totalMessages
        ]
    ];
}

// ---------------------------------------------------------------------
// ------------------------ TELEGRAM FUNCTIONS -------------------------
// ---------------------------------------------------------------------

function apiRequest($method, $parameters) {
    $handle = curl_init(API_URL . $method);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    if ($method === 'sendPhoto' || $method === 'editMessageMedia') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);
    } else {
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($parameters));
    }

    $response = curl_exec($handle);
    curl_close($handle);
    return json_decode($response, true);
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {
    apiRequest('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'Markdown', 'reply_markup' => json_encode($keyboard)]);
}

// =====================================================================
// === NEW & MODIFIED TELEGRAM FUNCTIONS FOR QURAN PHOTO VIEWER ======
// =====================================================================

function sendPhoto($chatId, $photoUrl, $keyboard = null, $caption = null) {
    $parameters = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
    ];
    if ($keyboard) {
        $parameters['reply_markup'] = json_encode($keyboard);
    }
    if ($caption) {
        $parameters['caption'] = $caption;
        $parameters['parse_mode'] = 'Markdown'; 
    }
    $url = API_URL . "sendPhoto?" . http_build_query($parameters);
    return json_decode(file_get_contents($url), true);
}

function editMessagePhoto($chatId, $messageId, $photoUrl, $keyboard = null) {
    $mediaPayload = [
        'type' => 'photo',
        'media' => $photoUrl
    ];
    $parameters = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'media' => json_encode($mediaPayload)
    ];
    if ($keyboard) {
        $parameters['reply_markup'] = json_encode($keyboard);
    }
    
    $handle = curl_init(API_URL . "editMessageMedia");
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($parameters));
    $response = curl_exec($handle);
    curl_close($handle);
    return json_decode($response, true);
}

function getQuranKeyboard($pageNumber) {
    $prevPage = max(1, $pageNumber - 1);
    $nextPage = min(604, $pageNumber + 1);
    
    $prevCallback = ($pageNumber > 1) ? 'quran_page_' . $prevPage : 'noop';
    $nextCallback = ($pageNumber < 604) ? 'quran_page_' . $nextPage : 'noop';

    return [
        'inline_keyboard' => [
            [['text' => "• صفحة $pageNumber •", 'callback_data' => 'noop']],
            [['text' => "صفحة السابقة", 'callback_data' => $prevCallback], ['text' => "صفحة التالية", 'callback_data' => $nextCallback]],
            [['text' => "🔙 رجوع", 'callback_data' => 'quran_menu_back']]
        ]
    ];
}

function getQuranPageUrl($pageNumber) {
    return "https://quran.ksu.edu.sa/png_big/" . $pageNumber . ".png";
}

function handleQuranPageDisplay($chatId, $pageNumber, $messageId = null) {
    if ($pageNumber < 1 || $pageNumber > 604) {
        return false;
    }
    
    updateStats('quran_view');
    
    $url = getQuranPageUrl($pageNumber);
    $keyboard = getQuranKeyboard($pageNumber);
    $caption = "📖 *القرآن الكريم - صفحة رقم {$pageNumber}*\n\nتصفح الصفحات باستخدام الأزرار أدناه 👇";
    
    if ($messageId) {
        editMessagePhoto($chatId, $messageId, $url, $keyboard);
    } else {
        sendPhoto($chatId, $url, $keyboard, $caption);
    }
    return true;
}

// =====================================================================

// ---------------------------------------------------------------------
// ---------------------------- DATA & LISTS ---------------------------
// ---------------------------------------------------------------------

$adhkar_lists = [
    'sabah' => [
        "أَعُوذُ بِاللهِ مِنْ الشَّيْطَانِ الرَّجِيمِ: {اللّهُ لاَ إِلَـهَ إِلاَّ هُوَ الْحَيُّ الْقَيُّومُ...} - آية الكرسي.",
        "قراءة سورة الإخلاص (3 مرات).",
        // ... (بقية الأذكار كما هي)
    ],
    // ... (بقية الأقسام)
];

$prayer_data = [
    'Saudi Arabia' => ['🇸🇦 السعودية', ['الرياض', 'جدة', 'مكة المكرمة', /* ... */]],
    // ... (بقية الدول)
];

$asma_al_husna_list = ["الله", "الرحمن", "الرحيم", /* ... */];

$tasbeeh_phrases = [
    'سبحان الله', 'الحمد لله', 'الله أكبر', /* ... */
];

function generateCountryKeyboard($page = 0) {
    global $prayer_data;
    $countries_per_page = 8; $country_keys = array_keys($prayer_data); $total_countries = count($country_keys); $total_pages = ceil($total_countries / $countries_per_page); $keyboard = []; $start = $page * $countries_per_page; $end = min($start + $countries_per_page, $total_countries); $row = [];
    for ($i = $start; $i < $end; $i++) {
        $country_key = $country_keys[$i]; $country_name_with_flag = $prayer_data[$country_key][0]; $row[] = ['text' => $country_name_with_flag, 'callback_data' => "prayer_country_{$country_key}"];
        if (count($row) == 2) { $keyboard[] = $row; $row = []; }
    }
    if (!empty($row)) $keyboard[] = $row;
    $nav_row = [];
    if ($page > 0) $nav_row[] = ['text' => '⬅️ السابق', 'callback_data' => 'prayer_page_' . ($page - 1)];
    if ($page < $total_pages - 1) $nav_row[] = ['text' => 'التالي ➡️', 'callback_data' => 'prayer_page_' . ($page + 1)];
    if (!empty($nav_row)) $keyboard[] = $nav_row;
    $keyboard[] = [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']];
    return ['inline_keyboard' => $keyboard];
}

// ---------------------------------------------------------------------
// ---------------------------- BOT LOGIC ------------------------------
// ---------------------------------------------------------------------

$startMessage = "🌙 *مرحبًا بك في بوت القرآن الكريم* 🌙\n\nهنا يبدأ صفاء قلبك، وسكينتك، ووصلك مع كلام الله 🤍\n\nدع همومك جانبًا، واستمع لآياتٍ تُنير طريقك وتُهدي روحك ✨\n\n🤲 *اللهم اجعل القرآن ربيع قلوبنا ونور صدورنا، وجلاء أحزاننا وذهاب همومنا.*";

$mainMenuKeyboard = ['inline_keyboard' => [
    [['text' => '📖 القرآن الكريم', 'callback_data' => 'quran_menu']],
    [['text' => '☀️ أذكار اليوم', 'callback_data' => 'adhkar_menu_main'], ['text' => '📿 سبحة الخير', 'callback_data' => 'tasbeeh_menu']],
    [['text' => '🕌 مواقيت الصلاة', 'callback_data' => 'prayer_page_0'], ['text' => '✨ أسماء الله الحسنى', 'callback_data' => 'asma_menu_0']],
    [['text' => '📞 التواصل مع المطور', 'callback_data' => 'contact_developer']]
]];

$adminMenuKeyboard = ['inline_keyboard' => [
    [['text' => '📊 إحصائيات البوت', 'callback_data' => 'admin_stats']],
    [['text' => '📨 الرسائل الواردة', 'callback_data' => 'admin_messages']],
    [['text' => '📢 إرسال إشعار للكل', 'callback_data' => 'admin_broadcast']],
    [['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'main_menu']]
]];

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message']; 
    $chatId = $message['chat']['id']; 
    $text = $message['text']; 
    $userId = $message['from']['id'];
    $firstName = $message['from']['first_name'] ?? 'مستخدم';
    $username = $message['from']['username'] ?? 'لا يوجد';
    
    // تحديث الإحصائيات للمستخدم الجديد
    $userData = getUserData($userId);
    if (!isset($userData['first_seen'])) {
        $userData['first_seen'] = time();
        $userData['username'] = $username;
        $userData['first_name'] = $firstName;
        saveUserData($userId, $userData);
        updateStats('new_user');
    }
    
    if ($text === '/start') {
        unset($userData['state']);
        saveUserData($userId, $userData);
        
        // لو كان المستخدم أدمن، نعرض له قائمة الأدمن
        if (isAdmin($userId)) {
            $adminStartMessage = "👑 *مرحبًا يا أدمن!*\n\nيمكنك التحكم في البوت من خلال القائمة أدناه:";
            apiRequest('sendMessage', [
                'chat_id' => $chatId, 
                'text' => $adminStartMessage, 
                'parse_mode' => 'Markdown', 
                'reply_markup' => json_encode($adminMenuKeyboard)
            ]);
        } else {
            apiRequest('sendMessage', [
                'chat_id' => $chatId, 
                'text' => $startMessage, 
                'parse_mode' => 'Markdown', 
                'reply_markup' => json_encode($mainMenuKeyboard)
            ]);
        }
    } 
    // إذا كان المستخدم في وضع إرسال رسالة للمطور
    elseif (isset($userData['state']) && $userData['state'] === 'sending_message') {
        $messageId = saveMessage($userId, $text);
        
        // إرسال تنبيه للمطور
        global $adminChatId, $developerUsername;
        $adminNotification = "📩 *رسالة جديدة من مستخدم*\n\n";
        $adminNotification .= "👤 الاسم: {$firstName}\n";
        $adminNotification .= "🆔 المعرف: @{$username} ({$userId})\n\n";
        $adminNotification .= "📝 الرسالة:\n{$text}\n\n";
        $adminNotification .= "🔑 معرِّف الرسالة: `{$messageId}`";
        
        apiRequest('sendMessage', [
            'chat_id' => $adminChatId,
            'text' => $adminNotification,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '💬 الرد على الرسالة', 'callback_data' => "admin_reply_{$messageId}"]
                ]]
            ])
        ]);
        
        // تأكيد للمستخدم
        apiRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ *تم إرسال رسالتك بنجاح!*\n\nسيتم الرد عليك قريبًا إن شاء الله.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($mainMenuKeyboard)
        ]);
        
        unset($userData['state']);
        saveUserData($userId, $userData);
        updateStats('message_sent');
    }
    // إذا كان الأدمن يرد على رسالة
    elseif (isset($userData['state']) && strpos($userData['state'], 'replying_to_') === 0) {
        if (isAdmin($userId)) {
            $messageId = str_replace('replying_to_', '', $userData['state']);
            $messages = json_decode(file_get_contents($messagesFile), true);
            
            if (isset($messages[$messageId])) {
                $targetUserId = $messages[$messageId]['from_user_id'];
                
                // إرسال الرسالة للمستخدم
                $replyMessage = "📬 *رد من المطور*\n\n{$text}\n\n";
                $replyMessage .= "يمكنك الرد عن طريق الضغط على '📞 التواصل مع المطور' في القائمة الرئيسية.";
                
                apiRequest('sendMessage', [
                    'chat_id' => $targetUserId,
                    'text' => $replyMessage,
                    'parse_mode' => 'Markdown'
                ]);
                
                // تأكيد للأدمن
                apiRequest('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "✅ *تم إرسال ردك بنجاح!*",
                    'parse_mode' => 'Markdown'
                ]);
                
                // تحديث حالة الرسالة
                markMessageAsReplied($messageId);
            }
            
            unset($userData['state']);
            saveUserData($userId, $userData);
        }
    }
    // إذا كان الأدمن يرسل إشعار للكل
    elseif (isset($userData['state']) && $userData['state'] === 'broadcasting') {
        if (isAdmin($userId)) {
            // قراءة جميع المستخدمين
            $allUsers = [];
            if (file_exists($userDataFile)) {
                $allData = json_decode(file_get_contents($userDataFile), true);
                $allUsers = array_keys($allData);
            }
            
            $successCount = 0;
            $failCount = 0;
            
            // إرسال الإشعار لكل مستخدم
            foreach ($allUsers as $user) {
                try {
                    apiRequest('sendMessage', [
                        'chat_id' => $user,
                        'text' => "📢 *إشعار من المطور*\n\n{$text}",
                        'parse_mode' => 'Markdown'
                    ]);
                    $successCount++;
                    usleep(50000); // تأخير 0.05 ثانية لتجنب حظر تيليجرام
                } catch (Exception $e) {
                    $failCount++;
                }
            }
            
            // تقرير للأدمن
            apiRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => "✅ *تم إرسال الإشعار بنجاح!*\n\n✅ تم بنجاح: {$successCount}\n❌ فشل: {$failCount}",
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($adminMenuKeyboard)
            ]);
            
            unset($userData['state']);
            saveUserData($userId, $userData);
        }
    }
    elseif (is_numeric($text)) {
        $userData = getUserData($userId);
        if (isset($userData['state']) && $userData['state'] === 'awaiting_page_number') {
            $pageNumber = intval($text);
            if ($pageNumber >= 1 && $pageNumber <= 604) {
                handleQuranPageDisplay($chatId, $pageNumber);
                unset($userData['state']); 
                saveUserData($userId, $userData);
            } else { 
                apiRequest('sendMessage', ['chat_id' => $chatId, 'text' => "عذراً، الرقم غير صحيح. 🚫\nالرجاء إرسال رقم بين 1 و 604."]); 
            }
        } else {
             apiRequest('sendMessage', ['chat_id' => $chatId, 'text' => "لا أفهم هذا الرقم الآن. الرجاء الضغط على '📖 القرآن الكريم' في القائمة الرئيسية أولاً وإرسال رقم الصفحة المطلوبة."]);
        }
    }
} 
elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query']; 
    $chatId = $callbackQuery['message']['chat']['id']; 
    $messageId = $callbackQuery['message']['message_id']; 
    $userId = $callbackQuery['from']['id']; 
    $data = $callbackQuery['data']; 
    $userData = getUserData($userId);

    if ($data === 'main_menu') {
        unset($userData['state'], $userData['tasbeeh_limit'], $userData['tasbeeh_count'], $userData['tasbeeh_step']);
        saveUserData($userId, $userData);
        
        if (isAdmin($userId)) {
            editMessage($chatId, $messageId, $startMessage, $adminMenuKeyboard);
        } else {
            editMessage($chatId, $messageId, $startMessage, $mainMenuKeyboard);
        }
    }
    // التواصل مع المطور
    elseif ($data === 'contact_developer') {
        global $developerUsername, $developerPhone;
        
        $contactInfo = "📞 *معلومات التواصل مع المطور*\n\n";
        $contactInfo .= "👤 الاسم: أحمد محمد\n";
        $contactInfo .= "📱 رقم الهاتف: `{$developerPhone}`\n";
        $contactInfo .= "✉️ تليجرام: {$developerUsername}\n\n";
        $contactInfo .= "يمكنك إرسال رسالتك مباشرة هنا، وسيتم الرد عليك قريبًا إن شاء الله.";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📝 إرسال رسالة', 'callback_data' => 'send_message']],
                [['text' => '🔙 رجوع', 'callback_data' => 'main_menu']]
            ]
        ];
        
        editMessage($chatId, $messageId, $contactInfo, $keyboard);
    }
    // بدء إرسال رسالة
    elseif ($data === 'send_message') {
        $userData['state'] = 'sending_message';
        saveUserData($userId, $userData);
        
        editMessage($chatId, $messageId, "✍️ *اكتب رسالتك الآن:*\n\nسيتم إرسالها مباشرة إلى المطور. يمكنك كتابة أي استفسار أو ملاحظة أو اقتراح.", [
            'inline_keyboard' => [[['text' => '❌ إلغاء', 'callback_data' => 'contact_developer']]]
        ]);
    }
    // لوحة الأدمن - الإحصائيات
    elseif ($data === 'admin_stats') {
        if (isAdmin($userId)) {
            $stats = getBotStats();
            
            if ($stats) {
                $statsText = "📊 *إحصائيات البوت*\n\n";
                $statsText .= "📅 *إحصائيات اليوم:*\n";
                $statsText .= "📖 مشاهدة القرآن: {$stats['today']['quran_views']}\n";
                $statsText .= "☀️ مشاهدة الأذكار: {$stats['today']['adhkar_views']}\n";
                $statsText .= "🕌 مواقيت الصلاة: {$stats['today']['prayer_views']}\n";
                $statsText .= "📿 استخدام السبحة: {$stats['today']['tasbeeh_uses']}\n";
                $statsText .= "📨 الرسائل المرسلة: {$stats['today']['messages_sent']}\n\n";
                
                $statsText .= "📈 *إحصائيات إجمالية:*\n";
                $statsText .= "👥 إجمالي المستخدمين: {$stats['total']['users']}\n";
                $statsText .= "📖 إجمالي مشاهدات القرآن: {$stats['total']['quran_views']}\n";
                $statsText .= "☀️ إجمالي مشاهدات الأذكار: {$stats['total']['adhkar_views']}\n";
                $statsText .= "🕌 إجمالي مواقيت الصلاة: {$stats['total']['prayer_views']}\n";
                $statsText .= "📿 إجمالي استخدام السبحة: {$stats['total']['tasbeeh_uses']}\n";
                $statsText .= "📨 إجمالي الرسائل: {$stats['total']['messages_sent']}";
                
                editMessage($chatId, $messageId, $statsText, $adminMenuKeyboard);
            }
        }
    }
    // لوحة الأدمن - الرسائل الواردة
    elseif ($data === 'admin_messages') {
        if (isAdmin($userId)) {
            $unreadMessages = getUnreadMessages();
            
            if (empty($unreadMessages)) {
                $messagesText = "📭 *لا توجد رسائل جديدة*";
                $keyboard = $adminMenuKeyboard;
            } else {
                $messagesText = "📨 *الرسائل الواردة ({$unreadMessages})*\n\n";
                $keyboard = ['inline_keyboard' => []];
                
                foreach ($unreadMessages as $id => $message) {
                    $time = date('Y-m-d H:i', $message['timestamp']);
                    $preview = substr($message['message'], 0, 30) . (strlen($message['message']) > 30 ? '...' : '');
                    
                    $keyboard['inline_keyboard'][] = [[
                        'text' => "📩 {$time} - {$preview}",
                        'callback_data' => "admin_view_message_{$id}"
                    ]];
                }
                
                $keyboard['inline_keyboard'][] = [['text' => '🔙 رجوع', 'callback_data' => 'admin_stats']];
            }
            
            editMessage($chatId, $messageId, $messagesText, $keyboard);
        }
    }
    // عرض رسالة محددة
    elseif (strpos($data, 'admin_view_message_') === 0) {
        if (isAdmin($userId)) {
            $messageId = str_replace('admin_view_message_', '', $data);
            $messages = json_decode(file_get_contents($messagesFile), true);
            
            if (isset($messages[$messageId])) {
                $msg = $messages[$messageId];
                $time = date('Y-m-d H:i', $msg['timestamp']);
                $userInfo = getUserData($msg['from_user_id']);
                
                $messageText = "📨 *تفاصيل الرسالة*\n\n";
                $messageText .= "🆔 المعرف: {$msg['from_user_id']}\n";
                $messageText .= "👤 الاسم: {$userInfo['first_name']}\n";
                $messageText .= "✉️ يوزر: @{$userInfo['username']}\n";
                $messageText .= "⏰ الوقت: {$time}\n\n";
                $messageText .= "📝 الرسالة:\n{$msg['message']}";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '💬 الرد على الرسالة', 'callback_data' => "admin_reply_{$messageId}"]],
                        [['text' => '✅ تم الرد', 'callback_data' => "admin_mark_replied_{$messageId}"]],
                        [['text' => '🔙 رجوع للرسائل', 'callback_data' => 'admin_messages']]
                    ]
                ];
                
                editMessage($chatId, $messageId, $messageText, $keyboard);
            }
        }
    }
    // الرد على رسالة
    elseif (strpos($data, 'admin_reply_') === 0) {
        if (isAdmin($userId)) {
            $messageId = str_replace('admin_reply_', '', $data);
            $userData['state'] = "replying_to_{$messageId}";
            saveUserData($userId, $userData);
            
            editMessage($chatId, $messageId, "💬 *اكتب ردك الآن:*\n\nسيتم إرساله للمستخدم مباشرة.", [
                'inline_keyboard' => [[['text' => '❌ إلغاء', 'callback_data' => 'admin_messages']]]
            ]);
        }
    }
    // تم الرد على رسالة
    elseif (strpos($data, 'admin_mark_replied_') === 0) {
        if (isAdmin($userId)) {
            $messageId = str_replace('admin_mark_replied_', '', $data);
            markMessageAsReplied($messageId);
            
            apiRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackQuery['id'],
                'text' => 'تم تحديث حالة الرسالة',
                'show_alert' => true
            ]);
            
            editMessage($chatId, $messageId, "✅ *تم تحديث حالة الرسالة*", $adminMenuKeyboard);
        }
    }
    // إرسال إشعار للكل
    elseif ($data === 'admin_broadcast') {
        if (isAdmin($userId)) {
            $userData['state'] = 'broadcasting';
            saveUserData($userId, $userData);
            
            editMessage($chatId, $messageId, "📢 *إرسال إشعار للجميع*\n\nاكتب الرسالة التي تريد إرسالها لجميع المستخدمين:", [
                'inline_keyboard' => [[['text' => '❌ إلغاء', 'callback_data' => 'admin_stats']]]
            ]);
        }
    }
    // باقي الأوامر القديمة (القرآن، الأذكار، إلخ)...
    elseif ($data === 'quran_menu' || $data === 'quran_menu_back') {
        $userData['state'] = 'awaiting_page_number'; 
        saveUserData($userId, $userData);
        
        $keyboard = ['inline_keyboard' => [[['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']]]];
        
        if ($data === 'quran_menu_back') {
            apiRequest('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
            apiRequest('sendMessage', ['chat_id' => $chatId, 'text' => "~-- 📖 *قسم القرآن الكريم* --~\n\nتفضل بإدخال رقم الصفحة التي ترغب في تلاوتها (من 1 إلى 604) ✍️", 'parse_mode' => 'Markdown', 'reply_markup' => json_encode($keyboard)]);
        } else {
            editMessage($chatId, $messageId, "~-- 📖 *قسم القرآن الكريم* --~\n\nتفضل بإدخال رقم الصفحة التي ترغب في تلاوتها (من 1 إلى 604) ✍️", $keyboard);
        }
    }
    elseif (strpos($data, 'quran_page_') === 0) {
        $pageNumber = intval(str_replace('quran_page_', '', $data));
        
        if ($pageNumber >= 1 && $pageNumber <= 604) {
            handleQuranPageDisplay($chatId, $pageNumber, $messageId);
        } else {
             apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackQuery['id'], 'text' => "لا توجد صفحة برقم {$pageNumber}.", 'show_alert' => false]);
        }
    }
    elseif ($data === 'tasbeeh_menu') {
        updateStats('tasbeeh_use');
        
        $keyboard = ['inline_keyboard' => [
            [['text' => '33 تسبيحة', 'callback_data' => 'tasbeeh_set_33'], ['text' => '100 تسبيحة', 'callback_data' => 'tasbeeh_set_100']],
            [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']]
        ]];
        $text = "~-- 📿 *سبحة الخير* --~\n\nاختر عدد التسبيح لكل ورد (سبحان الله، الحمد لله، إلخ):";
        if (isset($userData['tasbeeh_limit']) && isset($userData['tasbeeh_count']) && $userData['tasbeeh_count'] >= 0 && $userData['tasbeeh_count'] < $userData['tasbeeh_limit']) {
            $remaining = $userData['tasbeeh_limit'] - $userData['tasbeeh_count'];
            $current_phrase = $tasbeeh_phrases[$userData['tasbeeh_step'] ?? 0];
            $text = "📿 لديك ورد لم يكتمل بعد: *{$current_phrase}*\n\nالعدد المتبقي: *{$remaining}* تسبيحة.\n\nأكمل وردك بالضغط على 'متابعة'.";
            array_unshift($keyboard['inline_keyboard'], [['text' => '🔄 متابعة التسبيح', 'callback_data' => 'tasbeeh_continue']]);
        }
        else {
             unset($userData['tasbeeh_count'], $userData['tasbeeh_limit'], $userData['tasbeeh_step'], $userData['tasbeeh_next_step']);
             saveUserData($userId, $userData);
        }
        editMessage($chatId, $messageId, $text, $keyboard);
    }
    elseif (strpos($data, 'tasbeeh_set_') === 0) {
        $userData['tasbeeh_limit'] = intval(str_replace('tasbeeh_set_', '', $data));
        $userData['tasbeeh_count'] = 0; $userData['tasbeeh_step'] = 0;
        saveUserData($userId, $userData);
        $phrase = $tasbeeh_phrases[0];
        $keyboard = ['inline_keyboard' => [[['text' => "{$phrase} (0 / {$userData['tasbeeh_limit']})", 'callback_data' => 'do_tasbeeh']], [['text' => '🔙 العودة', 'callback_data' => 'tasbeeh_menu']]]];
        editMessage($chatId, $messageId, "لنبدأ بـ: *{$phrase}*\n\nاضغط على الزر في الأسفل للعد.", $keyboard);
    }
    elseif ($data === 'tasbeeh_continue' || $data === 'do_tasbeeh') {
        if ($data === 'do_tasbeeh') $userData['tasbeeh_count'] = ($userData['tasbeeh_count'] ?? 0) + 1;
        $current_step = $userData['tasbeeh_step'] ?? 0;
        $phrase = $tasbeeh_phrases[$current_step];
        if ($userData['tasbeeh_count'] >= $userData['tasbeeh_limit']) {
            $next_step = $current_step + 1;
            if ($next_step < count($tasbeeh_phrases)) {
                $userData['tasbeeh_next_step'] = $next_step;
                $success_keyboard = ['inline_keyboard' => [[['text' => '📿 التالي: ' . $tasbeeh_phrases[$next_step], 'callback_data' => 'tasbeeh_next']], [['text' => '🔢 اختيار عدد آخر', 'callback_data' => 'tasbeeh_menu']]]];
                editMessage($chatId, $messageId, "تقبل الله طاعتكم! ✨\n\nأتممتم ورد *{$phrase}* بنجاح.", $success_keyboard);
            } else {
                unset($userData['tasbeeh_count'], $userData['tasbeeh_limit'], $userData['tasbeeh_step'], $userData['tasbeeh_next_step']);
                editMessage($chatId, $messageId, "ما شاء الله! ✨\n\nلقد أتممتم دورة التسبيح كاملة.\nزادكم الله من فضله.", ['inline_keyboard' => [[['text' => 'العودة لقائمة السبحة', 'callback_data' => 'tasbeeh_menu']]]]);
            }
        } else {
             $keyboard = ['inline_keyboard' => [[['text' => "{$phrase} ({$userData['tasbeeh_count']} / {$userData['tasbeeh_limit']})", 'callback_data' => 'do_tasbeeh']], [['text' => '🔙 العودة', 'callback_data' => 'tasbeeh_menu']]]];
             apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackQuery['id']]);
             editMessage($chatId, $messageId, "تسبح الآن: *{$phrase}*\n\nاضغط على الزر في الأسفل للعد.", $keyboard);
        }
        saveUserData($userId, $userData);
    }
    elseif ($data === 'tasbeeh_next') {
        $userData['tasbeeh_step'] = $userData['tasbeeh_next_step']; unset($userData['tasbeeh_next_step']); $userData['tasbeeh_count'] = 0;
        saveUserData($userId, $userData);
        $phrase = $tasbeeh_phrases[$userData['tasbeeh_step']];
        $keyboard = ['inline_keyboard' => [[['text' => "{$phrase} (0 / {$userData['tasbeeh_limit']})", 'callback_data' => 'do_tasbeeh']], [['text' => '🔙 العودة', 'callback_data' => 'tasbeeh_menu']]]];
        editMessage($chatId, $messageId, "لنكمل بـ: *{$phrase}*\n\nاضغط على الزر في الأسفل للعد.", $keyboard);
    }
    elseif (strpos($data, 'prayer_page_') === 0) {
        updateStats('prayer_view');
        $page = intval(str_replace('prayer_page_', '', $data));
        editMessage($chatId, $messageId, "🕌 *مواقيت الصلاة*\n\nاختر دولتك من القائمة أدناه:", generateCountryKeyboard($page));
    }
    elseif (strpos($data, 'prayer_country_') === 0) {
        $country_key = str_replace('prayer_country_', '', $data); $cities = $prayer_data[$country_key][1]; $city_buttons = []; $row = [];
        foreach ($cities as $city) {
            $row[] = ['text' => $city, 'callback_data' => "prayer_city_{$country_key}_{$city}"];
            if (count($row) == 2) { $city_buttons[] = $row; $row = []; }
        }
        if(!empty($row)) $city_buttons[] = $row;
        $city_buttons[] = [['text' => '🔙 اختر دولة أخرى', 'callback_data' => 'prayer_page_0']];
        editMessage($chatId, $messageId, "رائع! الآن اختر محافظتك أو مدينتك:", ['inline_keyboard' => $city_buttons]);
    }
    elseif(strpos($data, 'prayer_city_') === 0){
        $parts = explode('_', $data, 4); $country_key = $parts[2]; $city = $parts[3];
        $prayerData = json_decode(file_get_contents("http://api.aladhan.com/v1/timingsByCity?city=".urlencode($city)."&country=".urlencode($country_key)."&method=4"), true);
        if ($prayerData && $prayerData['code'] == 200) {
            $timings = $prayerData['data']['timings']; $date = $prayerData['data']['date']['readable']; $hijri_date = $prayerData['data']['date']['hijri']['date'];
            $prayerText = "🕌 مواقيت الصلاة لمدينة *{$city}*\n🗓️ {$date} | {$hijri_date}\n\n";
            $prayerText .= "*الفجر:* " . $timings['Fajr'] . "\n*الشروق:* " . $timings['Sunrise'] . "\n*الظهر:* " . $timings['Dhuhr'] . "\n*العصر:* " . $timings['Asr'] . "\n*المغرب:* " . $timings['Maghrib'] . "\n*العشاء:* " . $timings['Isha'];
            editMessage($chatId, $messageId, $prayerText, ['inline_keyboard' => [[['text' => '🔙 اختر مدينة أخرى', 'callback_data' => "prayer_country_{$country_key}"]], [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']]]]);
        } else { editMessage($chatId, $messageId, "عذراً، حدث خطأ في جلب البيانات. قد تكون المدينة غير مدعومة حالياً.", ['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => "prayer_country_{$country_key}"]]]]); }
    }
    elseif ($data === 'adhkar_menu_main') {
        updateStats('adhkar_view');
        $keyboard = ['inline_keyboard' => [[['text' => '☀️ أذكار الصباح', 'callback_data' => 'adhkar_sabah_0'], ['text' => '🌙 أذكار المساء', 'callback_data' => 'adhkar_masaa_0']], [['text' => '🌅 أذكار الاستيقاظ', 'callback_data' => 'adhkar_wakeup_0'], ['text' => '💤 أذكار قبل النوم', 'callback_data' => 'adhkar_sleep_0']], [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']]]];
        editMessage($chatId, $messageId, "~-- ☀️ *أذكار اليوم والليلة* --~\n\nاختر نوع الأذكار لتبدأ القراءة:", $keyboard);
    }
    elseif (strpos($data, 'adhkar_') === 0) {
        $parts = explode('_', $data);
        $type = $parts[1];
        $index = intval($parts[2]);
        $adhkar_count = count($adhkar_lists[$type]);

        $adhkarText = $adhkar_lists[$type][$index];
        $fullText = "📜 *الذكر " . ($index + 1) . " / " . $adhkar_count . "*\n\n" . $adhkarText;
        $nextIndex = ($index + 1) % $adhkar_count;

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 تحديث (الذكر التالي)', 'callback_data' => "adhkar_{$type}_{$nextIndex}"]],
                [['text' => '🔙 العودة لقائمة الأذكار', 'callback_data' => 'adhkar_menu_main']]
            ]
        ];
        
        editMessage($chatId, $messageId, $fullText, $keyboard);
    }
    elseif (strpos($data, 'asma_menu_') === 0) {
        $index = intval(str_replace('asma_menu_', '', $data));
        $currentName = $asma_al_husna_list[$index]; 
        $nextIndex = ($index + 1) % count($asma_al_husna_list);
        $prevIndex = ($index - 1 + count($asma_al_husna_list)) % count($asma_al_husna_list);

        $text = "~-- ✨ *أسماء الله الحسنى* --~\n\n";
        $text .= "✨ *" . $currentName . "* ✨\n\n";
        $text .= "_(الاسم " . ($index + 1) . " من 99)_";
        
        $keyboard = ['inline_keyboard' => [
            [['text' => '➡️ السابق', 'callback_data' => 'asma_menu_' . $prevIndex], ['text' => 'التالي ⬅️', 'callback_data' => 'asma_menu_' . $nextIndex]],
            [['text' => '📜 عرض كل الأسماء', 'callback_data' => 'asma_all']], 
            [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'main_menu']]
        ]];
        editMessage($chatId, $messageId, $text, $keyboard);
    }
    elseif ($data === 'asma_all') {
        $allNamesText = implode("، ", $asma_al_husna_list);
        editMessage($chatId, $messageId, "*أسماء الله الحسنى كاملة:*\n\n" . $allNamesText, ['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'asma_menu_0']]]]);
    }
    elseif ($data === 'noop') {
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackQuery['id'], 'text' => "لا يمكن الانتقال أكثر من هذا.", 'show_alert' => false]);
    }
}

?>